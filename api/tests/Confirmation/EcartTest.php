<?php

namespace App\Tests\Confirmation;

use App\Confirmation\Ecart;
use App\Twig\MontantExtension;
use PHPUnit\Framework\TestCase;

/** Écarts entre estimé et confirmé (#209) et leur écriture dans le backoffice. */
final class EcartTest extends TestCase
{
    public function testUnPrixQuiMonteDonneUnEcartPositifArrondiAUneDecimale(): void
    {
        $ecart = new Ecart(318000, 360000);

        self::assertSame(42000, $ecart->centimes());
        self::assertSame(13.2, $ecart->pourcentage());
        self::assertTrue($ecart->important());
        self::assertSame('+420 €', MontantExtension::ecartEuros(42000));
        self::assertSame('+13,2 %', MontantExtension::ecartPourcentage(13.2));
    }

    public function testUnPrixQuiBaisseDonneUnEcartNegatif(): void
    {
        $ecart = new Ecart(20000, 15000);

        self::assertSame(-5000, $ecart->centimes());
        self::assertSame(-25.0, $ecart->pourcentage());
        self::assertTrue($ecart->important());
        self::assertSame('−50 €', MontantExtension::ecartEuros(-5000));
        self::assertSame('−25 %', MontantExtension::ecartPourcentage(-25.0));
    }

    public function testDixPourCentPileNEstPasSignale(): void
    {
        self::assertFalse((new Ecart(10000, 11000))->important());
        self::assertTrue((new Ecart(10000, 11010))->important());
    }

    public function testSansPrixConfirmeIlNYAPasDEcart(): void
    {
        $ecart = new Ecart(43200, null);

        self::assertNull($ecart->centimes());
        self::assertNull($ecart->pourcentage());
        self::assertFalse($ecart->important());
    }

    public function testUnEstimeNulNADonnePasDePourcentage(): void
    {
        $ecart = new Ecart(0, 5000);

        self::assertSame(5000, $ecart->centimes());
        self::assertNull($ecart->pourcentage());
        self::assertSame('0 €', MontantExtension::ecartEuros(0));
    }
}
