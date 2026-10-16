<?php

namespace App\Enum;

/** Rôle d'un utilisateur du backoffice. */
enum Role: string
{
    case Conseiller = 'ROLE_CONSEILLER';
    case Administrateur = 'ROLE_ADMIN';

    public function label(): string
    {
        return match ($this) {
            self::Conseiller => 'Conseiller',
            self::Administrateur => 'Administrateur',
        };
    }
}
