<?php

namespace App\Enum;

/** Les quatre agents IA derrière le chatbot, et la traduction du site en anglais. */
enum AgentIA: string
{
    case Conversation = 'conversation';
    case Recherche = 'recherche';
    case Chiffrage = 'chiffrage';
    case Modification = 'modification';
    case Traduction = 'traduction';

    public function label(): string
    {
        return match ($this) {
            self::Conversation => 'Conversation',
            self::Recherche => 'Recherche',
            self::Chiffrage => 'Chiffrage et rédaction',
            self::Modification => 'Modification',
            self::Traduction => 'Traduction du site',
        };
    }
}
