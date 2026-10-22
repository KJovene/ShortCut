<?php

namespace App\Tests\Admin;

use App\Admin\GrilleTransport;
use PHPUnit\Framework\TestCase;

final class GrilleTransportTest extends TestCase
{
    public function testUneLigneParArriveeUneColonneParDepartEtLeTransfertAPart(): void
    {
        $grille = GrilleTransport::depuis([
            ['depart' => 'Paris', 'arrivee' => 'Louxor', 'minCentimes' => 25000, 'maxCentimes' => 60000],
            ['depart' => 'Lyon', 'arrivee' => 'Louxor', 'minCentimes' => 28000, 'maxCentimes' => 64000],
            ['depart' => 'Paris', 'arrivee' => 'Djerba', 'minCentimes' => 15000, 'maxCentimes' => 40000],
            ['depart' => 'Aéroport de Louxor', 'arrivee' => 'Louxor', 'minCentimes' => 2000, 'maxCentimes' => 5000],
        ]);

        self::assertSame(['Paris', 'Lyon'], $grille['departs']);
        self::assertSame(['Louxor', 'Djerba'], array_column($grille['lignes'], 'arrivee'));
        self::assertSame(['min' => 28000, 'max' => 64000], $grille['lignes'][0]['vols']['Lyon']);
        self::assertSame(['min' => 2000, 'max' => 5000], $grille['lignes'][0]['transfert']);
        self::assertArrayNotHasKey('Lyon', $grille['lignes'][1]['vols']);
        self::assertNull($grille['lignes'][1]['transfert']);
    }
}
