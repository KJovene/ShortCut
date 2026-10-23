<?php

namespace App\Admin;

use App\Entity\TarifParPeriode;
use App\Enum\UniteTarif;

/**
 * Les tarifs d'un logement ou d'une activité, lus par un conseiller : dans l'ordre des dates, avec la période en
 * cours et les jours sans tarif à venir (le chatbot ne propose pas la fiche ces jours-là).
 */
final class GrilleTarifs
{
    /** Horizon des trous signalés : au-delà, l'absence de tarif n'est pas encore un oubli. */
    private const HORIZON = '+12 months';

    /**
     * Une ligne par tarif, et une ligne « trou » entre deux tarifs à venir qui ne se suivent pas, ou après le
     * dernier s'il s'arrête avant l'horizon. Un trou qui commence aujourd'hui (`avant`) dit seulement que les
     * tarifs saisis n'ont pas encore commencé.
     *
     * @param iterable<TarifParPeriode> $tarifs
     *
     * @return list<array{trou: false, tarif: TarifParPeriode, etat: 'passe'|'en_cours'|'a_venir'}|array{trou: true, avant: bool, debut: \DateTimeImmutable, fin: ?\DateTimeImmutable}>
     */
    public static function lignes(iterable $tarifs, \DateTimeImmutable $aujourdhui): array
    {
        $aujourdhui = $aujourdhui->setTime(0, 0);
        $lignes = [];
        $couvertJusquau = $aujourdhui->modify('-1 day');
        foreach (self::trier($tarifs) as $tarif) {
            /** @var \DateTimeImmutable $debut */
            $debut = $tarif->getDebut();
            /** @var \DateTimeImmutable $fin */
            $fin = $tarif->getFin();
            if ($fin >= $aujourdhui && $debut > $couvertJusquau->modify('+1 day')) {
                $lignes[] = ['trou' => true, 'avant' => $couvertJusquau < $aujourdhui, 'debut' => $couvertJusquau->modify('+1 day'), 'fin' => $debut->modify('-1 day')];
            }
            $lignes[] = ['trou' => false, 'tarif' => $tarif, 'etat' => match (true) {
                $fin < $aujourdhui => 'passe',
                $debut <= $aujourdhui => 'en_cours',
                default => 'a_venir',
            }];
            if ($fin > $couvertJusquau) {
                $couvertJusquau = $fin;
            }
        }
        if ([] !== $lignes && $couvertJusquau < $aujourdhui->modify(self::HORIZON)) {
            $lignes[] = ['trou' => true, 'avant' => false, 'debut' => $couvertJusquau->modify('+1 day'), 'fin' => null];
        }

        return $lignes;
    }

    /**
     * Ce qu'une liste montre d'une fiche : la fourchette des prix d'aujourd'hui et à venir, la période qu'ils
     * couvrent (`depuis` si elle n'a pas commencé), et le premier jour sans tarif avant l'horizon. Null si aucun
     * tarif ne reste à venir.
     *
     * @param iterable<TarifParPeriode> $tarifs
     *
     * @return array{min: int, max: int, unite: UniteTarif, depuis: ?\DateTimeImmutable, jusquau: \DateTimeImmutable, premierTrou: ?\DateTimeImmutable}|null
     */
    public static function resume(iterable $tarifs, \DateTimeImmutable $aujourdhui): ?array
    {
        $prix = [];
        $unite = UniteTarif::ParNuit;
        $jusquau = null;
        $premierTrou = null;
        $depuis = null;
        foreach (self::lignes($tarifs, $aujourdhui) as $ligne) {
            if ($ligne['trou']) {
                if ($ligne['avant']) {
                    $depuis = $ligne['fin'];
                } else {
                    $premierTrou ??= $ligne['debut'];
                }
                continue;
            }
            if ('passe' === $ligne['etat']) {
                continue;
            }
            $prix[] = (int) $ligne['tarif']->getPrixCentimes();
            $unite = $ligne['tarif']->getUnite();
            $fin = $ligne['tarif']->getFin();
            if (null === $jusquau || $fin > $jusquau) {
                $jusquau = $fin;
            }
        }
        if ([] === $prix || null === $jusquau) {
            return null;
        }

        return [
            'min' => min($prix),
            'max' => max($prix),
            'unite' => $unite,
            'depuis' => $depuis?->modify('+1 day'),
            'jusquau' => $jusquau,
            'premierTrou' => $premierTrou,
        ];
    }

    /**
     * @param iterable<TarifParPeriode> $tarifs
     *
     * @return list<TarifParPeriode>
     */
    private static function trier(iterable $tarifs): array
    {
        $liste = array_values(array_filter(
            [...$tarifs],
            static fn (TarifParPeriode $t): bool => null !== $t->getDebut() && null !== $t->getFin(),
        ));
        usort($liste, static fn (TarifParPeriode $a, TarifParPeriode $b): int => $a->getDebut() <=> $b->getDebut());

        return $liste;
    }
}
