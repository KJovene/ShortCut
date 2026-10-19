<?php

namespace App\Chiffrage;

/**
 * Un arbitrage chiffré proposé quand le budget ne suffit pas : les mêmes prestations, autrement (moins de nuits,
 * une période moins chère), chiffrées au niveau essentiel avec la même formule. Les prix ne sont jamais baissés.
 */
final readonly class Arbitrage
{
    public const MOINS_DE_NUITS = 'moins_de_nuits';
    public const AUTRE_PERIODE = 'autre_periode';
    public const LES_DEUX = 'moins_de_nuits_et_autre_periode';

    /**
     * @param self::MOINS_DE_NUITS|self::AUTRE_PERIODE|self::LES_DEUX $type
     * @param list<Etape>                                             $etapes les étapes à redonner à calculer_budget si le client accepte
     */
    public function __construct(
        public string $type,
        public string $libelle,
        public array $etapes,
        public Niveau $essentiel,
        public int $budgetTotalCentimes,
    ) {
    }

    public function tientDansLeBudget(): bool
    {
        return $this->essentiel->totalCentimes <= $this->budgetTotalCentimes;
    }

    /** Ce qu'il manque encore (positif) ou ce qu'il reste (négatif), en centimes, sur le total. */
    public function ecartCentimes(): int
    {
        return $this->essentiel->totalCentimes - $this->budgetTotalCentimes;
    }
}
