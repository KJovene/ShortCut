<?php

namespace App\Enum;

/**
 * Langue dans laquelle le client dialogue avec le chatbot (US15) : le chatbot lui répond, et sa proposition est
 * rédigée, dans cette langue. La fiche demande reste en français : les outils et le backoffice la lisent ainsi.
 */
enum Langue: string
{
    case Francais = 'fr';
    case Anglais = 'en';

    /** Le texte du client dans sa langue : `$langue->choisir('Bonjour', 'Hello')`. */
    public function choisir(string $francais, string $anglais): string
    {
        return self::Anglais === $this ? $anglais : $francais;
    }

    public function label(): string
    {
        return match ($this) {
            self::Francais => 'Français',
            self::Anglais => 'Anglais',
        };
    }
}
