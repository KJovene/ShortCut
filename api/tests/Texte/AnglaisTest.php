<?php

namespace App\Tests\Texte;

use App\Texte\Anglais;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Les textes du chiffrage, traduits pour un client qui dialogue en anglais (US15) : montants et noms intacts. */
final class AnglaisTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function textes(): iterable
    {
        yield 'logement' => [
            'Suite Caldera Oia : 5 étoiles, 1 chambre, 7 nuits du 12/06/2027 au 19/06/2027, tarif de juin 2027',
            'Suite Caldera Oia: 5 étoiles, 1 room, 7 nights from 12/06/2027 to 19/06/2027, rate for June 2027',
        ];
        yield 'deux périodes tarifaires' => [
            'Riad Yasmine : 20 chambres (2 unités), 2 nuits du 10/03/2027 au 12/03/2027, tarif d\'avril à juin 2027 puis du 01/07/2027 au 15/07/2027',
            'Riad Yasmine: 20 rooms (2 units), 2 nights from 10/03/2027 to 12/03/2027, rate for April to June 2027 then from 01/07/2027 to 15/07/2027',
        ];
        yield 'âge minimum' => [
            'Plongée : 2 participants sur 4 (âge minimum 12 ans)',
            'Plongée: 2 participants out of 4 (minimum age 12)',
        ];
        yield 'sessions' => [
            'Atelier cuisine : organisée en 3 sessions de 12 personnes au plus',
            'Atelier cuisine: organised in 3 sessions of up to 12 people',
        ];
        yield 'transport inclus' => [
            'Transport inclus : vols et transferts estimés au milieu de la fourchette, pour 2 voyageurs',
            'Transport included: flights and transfers estimated at the middle of the range, for 2 travellers',
        ];
        yield 'transport non inclus' => [
            'Transport non inclus : vols et transferts à la charge du client',
            'Transport not included: flights and transfers paid by the client',
        ];
        yield 'frais de dossier' => ['Frais de dossier voyage de noces : 150 €', 'Booking fee (honeymoon): 150 €'];
        yield 'frais confirmés' => ['Frais de dossier : 1 200 €', 'Booking fee: 1 200 €'];
        yield 'vol' => ['Vol aller-retour Paris – Santorin', 'Return flight Paris – Santorin'];
        yield 'transferts' => ['Transferts aéroport – hébergement à Oia', 'Airport – accommodation transfers in Oia'];
        yield 'arbitrage' => [
            'Réduire à 1 nuit, du 21/10/2027 au 22/10/2027, à une période moins chère',
            'Shorten to 1 night, from 21/10/2027 to 22/10/2027, at a cheaper time',
        ];
        yield 'nom de prestation' => ['Suite avec vue caldeira', 'Suite avec vue caldeira'];
    }

    #[DataProvider('textes')]
    public function testTraduitLesTextesDuChiffrage(string $francais, string $anglais): void
    {
        self::assertSame($anglais, Anglais::traduire($francais));
    }
}
