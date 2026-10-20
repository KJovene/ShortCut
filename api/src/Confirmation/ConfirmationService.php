<?php

namespace App\Confirmation;

use App\Chiffrage\BudgetCalculator;
use App\Chiffrage\DemandeDeChiffrage;
use App\Chiffrage\Etape;
use App\Chiffrage\Montant;
use App\Chiffrage\Poste;
use App\Entity\Demande;
use App\Entity\LigneDePrestation;
use App\Entity\Prestation;
use App\Entity\Proposition;
use App\Entity\RegleDePrix;
use App\Entity\Utilisateur;
use App\Enum\StatutProposition;
use App\Enum\TypeDeVoyage;
use App\Enum\TypeLigne;
use App\Enum\TypePrestation;
use App\Enum\TypeProposition;
use App\Repository\RegleDePrixRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Confirmation d'une proposition par le conseiller (#209) : seule la version confirmée engage l'agence.
 *
 * - À la première ouverture, une nouvelle version (type « confirmée », statut brouillon) copie les lignes de la
 *   dernière proposition envoyée au client. Le conseiller y saisit les prix vérifiés auprès des prestataires et les
 *   frais de dossier, remplace un logement ou retire une activité ; la proposition envoyée reste intacte.
 * - Les écarts se mesurent ligne par ligne avec le prix estimé, et au total avec la proposition envoyée.
 * - Confirmer exige tous les prix : la marge de la règle de prix s'applique au coût confirmé, comme au chiffrage.
 *   Les statuts suivent les workflows (#210) : la version passe « confirmée », l'estimation « remplacée » (le lien
 *   du client mène désormais à la version confirmée) et la demande « confirmée ».
 */
