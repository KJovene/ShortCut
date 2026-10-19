<?php

namespace App\Agent;

use App\Entity\Conversation;
use App\Entity\Demande;
use App\Enum\AgentIA;

/**
 * Contexte d'un appel d'outil, construit par le serveur à partir du jeton de conversation du visiteur
 * (docs/outils.md) : un outil n'agit que sur cette demande, jamais sur un identifiant fourni par le modèle.
 */
final readonly class ContexteAgent
{
    public function __construct(
        public Demande $demande,
        public Conversation $conversation,
        public AgentIA $agent,
    ) {
    }
}
