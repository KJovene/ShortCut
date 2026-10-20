<?php

namespace App\Chatbot;

/**
 * Un tour de parole d'un agent, rempli par Agent::boucle() au fil du flux : il reste lisible si le client se
 * déconnecte avant la fin (la réponse déjà diffusée est alors quand même enregistrée).
 */
final class Tour
{
    /** Texte diffusé au client pendant le tour. */
    public string $reponse = '';

    /** @var list<array{nom: string, sortie: array<string, mixed>}> sorties des outils exécutés sans erreur */
    public array $sorties = [];

    /**
     * @param list<mixed> $historique messages envoyés à Claude, complétés par ses réponses et les résultats d'outils
     */
    public function __construct(public array $historique)
    {
    }

    /**
     * La sortie du dernier appel réussi de cet outil, ou null.
     *
     * @return array<string, mixed>|null
     */
    public function sortie(string $outil): ?array
    {
        $sorties = array_filter($this->sorties, static fn (array $s): bool => $s['nom'] === $outil);

        return [] === $sorties ? null : end($sorties)['sortie'];
    }
}
