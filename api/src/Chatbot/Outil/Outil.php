<?php

namespace App\Chatbot\Outil;

use App\Chatbot\ContexteAgent;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Outil que le modèle peut appeler : il choisit l'outil et ses paramètres, le serveur vérifie et exécute.
 * Une entrée refusée lève une OutilException, renvoyée au modèle comme erreur qu'il peut corriger.
 */
#[AutoconfigureTag(self::TAG)]
interface Outil
{
    public const TAG = 'app.chatbot.outil';

    /** Ex. « chercher_destinations ». */
    public function nom(): string;

    /**
     * Définition envoyée à l'API Claude : name, description, input_schema, strict.
     *
     * @return array<string, mixed>
     */
    public function definition(): array;

    /**
     * @param array<mixed> $entree
     *
     * @return array<string, mixed>
     *
     * @throws OutilException
     */
    public function executer(ContexteAgent $contexte, array $entree): array;
}
