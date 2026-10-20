<?php

namespace App\Twig;

use App\Chiffrage\Periode;
use Twig\Attribute\AsTwigFilter;

/**
 * Dates écrites pour le client, en français : « 12 juin 2027 », « sam. 12 juin ». Sans IntlDateFormatter : l'image
 * PHP n'embarque que les données ICU anglaises (voir Periode).
 */
final class DateExtension
{
    #[AsTwigFilter('date_longue')]
    public static function dateLongue(\DateTimeInterface|string $date): string
    {
        return Periode::date(self::immuable($date));
    }

    #[AsTwigFilter('date_jour')]
    public static function dateJour(\DateTimeInterface|string $date): string
    {
        return Periode::jourCourt(self::immuable($date));
    }

    private static function immuable(\DateTimeInterface|string $date): \DateTimeImmutable
    {
        return \is_string($date) ? new \DateTimeImmutable($date) : \DateTimeImmutable::createFromInterface($date);
    }
}
