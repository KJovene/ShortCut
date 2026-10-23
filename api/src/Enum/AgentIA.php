<?php

namespace App\Enum;

/**
 * Les quatre agents IA derrière le chatbot. « Traduction du site » ne sert plus (traduction des contenus du
 * backoffice retirée) : le cas reste pour afficher les anciennes lignes du Journal IA.
 */
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
