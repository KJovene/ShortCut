<?php

namespace App\Tests\Entity;

use App\Entity\PeriodeClimatique;
use PHPUnit\Framework\TestCase;

/** Une période climatique saisie pour une année vaut pour les mêmes dates des autres années. */
final class PeriodeClimatiqueTest extends TestCase
{
    public function testLaPeriodeEstReporteeSurLAnneeDuVoyage(): void
    {
        $mai = self::periode('2027-05-01', '2027-05-31');

        self::assertSame([['2026-05-01', '2026-05-31']], self::dates($mai->auxDates(new \DateTimeImmutable('2026-05-10'), new \DateTimeImmutable('2026-05-17'))));
        self::assertSame([['2028-05-01', '2028-05-31']], self::dates($mai->auxDates(new \DateTimeImmutable('2028-04-28'), new \DateTimeImmutable('2028-05-03'))));
        self::assertSame([], $mai->auxDates(new \DateTimeImmutable('2026-06-01'), new \DateTimeImmutable('2026-06-08')));
    }

    public function testUnePeriodeAChevalSurDeuxAnneesToucheJanvier(): void
    {
        $hiver = self::periode('2027-11-01', '2028-02-29');

        self::assertSame([['2025-11-01', '2026-02-28']], self::dates($hiver->auxDates(new \DateTimeImmutable('2026-01-10'), new \DateTimeImmutable('2026-01-17'))));
    }

    public function testLeDernierJourDeFevrierLeReste(): void
    {
        $fevrier = self::periode('2027-02-01', '2027-02-28');

        self::assertSame([['2028-02-01', '2028-02-29']], self::dates($fevrier->auxDates(new \DateTimeImmutable('2028-02-10'), new \DateTimeImmutable('2028-02-12'))));
    }

    private static function periode(string $debut, string $fin): PeriodeClimatique
    {
        return (new PeriodeClimatique())->setDebut(new \DateTimeImmutable($debut))->setFin(new \DateTimeImmutable($fin));
    }

    /**
     * @param list<array{debut: \DateTimeImmutable, fin: \DateTimeImmutable}> $periodes
     *
     * @return list<array{string, string}>
     */
    private static function dates(array $periodes): array
    {
        return array_map(static fn (array $p): array => [$p['debut']->format('Y-m-d'), $p['fin']->format('Y-m-d')], $periodes);
    }
}
