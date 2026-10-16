<?php

namespace App\Enum;

/** État d'une version de proposition. */
enum StatutProposition: string
{
    case Brouillon = 'brouillon';
    case Envoyee = 'envoyee';
    case Confirmee = 'confirmee';
    case Acceptee = 'acceptee';
    case Refusee = 'refusee';
    case Remplacee = 'remplacee';

    public function label(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::Envoyee => 'Envoyée au client',
            self::Confirmee => 'Confirmée par le conseiller',
            self::Acceptee => 'Acceptée par le client',
            self::Refusee => 'Refusée',
            self::Remplacee => 'Remplacée par une nouvelle version',
        };
    }
}
