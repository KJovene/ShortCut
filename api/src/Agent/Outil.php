<?php

namespace App\Agent;

/**
 * Un outil que les agents IA peuvent appeler (docs/outils.md). Le modèle choisit l'outil et ses paramètres ;
 * le serveur vérifie l'entrée, exécute et renvoie le résultat.
 * Une entrée refusée lève EntreeInvalide : la boucle d'agent (#192) la renvoie en `tool_result` avec `is_error`.
 */
interface Outil
{
    /** Ex. « calculer_budget ». */
    public function nom(): string;

    public function description(): string;

    /**
     * Schéma JSON de l'entrée, tel qu'envoyé à l'API Claude.
     *
     * @return array<string, mixed>
     */
    public function schemaEntree(): array;

    /** Mode strict de l'API Claude : réservé aux outils qui écrivent (voir docs/outils.md, « Schémas »). */
    public function strict(): bool;

    /**
     * @param array<string, mixed> $entree
     *
     * @return array<string, mixed>
     *
     * @throws EntreeInvalide
     */
    public function executer(ContexteAgent $contexte, array $entree): array;
}
