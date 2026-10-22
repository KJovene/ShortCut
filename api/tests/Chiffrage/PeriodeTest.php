<?php

namespace App\Tests\Chiffrage;

use App\Chiffrage\Periode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PeriodeTest extends TestCase
{
    /**
     * @return iterable<array{string, string, string}>
     */
    public static function periodes(): iterable
    {
        yield ['2027-04-01', '2027-06-30', 'd\'avril à juin 2027'];
        yield ['2027-05-01', '2027-05-31', 'de mai 2027'];
        yield ['2027-08-01', '2027-08-31', 'd\'août 2027'];
        yield ['2027-10-01', '2027-12-31', 'd\'octobre à décembre 2027'];
        yield ['2027-11-01', '2028-02-29', 'de novembre 2027 à février 2028'];
        yield ['2027-05-14', '2027-06-30', 'du 14/05/2027 au 30/06/2027'];
        yield ['2027-05-01', '2027-05-30', 'du 01/05/2027 au 30/05/2027'];
    }

    #[DataProvider('periodes')]
    public function testEcrire(string $debut, string $fin, string $attendu): void
    {
        self::assertSame($attendu, Periode::ecrire(new \DateTimeImmutable($debut), new \DateTimeImmutable($fin)));
    }

    public function testSejour(): void
    {
        self::assertSame('du 21/10/2027 au 25/10/2027', Periode::sejour(new \DateTimeImmutable('2027-10-21'), 4));
    }

    public function testHeure(): void
    {
        self::assertSame('9 h 40', Periode::heure(new \DateTimeImmutable('2026-10-22 09:40')));
        self::assertSame('15 h', Periode::heure(new \DateTimeImmutable('2026-10-22 15:00')));
    }
}
