<?php

namespace App\Tests\Chiffrage;

use App\Chiffrage\Hypothese;
use App\Chiffrage\PositionTransport;
use App\Enum\TypeDeVoyage;
use App\Texte\Anglais;
use PHPUnit\Framework\TestCase;

/**
 * Les hypothèses de transport et de frais de dossier (D30) : écrites, reconnues et traduites à partir des mêmes
 * gabarits, pour chaque position de transport et chaque type de voyage.
 */
final class HypotheseTest extends TestCase
{
    public function testLesGabaritsGardentLeTexteEnregistre(): void
    {
        self::assertSame('Transport inclus : vols et transferts estimés au milieu de la fourchette, pour 2 voyageurs', Hypothese::transport(true, PositionTransport::Milieu, 2));
        self::assertSame('Transport non inclus : vols et transferts à la charge du client', Hypothese::transport(false, PositionTransport::Milieu, 2));
        self::assertSame('Frais de dossier voyage de noces : 150 €', Hypothese::fraisDeDossier(TypeDeVoyage::Noces, 15000));
        self::assertSame('Frais de dossier : 120 €', Hypothese::fraisDeDossier(null, 12000));
    }

    public function testLaConfirmationReconnaitChaqueHypothese(): void
    {
        foreach (PositionTransport::cases() as $position) {
            self::assertTrue(Hypothese::estTransportInclus(Hypothese::transport(true, $position, 4)));
        }
        self::assertTrue(Hypothese::estTransportInclus(Hypothese::transportConfirme(4)));
        self::assertFalse(Hypothese::estTransportInclus(Hypothese::transport(false, PositionTransport::Minimum, 4)));
        foreach (TypeDeVoyage::cases() as $type) {
            self::assertTrue(Hypothese::estFraisDeDossier(Hypothese::fraisDeDossier($type, 10000)));
        }
        self::assertTrue(Hypothese::porteSur('Riad Dar Yasmine : 2 chambres', 'Riad Dar Yasmine'));
        self::assertFalse(Hypothese::porteSur('Riad Dar Yasmine Spa : 2 chambres', 'Riad Dar Yasmine'));
    }

    public function testChaqueHypotheseSeTraduitEnAnglais(): void
    {
        $hypotheses = [Hypothese::transport(false, PositionTransport::Minimum, 1), Hypothese::transportConfirme(3), Hypothese::fraisDeDossier(null, 12000)];
        foreach (PositionTransport::cases() as $position) {
            $hypotheses[] = Hypothese::transport(true, $position, 2);
        }
        foreach (TypeDeVoyage::cases() as $type) {
            $hypotheses[] = Hypothese::fraisDeDossier($type, 20000);
        }

        foreach ($hypotheses as $hypothese) {
            $traduite = Anglais::traduire($hypothese);
            self::assertDoesNotMatchRegularExpression('/inclus|Frais|dossier|voyageur|fourchette|vols|transferts|prix confirmé/u', $traduite, $hypothese);
        }
        self::assertSame('Booking fee (honeymoon): 150 €', Anglais::traduire(Hypothese::fraisDeDossier(TypeDeVoyage::Noces, 15000)));
        self::assertSame('Transport included: flights and transfers estimated at the middle of the range, for 2 travellers', Anglais::traduire(Hypothese::transport(true, PositionTransport::Milieu, 2)));
    }
}
