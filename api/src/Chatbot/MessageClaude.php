<?php

namespace App\Chatbot;

/** Réponse complète de Claude, reconstituée à partir des événements du flux. */
final class MessageClaude
{
    /** @var array<int, \stdClass> blocs de contenu, par index */
    public array $blocs = [];

    /** @var array<int, string> JSON reçu par morceaux pour l'entrée de chaque appel d'outil */
    public array $entreesBrutes = [];

    /** @var array<string, string> JSON illisible, par identifiant d'appel d'outil */
    public array $entreesInvalides = [];

    public ?string $stopReason = null;
    public string $modele = '';
    public bool $termine = false;
    public int $tokensEntree = 0;
    public int $tokensSortie = 0;

    public function terminerBloc(int $index): void
    {
        $bloc = $this->blocs[$index] ?? null;
        if (!$bloc instanceof \stdClass || 'tool_use' !== ($bloc->type ?? null)) {
            return;
        }

        $brut = $this->entreesBrutes[$index] ?? '';
        $entree = json_decode('' === $brut ? '{}' : $brut, false);
        if ($entree instanceof \stdClass) {
            $bloc->input = $entree;

            return;
        }

        // Ne devrait pas arriver sans « eager_input_streaming » ; l'outil ne sera pas exécuté.
        $bloc->input = new \stdClass();
        $this->entreesInvalides[(string) $bloc->id] = $brut;
    }

    /**
     * @return list<\stdClass>
     */
    public function contenu(): array
    {
        ksort($this->blocs);

        return array_values($this->blocs);
    }

    /**
     * @return list<\stdClass>
     */
    public function appelsOutils(): array
    {
        return array_values(array_filter($this->contenu(), static fn (\stdClass $b) => 'tool_use' === ($b->type ?? null)));
    }

    public function texte(): string
    {
        return implode('', array_map(
            static fn (\stdClass $b) => 'text' === ($b->type ?? null) ? (string) $b->text : '',
            $this->contenu(),
        ));
    }

    /** message_start donne les tokens d'entrée, message_delta le cumul des tokens de sortie. */
    public function ajouterUsage(mixed $usage): void
    {
        if (!$usage instanceof \stdClass) {
            return;
        }

        $entree = (int) ($usage->input_tokens ?? 0) + (int) ($usage->cache_creation_input_tokens ?? 0) + (int) ($usage->cache_read_input_tokens ?? 0);
        if ($entree > 0) {
            $this->tokensEntree = $entree;
        }
        if (isset($usage->output_tokens)) {
            $this->tokensSortie = (int) $usage->output_tokens;
        }
    }
}
