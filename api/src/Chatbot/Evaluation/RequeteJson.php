<?php

namespace App\Chatbot\Evaluation;

use App\Chatbot\ClientClaude;
use App\Chatbot\ErreurClaude;

/**
 * Un appel à Claude dont la réponse suit un schéma JSON (structured outputs), pour le client simulé et le juge.
 */
final class RequeteJson
{
    /**
     * Tokens consommés depuis la création du service, par modèle : [entrée, sortie].
     *
     * @var array<string, array{0: int, 1: int}>
     */
    public array $tokens = [];

    public function __construct(private readonly ClientClaude $claude)
    {
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     *
     * @throws ErreurClaude
     */
    public function demander(string $modele, string $effort, string $systeme, string $message, array $schema): array
    {
        $flux = $this->claude->diffuser([
            'model' => $modele,
            'max_tokens' => 16000,
            'output_config' => ['effort' => $effort, 'format' => ['type' => 'json_schema', 'schema' => $schema]],
            'system' => $systeme,
            'messages' => [['role' => 'user', 'content' => $message]],
        ]);
        foreach ($flux as $_) {
            // Réponse attendue en entier : les morceaux ne sont pas affichés.
        }
        $reponse = $flux->getReturn();

        $this->tokens[$modele] ??= [0, 0];
        $this->tokens[$modele][0] += $reponse->tokensEntree;
        $this->tokens[$modele][1] += $reponse->tokensSortie;

        if ('end_turn' !== $reponse->stopReason) {
            throw new ErreurClaude(\sprintf('%s : réponse interrompue (%s).', $modele, (string) $reponse->stopReason));
        }

        try {
            $donnees = json_decode($reponse->texte(), true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ErreurClaude(\sprintf('%s : JSON illisible (%s).', $modele, $e->getMessage()), null, $e);
        }

        return \is_array($donnees) ? $donnees : [];
    }
}
