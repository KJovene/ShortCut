<?php

namespace App\Chiffrage;

/** Une ligne du détail d'un niveau : « Hôtel Alfama Vista, 3 nuits × 110 €, 330 € ». */
final readonly class Poste
{
    public const HEBERGEMENT = 'hebergement';
    public const TRANSPORT = 'transport';
    public const ACTIVITE = 'activite';

    /**
     * @param self::HEBERGEMENT|self::TRANSPORT|self::ACTIVITE $type
     * @param bool                                             $estime vrai pour le transport (fourchette, pas tarif précis)
     */
    public function __construct(
        public string $type,
        public string $libelle,
        public string $calcul,
        public int $centimes,
        public bool $estime = false,
    ) {
    }
}
