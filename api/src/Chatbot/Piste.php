<?php

namespace App\Chatbot;

use App\Chiffrage\Montant;
use App\Chiffrage\Periode;
use App\Entity\Demande;
use App\Enum\Langue;
use App\Enum\NiveauBudget;
use App\Texte\Anglais;

/**
 * La piste présentée au client, en carte dans le chat (événement « piste ») : destination, dates, faits, les trois
 * niveaux et leur total, le niveau conseillé, les activités en option et l'avertissement daté. Elle est faite de la
 * sortie de retenir_combinaison, jamais d'un texte du modèle : les montants sont ceux du chiffrage.
 *
 * Son texte (texte()) la remplace là où la carte ne s'affiche pas (conversations de « Mon espace ») et dit à l'agent
 * Conversation ce que le client a sous les yeux.
 */
final class Piste
{
    /**
     * @param array<string, mixed> $sortie sortie de retenir_combinaison
     *
     * @return array<string, mixed>
     */
    public static function depuis(array $sortie, Demande $demande): array
    {
        /** @var list<array{dateDebut: string, nombreDeNuits: int}> $etapes */
        $etapes = $sortie['etapes'];
        $nuits = array_sum(array_column($etapes, 'nombreDeNuits'));
        $debut = new \DateTimeImmutable($etapes[0]['dateDebut']);
        $justification = $sortie['justification'];
        $temperatures = array_filter(array_column($justification['climatAuxDates'] ?? [], 'temperatureMoyenne'), static fn ($t): bool => null !== $t);

        return [
            'destination' => [
                'slug' => $sortie['destination']['slug'],
                'ville' => $sortie['destination']['ville'],
                'pays' => $sortie['destination']['pays'],
            ],
            'dateDebut' => $debut->format('Y-m-d'),
            'dateFin' => $debut->modify(\sprintf('+%d days', $nuits))->format('Y-m-d'),
            'nuits' => $nuits,
            'voyageurs' => $demande->getAdultes() + $demande->getEnfants(),
            'temperatureMoyenne' => [] === $temperatures ? null : (int) round(array_sum($temperatures) / \count($temperatures)),
            'villeDepart' => $justification['villeDepart'] ?? null,
            'tempsDeTrajetMinutes' => $justification['tempsDeTrajetMinutes'] ?? null,
            'envies' => $justification['enviesCorrespondantes'] ?? [],
            'niveauConseille' => $sortie['niveauConseille'],
            'niveaux' => array_map(static fn (array $n): array => [
                'logements' => array_column($n['logements'], 'nom'),
                'activites' => array_column($n['activites'], 'nom'),
                'transport' => $n['transport'],
                'totalEuros' => $n['totalEuros'],
                'parPersonneEuros' => $n['parPersonneEuros'],
            ], $sortie['niveaux']),
            'options' => $sortie['options'] ?? [],
            'budgetInsuffisant' => $sortie['budgetInsuffisant'] ?? false,
            'arbitrages' => array_map(static fn (array $a): array => ['libelle' => $a['libelle'], 'totalEuros' => $a['totalEuros']], $sortie['arbitrages'] ?? []),
            'avertissement' => $sortie['avertissement'],
        ];
    }

    /**
     * « Kyoto (Japon), du 2 février 2027 au 16 février 2027, 14 nuits, 1 voyageur. » puis les faits, une ligne par
     * niveau, les options et l'avertissement.
     *
     * @param array<string, mixed> $piste
     */
    public static function texte(array $piste, Langue $langue): string
    {
        $anglais = Langue::Anglais === $langue;
        $date = static fn (string $iso): string => $anglais ? (new \DateTimeImmutable($iso))->format('j F Y') : Periode::date(new \DateTimeImmutable($iso));
        $euros = static fn (int|float $montant): string => Montant::euros((int) round($montant * 100));
        $deuxPoints = $anglais ? ': ' : ' : ';

        $lignes = [\sprintf(
            $anglais ? '%s (%s), from %s to %s, %s, %s.' : '%s (%s), du %s au %s, %s, %s.',
            $piste['destination']['ville'],
            $anglais ? Anglais::traduire((string) $piste['destination']['pays']) : $piste['destination']['pays'],
            $date($piste['dateDebut']),
            $date($piste['dateFin']),
            $anglais ? Montant::quantite($piste['nuits'], 'night') : Montant::quantite($piste['nuits'], 'nuit'),
            $anglais ? Montant::quantite($piste['voyageurs'], 'traveller') : Montant::quantite($piste['voyageurs'], 'voyageur'),
        )];
        $faits = [];
        if (null !== $piste['temperatureMoyenne']) {
            $faits[] = \sprintf($anglais ? '%d °C on average' : '%d °C en moyenne', $piste['temperatureMoyenne']);
        }
        if (null !== $piste['tempsDeTrajetMinutes'] && null !== $piste['villeDepart']) {
            $faits[] = \sprintf($anglais ? 'about %s from %s' : 'environ %s de trajet depuis %s', self::duree((int) $piste['tempsDeTrajetMinutes']), $piste['villeDepart']);
        }
        if ([] !== $faits) {
            $lignes[] = ucfirst(implode(', ', $faits)).'.';
        }

        foreach (NiveauBudget::cases() as $niveau) {
            $n = $piste['niveaux'][$niveau->value];
            $total = $euros($n['totalEuros']);
            if ($piste['voyageurs'] > 1) {
                $total .= \sprintf($anglais ? ' (%s per person)' : ' (%s par personne)', $euros($n['parPersonneEuros']));
            }
            $lignes[] = \sprintf(
                '- %s%s%s%s, %s',
                $anglais ? $niveau->labelEnAnglais() : $niveau->label(),
                $niveau->value === $piste['niveauConseille'] ? ($anglais ? ' (recommended)' : ' (conseillé)') : '',
                $deuxPoints,
                implode(', ', [...$n['logements'], ...$n['activites']]),
                $total,
            );
        }
        if ([] !== $piste['options']) {
            $lignes[] = ($anglais ? 'Options' : 'En option').$deuxPoints.implode(', ', array_map(
                static fn (array $o): string => \sprintf('%s (+%s)', $o['nom'], $euros($o['prixEuros'])),
                $piste['options'],
            )).'.';
        }
        $lignes[] = $piste['avertissement'];

        return implode("\n", $lignes);
    }

    /** « 2 h 30 », « 13 h », « 45 min » ; en anglais « 2 h 30 » aussi, lisible dans les deux langues. */
    private static function duree(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes.' min';
        }

        return \sprintf('%d h%s', intdiv($minutes, 60), 0 === $minutes % 60 ? '' : \sprintf(' %02d', $minutes % 60));
    }
}
