<?php

namespace App\Chatbot;

/** Échec d'un appel à l'API Claude : le détail est journalisé, le client ne voit que messagePourLeClient(). */
final class ErreurClaude extends \RuntimeException
{
    public function __construct(string $message, private readonly ?int $statut = null, ?\Throwable $precedente = null)
    {
        parent::__construct($message, 0, $precedente);
    }

    public function getStatut(): ?int
    {
        return $this->statut;
    }

    public function messagePourLeClient(): string
    {
        // 429 : limite de l'organisation ; 529 : API surchargée.
        return \in_array($this->statut, [429, 529], true)
            ? 'L\'assistant est très sollicité en ce moment. Réessayez dans quelques instants.'
            : 'L\'assistant est momentanément indisponible. Réessayez dans quelques instants ou demandez à parler à un conseiller.';
    }
}
