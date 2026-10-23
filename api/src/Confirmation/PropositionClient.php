<?php

namespace App\Confirmation;

use App\Entity\Demande;
use App\Entity\Destination;
use App\Entity\LigneDePrestation;
use App\Entity\Prestation;
use App\Entity\Proposition;
use App\Enum\Langue;
use App\Enum\StatutDemande;
use App\Enum\StatutProposition;
use App\Enum\TypeLigne;
use App\Enum\TypeProposition;
use App\Texte\Anglais;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * La proposition vue par le client, par son lien unique (#210) :
 * - le lien d'une version remplacée mène à la version qui fait foi (la confirmée, ou la plus récente) ;
 * - « estimée » tant que le conseiller n'a pas confirmé, « confirmée » ensuite ;
 * - le client demande la confirmation d'une estimation (demande « à confirmer »), puis accepte la version confirmée
 *   (proposition et demande « acceptées »).
 * Les prix d'achat des lignes ne sont jamais montrés : seuls les totaux, marge et frais compris.
 * Chaque version envoyée reste consultable (`versions`, #211), même remplacée. Avant la première version envoyée, le
 * client consulte aussi son récapitulatif « préparé » avec l'assistant (apercu()), dans le même format ; ensuite, un
 * brouillon n'est plus montré (le client garde la version envoyée jusqu'à la suivante).
 */
final class PropositionClient
{
    public const MENTION_CONFIRMEE = 'Proposition confirmée par votre conseiller : prix vérifiés auprès des prestataires.';
    public const MENTION_CONFIRMEE_EN = 'Proposal confirmed by your advisor: prices checked with the providers.';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $horloge,
        #[Target('proposition')] private readonly WorkflowInterface $workflowProposition,
        #[Target('demande')] private readonly WorkflowInterface $workflowDemande,
    ) {
    }

    /**
     * La version qui fait foi pour ce lien : la sienne, ou celle qui l'a remplacée. Un brouillon seulement s'il est le
     * récapitulatif préparé (apercu()).
     */
    public function versionQuiFaitFoi(Proposition $proposition): ?Proposition
    {
        $demande = self::demande($proposition);
        if (StatutProposition::Brouillon === $proposition->getStatut()) {
            return $proposition === self::apercu($demande) ? $proposition : null;
        }
        if (StatutProposition::Remplacee !== $proposition->getStatut()) {
            return $proposition;
        }

        return $demande->derniereProposition(static fn (Proposition $p): bool => $p->estVisibleDuClient())
            ?? self::apercu($demande) ?? $proposition;
    }

    /**
     * Ce que le client voit de sa demande (espace client) : son récapitulatif préparé tant qu'aucune version ne lui a
     * été envoyée, sinon la dernière version qu'il voit encore.
     */
    public static function visible(Demande $demande): ?Proposition
    {
        return self::apercu($demande) ?? $demande->derniereProposition(static fn (Proposition $p): bool => $p->estVisibleDuClient());
    }

    /**
     * Le récapitulatif que le client consulte avant que son conseiller lui ait envoyé une version : le dernier
     * brouillon de première proposition préparé avec l'assistant. Null dès qu'une version a été envoyée ou confirmée.
     */
    public static function apercu(Demande $demande): ?Proposition
    {
        $apercu = null;
        foreach ($demande->getPropositions() as $proposition) {
            $recue = $proposition->estRecueParLeClient()
                || \in_array($proposition->getStatut(), [StatutProposition::Envoyee, StatutProposition::Confirmee, StatutProposition::Acceptee, StatutProposition::Refusee], true);
            if ($recue) {
                return null;
            }
            if (StatutProposition::Brouillon === $proposition->getStatut() && TypeProposition::Premiere === $proposition->getType()
                && (null === $apercu || $proposition->getVersion() > $apercu->getVersion())) {
                $apercu = $proposition;
            }
        }

        return $apercu;
    }

    /**
     * Une version précise de la même demande, telle que le client l'a reçue ; null pour un brouillon ou un numéro
     * inconnu.
     */
    public function version(Proposition $proposition, int $numero): ?Proposition
    {
        foreach (self::envoyees($proposition) as $version) {
            if ($numero === $version->getVersion()) {
                return $version;
            }
        }

        return null;
    }

    /** Le client souhaite faire confirmer la proposition estimée : la demande passe « à confirmer ». */
    public function demanderConfirmation(Proposition $proposition): void
    {
        $demande = self::demande($proposition);
        if (StatutProposition::Envoyee !== $proposition->getStatut() || !$this->workflowDemande->can($demande, 'demander_confirmation')) {
            $langue = $demande->getLangue();
            $raison = StatutDemande::AConfirmer === $demande->getStatut()
                ? $langue->choisir('Votre demande de confirmation est déjà transmise à votre conseiller.', 'Your confirmation request has already been sent to your advisor.')
                : $langue->choisir('Cette proposition ne peut plus être soumise à confirmation.', 'This proposal can no longer be submitted for confirmation.');

            throw new ConfirmationImpossible($raison);
        }

        $this->workflowDemande->apply($demande, 'demander_confirmation');
        $demande->setModifieLe($this->horloge->now());
        $this->em->flush();
    }

    /** Le client accepte la version confirmée : proposition et demande passent « acceptées ». */
    public function accepter(Proposition $proposition): void
    {
        $demande = self::demande($proposition);
        if (!$this->workflowProposition->can($proposition, 'accepter') || !$this->workflowDemande->can($demande, 'accepter')) {
            $langue = $demande->getLangue();
            $raison = match ($proposition->getStatut()) {
                StatutProposition::Acceptee => $langue->choisir('Vous avez déjà accepté cette proposition.', 'You have already accepted this proposal.'),
                StatutProposition::Envoyee => $langue->choisir('Seule une proposition confirmée par votre conseiller peut être acceptée.', 'Only a proposal confirmed by your advisor can be accepted.'),
                default => $langue->choisir('Cette proposition ne peut plus être acceptée.', 'This proposal can no longer be accepted.'),
            };

            throw new ConfirmationImpossible($raison);
        }

        $this->workflowProposition->apply($proposition, 'accepter');
        $this->workflowDemande->apply($demande, 'accepter');
        $demande->setModifieLe($this->horloge->now());
        $this->em->flush();
    }

    /**
     * La proposition pour la page « Ma proposition ».
     *
     * @return array<string, mixed>
     */
    public function presenter(Proposition $proposition, ?Proposition $demandee = null): array
    {
        $demande = self::demande($proposition);
        $confirmee = TypeProposition::Confirmee === $proposition->getType();
        $preparee = StatutProposition::Brouillon === $proposition->getStatut();
        $langue = $demande->getLangue();
        // Hypothèses et libellés sont enregistrés en français par le chiffrage : traduits pour le client (US15).
        $traduire = Langue::Anglais === $langue ? Anglais::traduire(...) : static fn (string $texte): string => $texte;

        return [
            'jeton' => $proposition->getJetonPublic(),
            // Langue de la page et du PDF : celle dans laquelle le client a dialogué avec le chatbot.
            'langue' => $langue->value,
            // Le lien ouvert était celui d'une version remplacée : le site peut basculer sur ce jeton.
            'versionDemandee' => null !== $demandee && $demandee !== $proposition ? $demandee->getVersion() : null,
            'version' => $proposition->getVersion(),
            // « preparee » : le récapitulatif préparé avec l'assistant, que le conseiller n'a pas encore envoyé.
            'etat' => self::etat($proposition),
            'statut' => $proposition->getStatut()->value,
            'mention' => match (true) {
                $confirmee => $langue->choisir(self::MENTION_CONFIRMEE, self::MENTION_CONFIRMEE_EN),
                $preparee => $langue->choisir(Proposition::MENTION_PREPAREE, Proposition::MENTION_PREPAREE_EN),
                default => $langue->choisir(Proposition::MENTION, Proposition::MENTION_EN),
            },
            'creeLe' => $proposition->getCreeLe()->format(\DATE_ATOM),
            'confirmeeLe' => $proposition->getConfirmeeLe()?->format(\DATE_ATOM),
            'niveauBudget' => $proposition->getNiveauBudget()->value,
            'budgetsParNiveau' => (object) $proposition->getBudgetsParNiveau(),
            'totalCentimes' => $proposition->getTotalCentimes(),
            'totalParPersonneCentimes' => $proposition->getTotalParPersonneCentimes(),
            'fraisDeDossierCentimes' => $proposition->getFraisDeDossierCentimes(),
            'destination' => null === $proposition->getDestination() ? null : self::destination($proposition->getDestination()),
            'pourquoi' => $proposition->getPourquoi(),
            'argumentaire' => $proposition->getArgumentaire(),
            'hypotheses' => array_map($traduire, $proposition->getHypotheses()),
            'programme' => $proposition->getProgramme(),
            // Logements et activités retenus, avec leurs points forts et les niveaux qui les retiennent (#207), et leur
            // photo du catalogue.
            'prestations' => $this->avecPhotos($proposition->getPrestations()),
            // Activités que le client peut encore ajouter, à leur prix pour le groupe.
            'options' => array_map(static fn (array $o): array => [
                'nom' => $o['nom'],
                'prixCentimes' => $o['prixCentimes'],
                'parPersonneCentimes' => $o['parPersonneCentimes'],
            ], $proposition->getOptions()),
            // Les postes du niveau détaillé, sans leur prix d'achat.
            'lignes' => array_map(
                static fn (LigneDePrestation $ligne): array => self::ligne($ligne, $traduire),
                ConfirmationService::lignesTriees($proposition),
            ),
            'demande' => [
                'statut' => $demande->getStatut()->value,
                'typeDeVoyage' => $demande->getTypeDeVoyage()?->value,
                'lieuOuZone' => $demande->getLieuOuZone(),
                'dateDebut' => $demande->getDateDebut()?->format('Y-m-d'),
                'dateFin' => $demande->getDateFin()?->format('Y-m-d'),
                'adultes' => $demande->getAdultes(),
                'enfants' => $demande->getEnfants(),
            ],
            // Toutes les versions reçues par le client, de la plus ancienne à la plus récente.
            'versions' => array_map(static fn (Proposition $v): array => [
                'version' => $v->getVersion(),
                'jeton' => $v->getJetonPublic(),
                'etat' => self::etat($v),
                'statut' => $v->getStatut()->value,
                'creeLe' => $v->getCreeLe()->format(\DATE_ATOM),
                'totalCentimes' => $v->getTotalCentimes(),
            ], self::envoyees($proposition)),
            'actions' => [
                'demanderConfirmation' => StatutProposition::Envoyee === $proposition->getStatut() && $this->workflowDemande->can($demande, 'demander_confirmation'),
                'accepter' => $this->workflowProposition->can($proposition, 'accepter') && $this->workflowDemande->can($demande, 'accepter'),
            ],
        ];
    }

    /** @return array<string, mixed> un poste, sans son prix d'achat */
    private static function ligne(LigneDePrestation $ligne, \Closure $traduire): array
    {
        $source = $ligne->getPrestation();

        return [
            'type' => $ligne->getType()?->value,
            'libelle' => $traduire((string) $ligne->getLibelle()),
            'dateDebut' => $ligne->getDateDebut()?->format('Y-m-d'),
            'dateFin' => $ligne->getDateFin()?->format('Y-m-d'),
            'quantite' => $ligne->getQuantite(),
            'fiche' => null === $source ? null : \sprintf('/api/%s/%d', TypeLigne::Logement === $ligne->getType() ? 'logements' : 'activites', $source->getId()),
        ];
    }

    /** « confirmee », « estimee » (envoyée par le conseiller) ou « preparee » (récapitulatif pas encore envoyé). */
    public static function etat(Proposition $proposition): string
    {
        return match (true) {
            TypeProposition::Confirmee === $proposition->getType() => 'confirmee',
            StatutProposition::Brouillon === $proposition->getStatut() => 'preparee',
            default => 'estimee',
        };
    }

    /**
     * Les versions de la demande que le client a reçues (envoyées ou confirmées), de la plus ancienne à la plus
     * récente, ou son récapitulatif préparé avant toute version envoyée. Un brouillon remplacé n'y est pas.
     *
     * @return list<Proposition>
     */
    private static function envoyees(Proposition $proposition): array
    {
        $demande = self::demande($proposition);
        $apercu = self::apercu($demande);
        $versions = array_values(array_filter(
            $demande->getPropositions()->toArray(),
            static fn (Proposition $p): bool => $p->estRecueParLeClient() || $p === $apercu,
        ));
        usort($versions, static fn (Proposition $a, Proposition $b): int => $a->getVersion() <=> $b->getVersion());

        return $versions;
    }

    private static function demande(Proposition $proposition): Demande
    {
        return $proposition->getDemande() ?? throw new \LogicException('Proposition sans demande.');
    }

    /**
     * La destination telle que le site l'affiche : photo du catalogue et température moyenne de chaque mois
     * (la période saisie qui commence ce mois-là, null si aucune).
     *
     * @return array{slug: ?string, ville: ?string, nom: string, photo: ?string, photoAlt: ?string, temperatures: list<float|null>}
     */
    private static function destination(Destination $destination): array
    {
        $temperatures = array_fill(0, 12, null);
        foreach ($destination->getPeriodesClimatiques() as $periode) {
            $mois = (int) $periode->getDebut()?->format('n') - 1;
            if ($mois >= 0 && null === $temperatures[$mois]) {
                $temperatures[$mois] = $periode->getTemperatureMoyenne();
            }
        }

        return [
            'slug' => $destination->getSlug(),
            'ville' => $destination->getVille(),
            'nom' => $destination->getNomAffiche(),
            'photo' => $destination->getUrlPhoto(),
            'photoAlt' => $destination->getPhotoAlt(),
            'temperatures' => $temperatures,
        ];
    }

    /**
     * @param list<array{id: int, type: string, nom: string, pointsForts: list<string>, niveaux: list<string>}> $prestations
     *
     * @return list<array{id: int, type: string, nom: string, pointsForts: list<string>, niveaux: list<string>, photo: ?string}>
     */
    private function avecPhotos(array $prestations): array
    {
        $ids = array_column($prestations, 'id');
        $photos = [];
        foreach ([] === $ids ? [] : $this->em->getRepository(Prestation::class)->findBy(['id' => $ids]) as $prestation) {
            $photo = $prestation->getPhotoPrincipale();
            $photos[(int) $prestation->getId()] = null === $photo ? null : '/uploads/prestations/'.$photo;
        }

        return array_map(static fn (array $p): array => $p + ['photo' => $photos[$p['id']] ?? null], $prestations);
    }
}
