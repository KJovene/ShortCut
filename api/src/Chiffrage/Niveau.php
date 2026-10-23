<?php

namespace App\Chiffrage;

use App\Enum\NiveauBudget;

/** Un niveau de budget chiffré (essentiel, équilibré ou confort), poste par poste. Montants en centimes. */
final readonly class Niveau
{
    /**
     * @param list<string> $logements  noms des logements retenus, dans l'ordre des étapes
     * @param list<string> $activites
     * @param list<Poste>  $postes     hébergements, puis transports, puis activités
     * @param list<string> $hypotheses affichées au client sous le niveau
     */
    public function __construct(
        public NiveauBudget $niveau,
        public array $logements,
        public array $activites,
        public PositionTransport $transport,
        public array $postes,
        public int $coutPrestationsCentimes,
        public int $margeCentimes,
        public int $fraisDeDossierCentimes,
        public int $totalCentimes,
        public int $parPersonneCentimes,
        public array $hypotheses,
    ) {
    }

    /**
     * @param Poste::HEBERGEMENT|Poste::TRANSPORT|Poste::ACTIVITE $type
     *
     * @return list<Poste>
     */
    public function postes(string $type): array
    {
        return array_values(array_filter($this->postes, static fn (Poste $p): bool => $type === $p->type));
    }
}
