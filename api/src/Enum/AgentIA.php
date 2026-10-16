<?php

namespace App\Enum;

/** Les quatre agents IA derrière le chatbot. */
enum AgentIA: string
{
    case Conversation = 'conversation';
    case Recherche = 'recherche';
    case Chiffrage = 'chiffrage';
    case Modification = 'modification';

    public function label(): string
    {
        return match ($this) {
            self::Conversation => 'Conversation',
            self::Recherche => 'Recherche',
            self::Chiffrage => 'Chiffrage et rédaction',
            self::Modification => 'Modification',
        };
    }
}
