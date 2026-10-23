<?php

namespace App\Twig;

use App\Chiffrage\Periode;
use App\Enum\Langue;
use Twig\Attribute\AsTwigFilter;

/**
 * Dates écrites pour le client, en français : « 12 juin 2027 », « sam. 12 juin ». Sans IntlDateFormatter : l'image
 * PHP n'embarque que les données ICU anglaises (voir Periode). En anglais pour un client qui dialogue en anglais
 * (US15) : « 12 June 2027 », « Sat 12 Jun ».
 */
final class DateExtension
{
    #[AsTwigFilter('date_longue')]
    public static function dateLongue(\DateTimeInterface|string $date, string $langue = 'fr'): string
    {
        return Langue::Anglais->value === $langue ? self::immuable($date)->format('j F Y') : Periode::date(self::immuable($date));
    }

    #[AsTwigFilter('date_jour')]
    public static function dateJour(\DateTimeInterface|string $date, string $langue = 'fr'): string
    {
        return Langue::Anglais->value === $langue ? self::immuable($date)->format('D j M') : Periode::jourCourt(self::immuable($date));
    }

    private static function immuable(\DateTimeInterface|string $date): \DateTimeImmutable
    {
        return \is_string($date) ? new \DateTimeImmutable($date) : \DateTimeImmutable::createFromInterface($date);
    }
}
