<?php

namespace App\Enum;

/** Rôle d'un utilisateur : client du chatbot ou compte du backoffice. */
enum Role: string
{
    case Client = 'ROLE_CLIENT';
    case Conseiller = 'ROLE_CONSEILLER';
    case Administrateur = 'ROLE_ADMIN';

    public function label(): string
    {
        return match ($this) {
            self::Client => 'Client',
            self::Conseiller => 'Conseiller',
            self::Administrateur => 'Administrateur',
        };
    }
}
