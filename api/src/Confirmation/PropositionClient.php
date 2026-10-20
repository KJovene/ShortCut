<?php

namespace App\Confirmation;

use App\Entity\Demande;
use App\Entity\LigneDePrestation;
use App\Entity\Proposition;
use App\Enum\StatutDemande;
use App\Enum\StatutProposition;
use App\Enum\TypeLigne;
use App\Enum\TypeProposition;
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
 * Chaque version envoyée reste consultable (`versions`, #211), même remplacée ; un brouillon jamais.
 */
final class PropositionClient
{
    public const MENTION_CONFIRMEE = 'Proposition confirmée par votre conseiller : prix vérifiés auprès des prestataires.';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $horloge,
        #[Target('proposition')] private readonly WorkflowInterface $workflowProposition,
        #[Target('demande')] private readonly WorkflowInterface $workflowDemande,
    ) {
    }

    /** La version qui fait foi pour ce lien : la sienne, ou celle qui l'a remplacée. Jamais un brouillon. */
    public function versionQuiFaitFoi(Proposition $proposition): ?Proposition
    {
        if (StatutProposition::Brouillon === $proposition->getStatut()) {
            return null;
        }
        if (StatutProposition::Remplacee !== $proposition->getStatut()) {
            return $proposition;
        }

        $visibles = array_filter(
            $proposition->getDemande()?->getPropositions()->toArray() ?? [],
            static fn (Proposition $p): bool => !\in_array($p->getStatut(), [StatutProposition::Brouillon, StatutProposition::Remplacee], true),
        );
        usort($visibles, static fn (Proposition $a, Proposition $b): int => $b->getVersion() <=> $a->getVersion());

        return $visibles[0] ?? $proposition;
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
            $raison = StatutDemande::AConfirmer === $demande->getStatut()
                ? 'Votre demande de confirmation est déjà transmise à votre conseiller.'
                : 'Cette proposition ne peut plus être soumise à confirmation.';

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
            $raison = match ($proposition->getStatut()) {
                StatutProposition::Acceptee => 'Vous avez déjà accepté cette proposition.',
                StatutProposition::Envoyee => 'Seule une proposition confirmée par votre conseiller peut être acceptée.',
                default => 'Cette proposition ne peut plus être acceptée.',
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

        return [
            'jeton' => $proposition->getJetonPublic(),
            // Le lien ouvert était celui d'une version remplacée : le site peut basculer sur ce jeton.
            'versionDemandee' => null !== $demandee && $demandee !== $proposition ? $demandee->getVersion() : null,
            'version' => $proposition->getVersion(),
            'etat' => $confirmee ? 'confirmee' : 'estimee',
            'statut' => $proposition->getStatut()->value,
            'mention' => $confirmee ? self::MENTION_CONFIRMEE : Proposition::MENTION,
            'creeLe' => $proposition->getCreeLe()->format(\DATE_ATOM),
            'confirmeeLe' => $proposition->getConfirmeeLe()?->format(\DATE_ATOM),
            'niveauBudget' => $proposition->getNiveauBudget()->value,
            'budgetsParNiveau' => (object) $proposition->getBudgetsParNiveau(),
            'totalCentimes' => $proposition->getTotalCentimes(),
            'totalParPersonneCentimes' => $proposition->getTotalParPersonneCentimes(),
            'fraisDeDossierCentimes' => $proposition->getFraisDeDossierCentimes(),
            'destination' => null === $proposition->getDestination() ? null : [
                'slug' => $proposition->getDestination()->getSlug(),
                'ville' => $proposition->getDestination()->getVille(),
            ],
            'pourquoi' => $proposition->getPourquoi(),
            'argumentaire' => $proposition->getArgumentaire(),
            'hypotheses' => $proposition->getHypotheses(),
            'programme' => $proposition->getProgramme(),
            // Logements et activités retenus, avec leurs points forts et les niveaux qui les retiennent (#207).
            'prestations' => $proposition->getPrestations(),
            // Les postes du niveau détaillé, sans leur prix d'achat.
            'lignes' => array_map(self::ligne(...), ConfirmationService::lignesTriees($proposition)),
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
                'etat' => TypeProposition::Confirmee === $v->getType() ? 'confirmee' : 'estimee',
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
    private static function ligne(LigneDePrestation $ligne): array
    {
        $source = $ligne->getPrestation();

        return [
            'type' => $ligne->getType()?->value,
            'libelle' => $ligne->getLibelle(),
            'dateDebut' => $ligne->getDateDebut()?->format('Y-m-d'),
            'dateFin' => $ligne->getDateFin()?->format('Y-m-d'),
            'quantite' => $ligne->getQuantite(),
            'fiche' => null === $source ? null : \sprintf('/api/%s/%d', TypeLigne::Logement === $ligne->getType() ? 'logements' : 'activites', $source->getId()),
        ];
    }

    /**
     * Les versions de la demande que le client a reçues (envoyées ou confirmées), de la plus ancienne à la plus
     * récente. Un brouillon remplacé avant d'être envoyé n'a jamais été vu : il n'y est pas.
     *
     * @return list<Proposition>
     */
    private static function envoyees(Proposition $proposition): array
    {
        $versions = array_values(array_filter(
            self::demande($proposition)->getPropositions()->toArray(),
            static fn (Proposition $p): bool => null !== $p->getEnvoyeeLe() || null !== $p->getConfirmeeLe(),
        ));
        usort($versions, static fn (Proposition $a, Proposition $b): int => $a->getVersion() <=> $b->getVersion());

        return $versions;
    }

    private static function demande(Proposition $proposition): Demande
    {
        return $proposition->getDemande() ?? throw new \LogicException('Proposition sans demande.');
    }
}
