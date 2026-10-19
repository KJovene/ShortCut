<?php

namespace App\Chiffrage;

use App\Entity\Destination;
use App\Entity\Prestataire;
use App\Entity\Prestation;
use App\Enum\TypeDeVoyage;

/**
 * Tout ce qu'il faut pour chiffrer, sans accès à la base : le service de chiffrage reste sans état et testable.
 * Les voyageurs, le budget et le type viennent de la demande du client ; les étapes et activités, des candidats
 * choisis par l'agent ; la marge et les frais, de la règle de prix ; les fourchettes, des transporteurs.
 */
final readonly class DemandeDeChiffrage
{
    /**
     * @param list<int>         $agesEnfants
     * @param list<Etape>       $etapes
     * @param list<Prestation>  $activitesDemandees incluses à tous les niveaux
     * @param list<Prestation>  $activitesSuggerees ajoutées selon le niveau et le budget
     * @param list<Prestataire> $transporteurs      leurs fourchettes donnent le prix des vols et transferts
     */
    public function __construct(
        public TypeDeVoyage $typeDeVoyage,
        public Destination $destination,
        public int $adultes,
        public array $agesEnfants,
        public ?string $villeDepart,
        public bool $transportInclus,
        public ?int $budgetCentimes,
        public bool $budgetParPersonne,
        public array $etapes,
        public array $activitesDemandees,
        public array $activitesSuggerees,
        public array $transporteurs,
        public int $margePourcentage,
        public int $fraisDeDossierCentimes,
    ) {
        if ($adultes < 1) {
            throw new \InvalidArgumentException('Il faut au moins un adulte.');
        }
        if ([] === $etapes) {
            throw new \InvalidArgumentException('Il faut au moins une étape.');
        }
        if ($transportInclus && (null === $villeDepart || '' === trim($villeDepart))) {
            throw new \InvalidArgumentException('La ville de départ est nécessaire pour chiffrer le transport.');
        }
    }

    public function voyageurs(): int
    {
        return $this->adultes + \count($this->agesEnfants);
    }

    /** Budget total du client, en centimes, quel que soit son mode de saisie ; null s'il n'en a pas donné. */
    public function budgetTotalCentimes(): ?int
    {
        if (null === $this->budgetCentimes) {
            return null;
        }

        return $this->budgetParPersonne ? $this->budgetCentimes * $this->voyageurs() : $this->budgetCentimes;
    }

    public function premierJour(): \DateTimeImmutable
    {
        return $this->etapes[0]->dateDebut;
    }

    public function nombreDeNuits(): int
    {
        return array_sum(array_map(static fn (Etape $e): int => $e->nombreDeNuits, $this->etapes));
    }

    /**
     * La même demande avec d'autres étapes (autres dates, moins de nuits) : pour chiffrer un arbitrage.
     *
     * @param list<Etape> $etapes
     */
    public function avecEtapes(array $etapes): self
    {
        return new self(
            $this->typeDeVoyage, $this->destination, $this->adultes, $this->agesEnfants, $this->villeDepart,
            $this->transportInclus, $this->budgetCentimes, $this->budgetParPersonne, $etapes,
            $this->activitesDemandees, $this->activitesSuggerees, $this->transporteurs,
            $this->margePourcentage, $this->fraisDeDossierCentimes,
        );
    }
}
