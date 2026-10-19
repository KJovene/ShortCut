<?php

namespace App\Chiffrage;

use App\Enum\NiveauBudget;

/**
 * Résultat du chiffrage : les trois niveaux, ou la raison pour laquelle la demande n'est pas chiffrable.
 * Les exclusions listent les candidats écartés (sans tarif aux dates, séjour minimum, âge minimum…).
 */
final readonly class ResultatChiffrage
{
    /**
     * @param array<value-of<NiveauBudget>, Niveau>|null      $niveaux
     * @param list<array{prestation: string, raison: string}> $exclusions
     */
    public function __construct(
        public ?int $budgetCentimes,
        public bool $budgetParPersonne,
        public bool $budgetInsuffisant,
        public ?array $niveaux,
        public ?NonChiffrable $nonChiffrable,
        public array $exclusions,
    ) {
    }

    public function niveau(NiveauBudget $niveau): ?Niveau
    {
        return $this->niveaux[$niveau->value] ?? null;
    }
}
