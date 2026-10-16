<?php

namespace App\Enum;

/** Origine de la proposition : chatbot ou conseiller. */
enum TypeProposition: string
{
    case Premiere = 'premiere';
    case Confirmee = 'confirmee';

    public function label(): string
    {
        return match ($this) {
            self::Premiere => 'Première proposition (prix estimés)',
            self::Confirmee => 'Proposition confirmée',
        };
    }
}
