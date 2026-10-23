<?php

namespace App\Chiffrage;

use App\Enum\TypeDeVoyage;

/**
 * Gabarits des hypothèses de budget sur le transport et les frais de dossier (D30). Elles sont enregistrées en
 * français dans la proposition : le chiffrage les écrit, la confirmation les reconnaît et les réécrit, Texte\Anglais
 * les traduit. Les trois passent par ici, pour qu'une reformulation ne casse ni la confirmation ni la traduction.
 */
final class Hypothese
{
    private const TRANSPORT_INCLUS = 'Transport inclus : vols et transferts';
    private const TRANSPORT_NON_INCLUS = 'Transport non inclus : vols et transferts à la charge du client';
    private const FRAIS_DE_DOSSIER = 'Frais de dossier';

    /** « Transport inclus : vols et transferts estimés au milieu de la fourchette, pour 2 voyageurs ». */
    public static function transport(bool $inclus, PositionTransport $position, int $voyageurs): string
    {
        return $inclus
            ? \sprintf('%s estimés au %s, pour %s', self::TRANSPORT_INCLUS, $position->label(), Montant::quantite($voyageurs, 'voyageur'))
            : self::TRANSPORT_NON_INCLUS;
    }

    /** Après confirmation : le transport au prix vérifié par le conseiller. */
    public static function transportConfirme(int $voyageurs): string
    {
        return \sprintf('%s au prix confirmé, pour %s', self::TRANSPORT_INCLUS, Montant::quantite($voyageurs, 'voyageur'));
    }

    /** « Frais de dossier voyage de noces : 150 € » ; sans type, les frais confirmés. */
    public static function fraisDeDossier(?TypeDeVoyage $type, int $centimes): string
    {
        return \sprintf('%s%s : %s', self::FRAIS_DE_DOSSIER, null === $type ? '' : ' '.mb_strtolower($type->label()), Montant::euros($centimes));
    }

    public static function estTransportInclus(string $hypothese): bool
    {
        return str_starts_with($hypothese, self::TRANSPORT_INCLUS);
    }

    public static function estFraisDeDossier(string $hypothese): bool
    {
        return str_starts_with($hypothese, self::FRAIS_DE_DOSSIER);
    }

    /** L'hypothèse porte sur cette prestation (« Riad Dar Yasmine : 2 chambres… »). */
    public static function porteSur(string $hypothese, string $prestation): bool
    {
        return str_starts_with($hypothese, $prestation.' :');
    }

    /**
     * Les débuts de phrase à traduire pour un client qui écrit en anglais (Texte\Anglais), générés depuis les mêmes
     * gabarits : chaque position de transport et chaque type de voyage est couvert.
     *
     * @return array<string, string>
     */
    public static function enAnglais(): array
    {
        $traductions = [];
        foreach (PositionTransport::cases() as $position) {
            $traductions[\sprintf('%s estimés au %s, pour', self::TRANSPORT_INCLUS, $position->label())]
                = \sprintf('Transport included: flights and transfers estimated at %s, for', $position->labelEnAnglais());
        }
        $traductions[self::TRANSPORT_INCLUS.' au prix confirmé, pour'] = 'Transport included: flights and transfers at the confirmed price, for';
        $traductions[self::TRANSPORT_NON_INCLUS] = 'Transport not included: flights and transfers paid by the client';
        foreach (TypeDeVoyage::cases() as $type) {
            $traductions[\sprintf('%s %s :', self::FRAIS_DE_DOSSIER, mb_strtolower($type->label()))] = \sprintf('Booking fee (%s):', mb_strtolower($type->labelEnAnglais()));
        }
        $traductions[self::FRAIS_DE_DOSSIER.' :'] = 'Booking fee:';

        return $traductions;
    }
}
