<?php

namespace App\Proposition;

/** Proposition rédigée refusée avant enregistrement : chaque raison est renvoyée au modèle pour qu'il corrige. */
final class PropositionRefusee extends \DomainException
{
    /**
     * @param list<string> $raisons
     */
    public function __construct(public readonly array $raisons)
    {
        parent::__construct("Proposition refusée :\n- ".implode("\n- ", $raisons));
    }
}