final class ConfirmationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RegleDePrixRepository $reglesDePrix,
        private readonly BudgetCalculator $calculateur,
        private readonly ClockInterface $horloge,
        #[Target('proposition')] private readonly WorkflowInterface $workflowProposition,
        #[Target('demande')] private readonly WorkflowInterface $workflowDemande,
    ) {
    }

    /**
     * La dernière proposition chiffrée par le chatbot et envoyée au client, ou null s'il n'y en a pas. Une fois
     * confirmée, elle est « remplacée » : c'est alors la dernière première proposition qui précède la version
     * confirmée. Un brouillon du chatbot, jamais envoyé, ne compte pas (#207).
     */
    public function estimation(Demande $demande): ?Proposition
    {
        $propositions = $demande->getPropositions()->toArray();
        $envoyee = self::derniere(array_filter(
            $propositions,
            static fn (Proposition $p): bool => TypeProposition::Premiere === $p->getType() && StatutProposition::Envoyee === $p->getStatut(),
        ));
        $confirmee = self::derniere(array_filter($propositions, static fn (Proposition $p): bool => self::confirmeLEstimation($p)));
        if (null !== $envoyee || null === $confirmee) {
            return $envoyee;
        }

        return self::derniere(array_filter(
            $propositions,
            static fn (Proposition $p): bool => TypeProposition::Premiere === $p->getType()
                && StatutProposition::Remplacee === $p->getStatut()
                && $p->getVersion() < $confirmee->getVersion(),
        ));
    }

    /**
     * La version qui confirme cette estimation : le brouillon en cours, la version déjà confirmée, ou un brouillon
     * créé à l'instant à partir de l'estimation.
     */
    public function versionDeConfirmation(Demande $demande, Proposition $estimation): Proposition
    {
        $existante = self::derniere(array_filter(
            $demande->getPropositions()->toArray(),
            static fn (Proposition $p): bool => self::confirmeLEstimation($p) && $p->getVersion() > $estimation->getVersion(),
        ));
        if (null !== $existante) {
            return $existante;
        }

        $version = (new Proposition())
            ->setVersion(1 + max(array_map(static fn (Proposition $p): int => $p->getVersion(), $demande->getPropositions()->toArray())))
            ->setType(TypeProposition::Confirmee)
            ->setStatut(StatutProposition::Brouillon)
            ->setDestination($estimation->getDestination())
            ->setPourquoi($estimation->getPourquoi())
            ->setArgumentaire($estimation->getArgumentaire())
            ->setPrestations($estimation->getPrestations())
            ->setChoix($estimation->getChoix())
            ->setExigences($estimation->getExigences())
            ->setProgramme($estimation->getProgramme())
            ->setNiveauBudget($estimation->getNiveauBudget())
            ->setTotalCentimes($estimation->getTotalCentimes())
            ->setTotalParPersonneCentimes($estimation->getTotalParPersonneCentimes())
            ->setFraisDeDossierCentimes($estimation->getFraisDeDossierCentimes())
            ->setHypotheses($estimation->getHypotheses())
            ->setCreeLe($this->horloge->now());
        $demande->addProposition($version);
        $this->em->persist($version);

        foreach (self::lignesTriees($estimation) as $ligne) {
            $copie = (new LigneDePrestation())
                ->setType($ligne->getType() ?? TypeLigne::Logement)
                ->setLibelle((string) $ligne->getLibelle())
                ->setPrestation($ligne->getPrestation())
                ->setPrestataire($ligne->getPrestataire())
                ->setDateDebut($ligne->getDateDebut())
                ->setDateFin($ligne->getDateFin())
                ->setQuantite($ligne->getQuantite())
                ->setCalcul($ligne->getCalcul())
                ->setPrixEstimeCentimes($ligne->getPrixEstimeCentimes());
            $version->addLigne($copie);
            $this->em->persist($copie);
        }
        $this->em->flush();

        return $version;
    }

    /**
     * Enregistre les prix saisis (null : pas encore vérifié) et les frais de dossier.
     *
     * @param array<int, int|null> $prixParLigne prix confirmé de chaque ligne, en centimes, par id de ligne
     */
    public function saisir(Proposition $version, array $prixParLigne, int $fraisDeDossierCentimes): void
    {
        self::exigerUnBrouillon($version);
        foreach ($version->getLignes() as $ligne) {
            if (\array_key_exists((int) $ligne->getId(), $prixParLigne)) {
                $ligne->setPrixConfirmeCentimes($prixParLigne[(int) $ligne->getId()]);
            }
        }
        $version->setFraisDeDossierCentimes($fraisDeDossierCentimes);
        $this->em->flush();
    }

    /** Retire une activité de la proposition (le client ne la fera pas). */
    public function retirer(Proposition $version, int $ligneId): void
    {
        self::exigerUnBrouillon($version);
        $ligne = self::ligne($version, $ligneId);
        if (TypeLigne::Activite !== $ligne->getType()) {
            throw new ConfirmationImpossible('Seule une activité peut être retirée.');
        }

        $version->removeLigne($ligne);
        $this->em->flush();
    }

    /**
     * Remplace le logement d'une ligne par un autre de la même destination, aux mêmes dates. Son prix estimé est
     * recalculé avec le service de chiffrage ; le prix confirmé est à saisir de nouveau.
     */
    public function remplacer(Proposition $version, int $ligneId, Prestation $logement): LigneDePrestation
    {
        self::exigerUnBrouillon($version);
        $ligne = self::ligne($version, $ligneId);
        if (TypeLigne::Logement !== $ligne->getType()) {
            throw new ConfirmationImpossible('Seul un logement peut être remplacé.');
        }
        if (!\in_array($logement, $this->logementsDeRemplacement($ligne), true)) {
            throw new ConfirmationImpossible(\sprintf('%s ne peut pas remplacer %s : choisissez un autre logement de la même destination.', $logement->getNom(), $ligne->getLibelle()));
        }

        $estimation = $this->estimerLogement($logement, $ligne, $version->getDemande() ?? throw new \LogicException('Proposition sans demande.'));
        $ligne->setPrestation($logement)
            ->setPrestataire($logement->getPrestataire())
            ->setLibelle((string) $logement->getNom())
            ->setQuantite($estimation['unites'])
            ->setCalcul($estimation['calcul'])
            ->setPrixEstimeCentimes($estimation['centimes'])
            ->setPrixConfirmeCentimes(null);
        $this->em->flush();

        return $ligne;
    }

    /**
     * Les logements qui peuvent remplacer celui de la ligne : ceux de la même destination, triés par nom.
     *
     * @return list<Prestation>
     */
    public function logementsDeRemplacement(LigneDePrestation $ligne): array
    {
        $actuel = $ligne->getPrestation();
        $destination = $actuel?->getDestination();
        if (null === $destination) {
            return [];
        }

        $logements = array_values(array_filter(
            $destination->getPrestations()->toArray(),
            static fn (Prestation $p): bool => TypePrestation::Logement === $p->getType() && $p !== $actuel,
        ));
        usort($logements, static fn (Prestation $a, Prestation $b): int => strcmp((string) $a->getNom(), (string) $b->getNom()));

        return $logements;
    }

    /**
     * Prix estimé d'un logement pour les dates de la ligne et les voyageurs de la demande, avec les règles du
     * chiffrage (tarif de chaque nuit, unités, séjour minimum).
     *
     * @return array{centimes: int, unites: int, calcul: string}
     *
     * @throws ConfirmationImpossible si le logement n'est pas chiffrable à ces dates
     */
    public function estimerLogement(Prestation $logement, LigneDePrestation $ligne, Demande $demande): array
    {
        $debut = $ligne->getDateDebut();
        $fin = $ligne->getDateFin();
        $destination = $logement->getDestination();
        if (null === $debut || null === $fin || $fin <= $debut || null === $destination) {
            throw new ConfirmationImpossible(\sprintf('Les dates de « %s » ne sont pas connues : le logement ne peut pas être chiffré.', $ligne->getLibelle()));
        }

        $resultat = $this->calculateur->calculer(new DemandeDeChiffrage(
            $demande->getTypeDeVoyage() ?? TypeDeVoyage::Sejour,
            $destination,
            max(1, $demande->getAdultes()),
            $demande->agesDesEnfants(),
            null,
            false,
            null,
            false,
            [new Etape($debut, (int) $debut->diff($fin)->days, [$logement])],
            [],
            [],
            [],
            0,
            0,
        ));
        if (null !== $resultat->nonChiffrable) {
            throw new ConfirmationImpossible(\sprintf('%s ne peut pas être chiffré : %s', $logement->getNom(), lcfirst($resultat->nonChiffrable->raison)));
        }

        $niveau = $resultat->niveaux['essentiel'] ?? throw new \LogicException('Chiffrage sans niveau essentiel.');

        $poste = $niveau->postes(Poste::HEBERGEMENT)[0];

        return [
            'centimes' => $poste->centimes,
            'calcul' => $poste->calcul,
            'unites' => (int) ceil(self::voyageurs($demande) / max(1, $logement->getCapacite())),
        ];
    }

    /** Écarts de la version de confirmation avec la proposition envoyée au client. */
    public function comparer(Proposition $version, Proposition $estimation): Comparaison
    {
        $lignes = [];
        $manquantes = [];
        $coutConfirme = 0;
        foreach ($version->getLignes() as $ligne) {
            $lignes[(int) $ligne->getId()] = new Ecart($ligne->getPrixEstimeCentimes(), $ligne->getPrixConfirmeCentimes());
            if (null === $ligne->getPrixConfirmeCentimes()) {
                $manquantes[] = (string) $ligne->getLibelle();
            }
            $coutConfirme += (int) $ligne->getPrixConfirmeCentimes();
        }

        $coutEstime = array_sum(array_map(static fn (LigneDePrestation $l): int => $l->getPrixEstimeCentimes(), $estimation->getLignes()->toArray()));
        $fraisEstimes = $estimation->getFraisDeDossierCentimes();
        $complete = [] === $manquantes;
        $marge = $complete ? BudgetCalculator::marge($coutConfirme, $this->regle()->getMargePourcentage()) : null;
        $total = null === $marge ? null : $coutConfirme + $marge + $version->getFraisDeDossierCentimes();

        return new Comparaison(
            $lignes,
            new Ecart($coutEstime, $complete ? $coutConfirme : null),
            new Ecart($estimation->getTotalCentimes() - $coutEstime - $fraisEstimes, $marge),
            new Ecart($fraisEstimes, $version->getFraisDeDossierCentimes()),
            new Ecart($estimation->getTotalCentimes(), $total),
            new Ecart($estimation->getTotalParPersonneCentimes(), null === $total ? null : (int) round($total / self::voyageurs($version))),
            $manquantes,
        );
    }

    /**
     * Confirme la version : tous les prix doivent être saisis. Les totaux deviennent ceux des prix confirmés ;
     * l'estimation est remplacée et la demande passe « confirmée ».
     */
    public function confirmer(Proposition $version, Proposition $estimation, Utilisateur $conseiller): void
    {
        self::exigerUnBrouillon($version);
        $comparaison = $this->comparer($version, $estimation);
        if (!$comparaison->complete()) {
            throw new ConfirmationImpossible(\sprintf('Saisissez d\'abord le prix confirmé de : %s.', implode(', ', $comparaison->manquantes)));
        }

        $demande = $version->getDemande() ?? throw new \LogicException('Proposition sans demande.');
        if (!$this->workflowDemande->can($demande, 'confirmer')) {
            throw new ConfirmationImpossible(\sprintf('La demande est « %s » : seule une demande dont la proposition a été envoyée peut être confirmée.', $demande->getStatut()->label()));
        }

        $total = (int) $comparaison->total->confirmeCentimes;
        $parPersonne = (int) $comparaison->parPersonne->confirmeCentimes;
        $maintenant = $this->horloge->now();
        $version
            ->setTotalCentimes($total)
            ->setTotalParPersonneCentimes($parPersonne)
            ->setBudgetsParNiveau([$version->getNiveauBudget()->value => ['totalCentimes' => $total, 'parPersonneCentimes' => $parPersonne]])
            ->setHypotheses(self::hypothesesConfirmees($version, $estimation))
            ->setConfirmeePar($conseiller)
            ->setConfirmeeLe($maintenant);
        $this->workflowProposition->apply($version, 'confirmer');
        if ($this->workflowProposition->can($estimation, 'remplacer')) {
            $this->workflowProposition->apply($estimation, 'remplacer');
        }
        $this->workflowDemande->apply($demande, 'confirmer');
        $demande->setModifieLe($maintenant);
        $this->em->flush();
    }

    /** @return array{int, int} bornes des frais de dossier fixées par l'administrateur, en centimes */
    public function bornesDesFrais(): array
    {
        $regle = $this->regle();

        return [$regle->getFraisDossierMinCentimes(), $regle->getFraisDossierMaxCentimes()];
    }

    public function margePourcentage(): int
    {
        return $this->regle()->getMargePourcentage();
    }

    /**
     * Les lignes dans l'ordre de lecture : logements, transport, activités, guides, puis par date.
     *
     * @return list<LigneDePrestation>
     */
    public static function lignesTriees(Proposition $proposition): array
    {
        $ordre = array_flip(array_map(static fn (TypeLigne $t): string => $t->value, TypeLigne::cases()));
        $lignes = $proposition->getLignes()->toArray();
        usort($lignes, static fn (LigneDePrestation $a, LigneDePrestation $b): int => [$ordre[$a->getType()?->value] ?? 9, $a->getDateDebut(), $a->getId()]
            <=> [$ordre[$b->getType()?->value] ?? 9, $b->getDateDebut(), $b->getId()]);

        return $lignes;
    }

    /**
     * Les hypothèses de l'estimation, mises à jour pour la version confirmée : sans les prestations retirées ou
     * remplacées, transport et frais de dossier aux montants confirmés.
     *
     * @return list<string>
     */
    private static function hypothesesConfirmees(Proposition $version, Proposition $estimation): array
    {
        $libelles = static fn (Proposition $p): array => array_map(static fn (LigneDePrestation $l): string => (string) $l->getLibelle(), $p->getLignes()->toArray());
        $parties = array_diff($libelles($estimation), $libelles($version));

        $hypotheses = [];
        foreach ($version->getHypotheses() as $hypothese) {
            foreach ($parties as $libelle) {
                if (str_starts_with($hypothese, $libelle.' :')) {
                    continue 2;
                }
            }
            if (str_starts_with($hypothese, 'Transport inclus')) {
                $hypothese = \sprintf('Transport inclus : vols et transferts au prix confirmé, pour %s', Montant::quantite(self::voyageurs($version), 'voyageur'));
            } elseif (str_starts_with($hypothese, 'Frais de dossier')) {
                $hypothese = \sprintf('Frais de dossier : %s', Montant::euros($version->getFraisDeDossierCentimes()));
            }
            $hypotheses[] = $hypothese;
        }

        return $hypotheses;
    }

    /** Version de confirmation en cours ou aboutie ; une version remplacée par un nouvel envoi ne compte plus. */
    private static function confirmeLEstimation(Proposition $proposition): bool
    {
        return TypeProposition::Confirmee === $proposition->getType() && StatutProposition::Remplacee !== $proposition->getStatut();
    }

    private static function exigerUnBrouillon(Proposition $version): void
    {
        if (StatutProposition::Brouillon !== $version->getStatut()) {
            throw new ConfirmationImpossible('Cette proposition est déjà confirmée : elle ne se modifie plus.');
        }
    }

    private static function ligne(Proposition $version, int $id): LigneDePrestation
    {
        foreach ($version->getLignes() as $ligne) {
            if ($id === $ligne->getId()) {
                return $ligne;
            }
        }

        throw new ConfirmationImpossible('Cette ligne ne fait pas partie de la proposition.');
    }

    private static function voyageurs(Demande|Proposition $sujet): int
    {
        $demande = $sujet instanceof Demande ? $sujet : $sujet->getDemande();

        return max(1, (int) $demande?->getAdultes() + (int) $demande?->getEnfants());
    }

    /**
     * @param array<Proposition> $propositions
     */
    private static function derniere(array $propositions): ?Proposition
    {
        usort($propositions, static fn (Proposition $a, Proposition $b): int => $b->getVersion() <=> $a->getVersion());

        return $propositions[0] ?? null;
    }

    private function regle(): RegleDePrix
    {
        return $this->reglesDePrix->findOneBy([]) ?? new RegleDePrix();
    }
}
