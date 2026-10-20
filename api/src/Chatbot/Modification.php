<?php

namespace App\Chatbot;

use App\Entity\Proposition;

/**
 * Une modification du client après une proposition (#211) : la version de référence, ce qui a changé et la
 * combinaison de cette version. Les agents Recherche et Rédaction s'en servent pour ne refaire que ce qui est touché.
 */
final readonly class Modification
{
    /**
     * @param array<string, mixed> $combinaison combinaison de la référence, avec le nom de chaque prestation
     * @param string|null          $refus       pourquoi la même combinaison ne convient plus, si elle a été essayée
     */
    public function __construct(
        public Proposition $reference,
        public Changement $changement,
        public array $combinaison,
        public ?string $refus = null,
    ) {
    }

    public function avecRefus(string $refus): self
    {
        return new self($this->reference, $this->changement, $this->combinaison, $refus);
    }

    /** Ce que l'agent Recherche doit savoir pour partir de la version précédente. */
    public function pourLaRecherche(): string
    {
        $sections = [
            \sprintf('Le client a modifié sa demande après sa proposition (version %d) et l\'a confirmée.', $this->reference->getVersion()),
            'Ce qui a changé (avant → après) : '.self::json($this->changement->champs),
            \sprintf('Combinaison de la version %d : %s', $this->reference->getVersion(), self::json($this->combinaison)),
        ];
        if (null !== $this->refus) {
            $sections[] = 'Recalculée telle quelle avec la demande modifiée, cette combinaison a été refusée : '.$this->refus;
        }

        return implode("\n\n", $sections);
    }

    /** Ce que l'agent Rédaction doit savoir pour écrire la version suivante. */
    public function pourLaRedaction(): string
    {
        return implode("\n\n", [
            \sprintf('C\'est la version %d de sa proposition : le client a modifié sa demande après la version %d.', $this->reference->getVersion() + 1, $this->reference->getVersion()),
            'Ce qui a changé : '.implode(' ; ', $this->changement->resume()),
            \sprintf('Programme de la version %d, à reprendre en l\'adaptant au nouveau calendrier et aux prestations retenues : %s', $this->reference->getVersion(), self::json($this->reference->getProgramme())),
        ]);
    }

    /**
     * @param array<mixed> $donnees
     */
    private static function json(array $donnees): string
    {
        return json_encode($donnees, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }
}
