<?php

namespace App\Chatbot;

use App\Entity\Conversation;
use App\Entity\Demande;
use App\Enum\AgentIA;

/**
 * Ce sur quoi un outil a le droit d'agir, construit par le serveur à partir du jeton de conversation :
 * jamais à partir de l'entrée du modèle (voir docs/outils.md).
 */
final class ContexteAgent
{
    public function __construct(
        public readonly Demande $demande,
        public readonly Conversation $conversation,
        public readonly AgentIA $agent,
    ) {
    }
}
