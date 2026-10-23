<?php

namespace App\Tests\Texte;

use App\Texte\Correspondance;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CorrespondanceTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function paires(): iterable
    {
        yield 'casse' => ['LISBONNE', 'Lisbonne', true];
        yield 'accents' => ['Reykjavík', 'Reykjavik', true];
        yield 'espaces et ponctuation' => ['  la-fortuna ', 'La Fortuna', true];
        yield 'faute de frappe' => ['Lisbone', 'Lisbonne', true];
        yield 'lettre doublée' => ['Teneriffe', 'Tenerife', true];
        yield 'mot entier contenu' => ['Lisbonne (Portugal)', 'Lisbonne', true];
        yield 'aéroport' => ['Aéroport de Marrakech', 'Marrakech', true];
        yield 'envie au pluriel' => ['plages', 'plage', true];
        yield 'envie sans accent' => ['Detente', 'détente', true];
        yield 'nom court, une lettre' => ['Rome', 'Roma', false];
        yield 'nom court différent' => ['Lyon', 'Lion', false];
        yield 'villes différentes' => ['Lisbonne', 'Marrakech', false];
        yield 'morceau de mot' => ['Santorin', 'San', false];
        yield 'envies différentes' => ['nature', 'culture', false];
        yield 'vide' => ['', 'Paris', false];
    }

    #[DataProvider('paires')]
    public function testCorrespondent(string $a, string $b, bool $attendu): void
    {
        self::assertSame($attendu, Correspondance::correspondent($a, $b));
        self::assertSame($attendu, Correspondance::correspondent($b, $a));
    }

    public function testFiltrerGardeLesCandidatsProches(): void
    {
        self::assertSame(['détente', 'Détente'], Correspondance::filtrer('detente', ['détente', 'sport', 'Détente']));
    }
}
