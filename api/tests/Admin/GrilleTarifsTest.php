<?php

namespace App\Tests\Admin;

use App\Admin\GrilleTarifs;
use App\Entity\TarifParPeriode;
use App\Enum\UniteTarif;
use PHPUnit\Framework\TestCase;

final class GrilleTarifsTest extends TestCase
{
    private const AUJOURDHUI = '2027-03-15';

    public function testLesTarifsSontDansLOrdreAvecLeurEtatEtLesTrousAVenir(): void
    {
        $lignes = GrilleTarifs::lignes([
            self::tarif('2027-07-01', '2027-08-31', 18900),
            self::tarif('2027-01-01', '2027-03-31', 11200),
            self::tarif('2026-10-01', '2026-12-31', 9900),
            self::tarif('2027-04-01', '2027-05-31', 14000),
        ], new \DateTimeImmutable(self::AUJOURDHUI.' 10:30'));

        self::assertSame(
            ['passe', 'en_cours', 'a_venir', 'trou', 'a_venir', 'trou'],
            array_map(static fn (array $l): string => $l['trou'] ? 'trou' : $l['etat'], $lignes),
        );
        // Juin manque entre deux saisons ; après août, plus rien avant l'horizon de douze mois.
        self::assertSame(['2027-06-01', '2027-06-30'], [$lignes[3]['debut']->format('Y-m-d'), $lignes[3]['fin']?->format('Y-m-d')]);
        self::assertFalse($lignes[3]['avant']);
        self::assertSame('2027-09-01', $lignes[5]['debut']->format('Y-m-d'));
        self::assertNull($lignes[5]['fin']);
    }

    public function testDesTarifsQuiNOntPasCommenceNeSontPasUnTrou(): void
    {
        $tarifs = [self::tarif('2027-06-01', '2028-05-31', 12000)];

        $lignes = GrilleTarifs::lignes($tarifs, new \DateTimeImmutable(self::AUJOURDHUI));
        self::assertTrue($lignes[0]['trou']);
        self::assertTrue($lignes[0]['avant']);
        self::assertCount(2, $lignes, 'Couvert au-delà de l\'horizon : pas de trou final.');

        $resume = GrilleTarifs::resume($tarifs, new \DateTimeImmutable(self::AUJOURDHUI));
        self::assertNotNull($resume);
        self::assertSame('2027-06-01', $resume['depuis']?->format('Y-m-d'));
        self::assertNull($resume['premierTrou']);
    }

    public function testLeResumeNeGardeQueLesPrixEnCoursEtAVenir(): void
    {
        $resume = GrilleTarifs::resume([
            self::tarif('2026-10-01', '2026-12-31', 9900),
            self::tarif('2027-01-01', '2027-03-31', 11200),
            self::tarif('2027-07-01', '2027-08-31', 18900),
        ], new \DateTimeImmutable(self::AUJOURDHUI));

        self::assertNotNull($resume);
        self::assertSame([11200, 18900], [$resume['min'], $resume['max']]);
        self::assertSame(UniteTarif::ParNuit, $resume['unite']);
        self::assertNull($resume['depuis']);
        self::assertSame('2027-08-31', $resume['jusquau']->format('Y-m-d'));
        self::assertSame('2027-04-01', $resume['premierTrou']?->format('Y-m-d'));
    }

    public function testSansTarifAVenirIlNyAPasDeResume(): void
    {
        self::assertNull(GrilleTarifs::resume([self::tarif('2026-10-01', '2026-12-31', 9900)], new \DateTimeImmutable(self::AUJOURDHUI)));
        self::assertSame([], GrilleTarifs::lignes([], new \DateTimeImmutable(self::AUJOURDHUI)));
    }

    private static function tarif(string $debut, string $fin, int $prixCentimes): TarifParPeriode
    {
        return (new TarifParPeriode())
            ->setDebut(new \DateTimeImmutable($debut))
            ->setFin(new \DateTimeImmutable($fin))
            ->setPrixCentimes($prixCentimes)
            ->setUnite(UniteTarif::ParNuit);
    }
}
