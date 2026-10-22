<?php

namespace App\Proposition;

use App\Chiffrage\ChiffrageCache;
use App\Chiffrage\Poste;
use App\Entity\Demande;
use App\Entity\LigneDePrestation;
use App\Entity\Proposition;
use App\Enum\NiveauBudget;
use App\Enum\StatutProposition;
use App\Enum\TypeLigne;
use App\Enum\TypeProposition;
use App\Repository\DestinationRepository;
use App\Repository\PrestationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Enregistre une version de proposition (ticket #207, docs/outils.md « enregistrer_proposition ») : le texte rédigé
 * par l'agent, les montants et les prestations du chiffrage en cache (jamais ceux du modèle).
 *
 * Avant d'écrire, il vérifie que la rédaction colle au chiffrage : un jour de programme par jour de voyage (nuits + 1,
 * du jour d'arrivée au jour du départ), des points forts pour chaque prestation retenue et pour elles seules, les
 * activités du niveau détaillé placées dans le programme. Toutes les raisons d'un refus sont données d'un coup.
 *
 * La version est enregistrée en brouillon : le client ne la voit qu'une fois que le conseiller l'a relue et envoyée
 * (envoyer(), depuis la fiche demande du backoffice). Les statuts changent par les workflows « proposition » et
 * « demande » (config/packages/workflow.yaml, #210).
 */
final class PropositionService
{
    public function __construct(
        private readonly DenormalizerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly ChiffrageCache $cache,
        private readonly EntityManagerInterface $em,
        private readonly DestinationRepository $destinations,
        private readonly PrestationRepository $prestations,
        private readonly ClockInterface $horloge,
        #[Target('proposition')] private readonly WorkflowInterface $workflowProposition,
        #[Target('demande')] private readonly WorkflowInterface $workflowDemande,
    ) {
    }

    /**
     * La sortie structurée du modèle, dénormalisée puis validée.
     *
     * @param array<mixed> $entree
     *
     * @throws PropositionRefusee
     */
    public function lire(array $entree): PropositionRedigee
    {
        try {
            $redigee = $this->serializer->denormalize($entree, PropositionRedigee::class, null, [
                AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false,
                DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => true,
            ]);
        } catch (PartialDenormalizationException $e) {
            throw new PropositionRefusee(array_map(static fn (NotNormalizableValueException $erreur): string => \sprintf('%s : %s attendu.', $erreur->getPath() ?? 'entrée', implode(' ou ', $erreur->getExpectedTypes() ?? ['autre type'])), $e->getErrors()));
        } catch (ExceptionInterface $e) {
            throw new PropositionRefusee([$e->getMessage()]);
        }

        $violations = $this->validator->validate($redigee);
        if (\count($violations) > 0) {
            $raisons = [];
            foreach ($violations as $violation) {
                $raisons[] = \sprintf('%s : %s', $violation->getPropertyPath(), $violation->getMessage());
            }

            throw new PropositionRefusee($raisons);
        }

        return $redigee;
    }

    /**
     * @throws PropositionRefusee
     */
    public function enregistrer(Demande $demande, PropositionRedigee $redigee): Proposition
    {
        $chiffrage = $this->cache->retrouver($demande, $redigee->chiffrageId)
            ?? throw new PropositionRefusee([\sprintf('Le chiffrage « %s » est inconnu ou a expiré : reprends le chiffrageId du premier message.', $redigee->chiffrageId)]);
        /** @var array{destination: array{slug: string}, etapes: list<array{dateDebut: string, nombreDeNuits: int, logements: list<array{id: int, nom: string}>}>, niveaux: array<string, array{logements: list<array{id: int, nom: string}>, activites: list<array{id: int, nom: string}>}>, options?: list<array{id: int, nom: string, prixEuros: int|float, parPersonneEuros: int|float, participants: int}>} $combinaison */
        $combinaison = $chiffrage['combinaison'] ?? throw new PropositionRefusee(['Ce chiffrage n\'a pas de combinaison retenue.']);
        /** @var array<string, array{postes: list<array{type: string, libelle: string, calcul: string, montantEuros: int|float}>, fraisDeDossierEuros: int|float, totalEuros: int|float, parPersonneEuros: int|float, hypotheses: list<string>}> $niveaux */
        $niveaux = $chiffrage['niveaux'];
        $niveau = $niveaux[$redigee->niveauDetaille->value];
        $activitesDetaillees = $combinaison['niveaux'][$redigee->niveauDetaille->value]['activites'];

        $this->verifier($redigee, $combinaison, $activitesDetaillees);

        $premierJour = new \DateTimeImmutable($combinaison['etapes'][0]['dateDebut']);
        $proposition = (new Proposition())
            ->setDemande($demande)
            ->setVersion($this->remplacerLesBrouillons($demande) + 1)
            ->setType(TypeProposition::Premiere)
            ->setStatut(StatutProposition::Brouillon)
            ->setDestination($this->destinations->findOneBy(['slug' => $combinaison['destination']['slug']]))
            ->setPourquoi($redigee->pourquoi)
            ->setArgumentaire(array_map(static fn (Argument $a): array => ['exigence' => $a->exigence, 'reponse' => $a->reponse], $redigee->argumentaire))
            ->setPrestations($this->presentations($redigee, $combinaison['niveaux']))
            ->setProgramme(array_map(static fn (JourDuProgramme $j): array => [
                'jour' => $j->jour,
                'date' => $premierJour->modify(\sprintf('+%d days', $j->jour - 1))->format('Y-m-d'),
                'titre' => $j->titre,
                'description' => $j->description,
                'activiteIds' => array_values(array_unique($j->activiteIds)),
            ], $redigee->programme))
            ->setNiveauBudget($redigee->niveauDetaille)
            ->setBudgetsParNiveau(array_map(static fn (array $n): array => [
                'totalCentimes' => self::centimes($n['totalEuros']),
                'parPersonneCentimes' => self::centimes($n['parPersonneEuros']),
            ], $niveaux))
            ->setTotalCentimes(self::centimes($niveau['totalEuros']))
            ->setTotalParPersonneCentimes(self::centimes($niveau['parPersonneEuros']))
            ->setFraisDeDossierCentimes(self::centimes($niveau['fraisDeDossierEuros']))
            ->setHypotheses($niveau['hypotheses'])
            ->setOptions(self::options($combinaison['options'] ?? [], $activitesDetaillees))
            ->setChoix(\is_array($chiffrage['choix'] ?? null) ? $chiffrage['choix'] : [])
            ->setExigences(\is_array($chiffrage['exigences'] ?? null) ? $chiffrage['exigences'] : [])
            ->setCreeLe($this->horloge->now());

        foreach ($this->lignes($niveau['postes'], $combinaison, $redigee->niveauDetaille) as $ligne) {
            $proposition->addLigne($ligne);
            $this->em->persist($ligne);
        }
        $demande->addProposition($proposition)->setModifieLe($this->horloge->now());
        $this->em->persist($proposition);
        $this->em->flush();

        return $proposition;
    }

    /**
     * Le conseiller envoie au client un brouillon relu : les autres versions encore valables sont remplacées et la
     * demande passe au statut « proposition envoyée ».
     *
     * @throws \DomainException si la proposition n'est pas un brouillon
     */
    public function envoyer(Proposition $proposition): void
    {
        if (TypeProposition::Premiere !== $proposition->getType()) {
            throw new \DomainException('Une version de confirmation ne s\'envoie pas : elle se confirme depuis « Confirmer la proposition ».');
        }
        if (!$this->workflowProposition->can($proposition, 'envoyer')) {
            throw new \DomainException(\sprintf('La version %d est déjà %s.', $proposition->getVersion(), mb_strtolower($proposition->getStatut()->label())));
        }
        $demande = $proposition->getDemande() ?? throw new \LogicException('Proposition sans demande.');
        if (!$this->workflowDemande->can($demande, 'envoyer_proposition')) {
            throw new \DomainException(\sprintf('La demande est « %s » : une nouvelle proposition ne peut plus lui être envoyée.', $demande->getStatut()->label()));
        }

        // Les versions encore valables (envoyée, confirmée ou en cours de confirmation) sont remplacées ; une version
        // acceptée par le client reste telle quelle.
        foreach ($demande->getPropositions() as $autre) {
            if ($autre !== $proposition && $this->workflowProposition->can($autre, 'remplacer')) {
                $this->workflowProposition->apply($autre, 'remplacer');
            }
        }
        $this->workflowProposition->apply($proposition, 'envoyer');
        $this->workflowDemande->apply($demande, 'envoyer_proposition');
        $proposition->setEnvoyeeLe($this->horloge->now());
        $demande->setModifieLe($this->horloge->now());
        $this->em->flush();
    }

    /**
     * @param array{etapes: list<array{dateDebut: string, nombreDeNuits: int}>, niveaux: array<string, array{logements: list<array{id: int, nom: string}>, activites: list<array{id: int, nom: string}>}>} $combinaison
     * @param list<array{id: int, nom: string}>                                                                                                                                                            $activitesDetaillees
     *
     * @throws PropositionRefusee
     */
    private function verifier(PropositionRedigee $redigee, array $combinaison, array $activitesDetaillees): void
    {
        $raisons = [];

        $nuits = array_sum(array_column($combinaison['etapes'], 'nombreDeNuits'));
        $jours = $nuits + 1;
        if (\count($redigee->programme) !== $jours) {
            $raisons[] = \sprintf('Le programme compte %d jours ; le voyage en compte %d (%d nuits, du jour d\'arrivée au jour du départ).', \count($redigee->programme), $jours, $nuits);
        }

        $retenues = [];
        foreach ($combinaison['niveaux'] as $niveau) {
            foreach ([...$niveau['logements'], ...$niveau['activites']] as $prestation) {
                $retenues[$prestation['id']] = $prestation['nom'];
            }
        }
        $presentees = array_map(static fn (PointsForts $p): int => $p->id, $redigee->prestations);
        foreach (array_diff_key($retenues, array_flip($presentees)) as $id => $nom) {
            $raisons[] = \sprintf('Donne aussi les points forts de « %s » (id %d).', $nom, $id);
        }
        foreach (array_unique(array_diff($presentees, array_keys($retenues))) as $id) {
            $raisons[] = \sprintf('La prestation %d n\'est retenue par aucun niveau : retire-la de prestations.', $id);
        }
        foreach (array_unique(array_diff_assoc($presentees, array_unique($presentees))) as $id) {
            $raisons[] = \sprintf('La prestation %d est présentée deux fois.', $id);
        }

        $detaillees = array_column($activitesDetaillees, 'nom', 'id');
        $placees = array_merge([], ...array_map(static fn (JourDuProgramme $j): array => $j->activiteIds, $redigee->programme));
        foreach (array_diff_key($detaillees, array_flip($placees)) as $id => $nom) {
            $raisons[] = \sprintf('Place l\'activité « %s » (id %d) dans un jour du programme (activiteIds).', $nom, $id);
        }
        foreach (array_unique(array_diff($placees, array_keys($detaillees))) as $id) {
            $raisons[] = \sprintf('L\'activité %d n\'est pas dans le niveau %s : retire-la du programme.', $id, $redigee->niveauDetaille->label());
        }

        if ([] !== $raisons) {
            throw new PropositionRefusee($raisons);
        }
    }

    /**
     * Les options de la combinaison que le niveau détaillé ne contient pas, en centimes.
     *
     * @param list<array{id: int, nom: string, prixEuros: int|float, parPersonneEuros: int|float, participants: int}> $options
     * @param list<array{id: int, nom: string}>                                                                       $activitesDetaillees
     *
     * @return list<array{id: int, nom: string, prixCentimes: int, parPersonneCentimes: int, participants: int}>
     */
    private static function options(array $options, array $activitesDetaillees): array
    {
        $incluses = array_column($activitesDetaillees, 'id');

        return array_values(array_map(static fn (array $o): array => [
            'id' => $o['id'],
            'nom' => $o['nom'],
            'prixCentimes' => self::centimes($o['prixEuros']),
            'parPersonneCentimes' => self::centimes($o['parPersonneEuros']),
            'participants' => $o['participants'],
        ], array_filter($options, static fn (array $o): bool => !\in_array($o['id'], $incluses, true))));
    }

    /**
     * Un brouillon pas encore envoyé passe à « remplacée » ; une version déjà envoyée reste visible du client jusqu'à
     * l'envoi de la nouvelle.
     *
     * @return int le numéro de la dernière version
     */
    private function remplacerLesBrouillons(Demande $demande): int
    {
        $derniere = 0;
        foreach ($demande->getPropositions() as $precedente) {
            $derniere = max($derniere, $precedente->getVersion());
            if (StatutProposition::Brouillon === $precedente->getStatut() && TypeProposition::Premiere === $precedente->getType()) {
                $this->workflowProposition->apply($precedente, 'remplacer');
            }
        }

        return $derniere;
    }

    /**
     * Chaque prestation retenue, avec ses points forts et les niveaux qui la retiennent, logements d'abord.
     *
     * @param array<string, array{logements: list<array{id: int, nom: string}>, activites: list<array{id: int, nom: string}>}> $niveaux
     *
     * @return list<array{id: int, type: string, nom: string, pointsForts: list<string>, niveaux: list<string>}>
     */
    private function presentations(PropositionRedigee $redigee, array $niveaux): array
    {
        $pointsForts = array_column(array_map(static fn (PointsForts $p): array => ['id' => $p->id, 'points' => $p->pointsForts], $redigee->prestations), 'points', 'id');
        $presentations = [];
        foreach (['logements' => TypeLigne::Logement, 'activites' => TypeLigne::Activite] as $cle => $type) {
            foreach ($niveaux as $nomNiveau => $niveau) {
                foreach ($niveau[$cle] as $prestation) {
                    $presentations[$prestation['id']] ??= ['id' => $prestation['id'], 'type' => $type->value, 'nom' => $prestation['nom'], 'pointsForts' => $pointsForts[$prestation['id']], 'niveaux' => []];
                    $presentations[$prestation['id']]['niveaux'][] = $nomNiveau;
                }
            }
        }

        return array_values($presentations);
    }

    /**
     * Les postes du niveau détaillé, en lignes de prestation : un logement par étape (avec ses dates), les transports
     * (fourchette estimée), les activités. Les prestations sont retrouvées par leur nom parmi celles du niveau.
     *
     * @param list<array{type: string, libelle: string, calcul: string, montantEuros: int|float}>                                                                                                          $postes
     * @param array{etapes: list<array{dateDebut: string, nombreDeNuits: int}>, niveaux: array<string, array{logements: list<array{id: int, nom: string}>, activites: list<array{id: int, nom: string}>}>} $combinaison
     *
     * @return list<LigneDePrestation>
     */
    private function lignes(array $postes, array $combinaison, NiveauBudget $niveau): array
    {
        $ids = array_column([...$combinaison['niveaux'][$niveau->value]['logements'], ...$combinaison['niveaux'][$niveau->value]['activites']], 'id', 'nom');
        $lignes = [];
        $etape = 0;
        foreach ($postes as $poste) {
            $ligne = (new LigneDePrestation())
                ->setType(match ($poste['type']) {
                    Poste::HEBERGEMENT => TypeLigne::Logement,
                    Poste::ACTIVITE => TypeLigne::Activite,
                    default => TypeLigne::Transport,
                })
                ->setLibelle($poste['libelle'])
                ->setCalcul($poste['calcul'])
                ->setPrixEstimeCentimes(self::centimes($poste['montantEuros']));
            if (isset($ids[$poste['libelle']]) && Poste::TRANSPORT !== $poste['type']) {
                $ligne->setPrestation($this->prestations->find($ids[$poste['libelle']]));
            }
            // Les postes d'hébergement suivent l'ordre des étapes.
            if (Poste::HEBERGEMENT === $poste['type'] && isset($combinaison['etapes'][$etape])) {
                $debut = new \DateTimeImmutable($combinaison['etapes'][$etape]['dateDebut']);
                $ligne->setDateDebut($debut)
                    ->setDateFin($debut->modify(\sprintf('+%d days', $combinaison['etapes'][$etape]['nombreDeNuits'])))
                    ->setQuantite($combinaison['etapes'][$etape]['nombreDeNuits']);
                ++$etape;
            }
            $lignes[] = $ligne;
        }

        return $lignes;
    }

    private static function centimes(int|float $euros): int
    {
        return (int) round($euros * 100);
    }
}
