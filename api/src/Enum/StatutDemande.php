<?php

namespace App\Enum;

/** Étape de la demande dans le pipeline des conseillers. */
enum StatutDemande: string
{
    case Nouvelle = 'nouvelle';
    case PropositionEnvoyee = 'proposition_envoyee';
    case AConfirmer = 'a_confirmer';
    case Confirmee = 'confirmee';
    case Acceptee = 'acceptee';
    case Perdue = 'perdue';

    public function label(): string
    {
        return match ($this) {
            self::Nouvelle => 'Nouvelle',
            self::PropositionEnvoyee => 'Proposition envoyée',
            self::AConfirmer => 'À confirmer',
            self::Confirmee => 'Confirmée',
            self::Acceptee => 'Acceptée',
            self::Perdue => 'Perdue',
        };
    }
}
