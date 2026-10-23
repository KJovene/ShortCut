<?php

namespace App\Texte;

use App\Chiffrage\Hypothese;

/**
 * Les textes que le chiffrage écrit en français (hypothèses de budget, libellés de transport, arbitrages), traduits
 * pour un client qui dialogue en anglais (US15). Ils restent enregistrés en français, car le backoffice et la
 * confirmation les relisent ainsi ; seule leur présentation au client change.
 *
 * La traduction remplace des morceaux connus et garde le reste : noms de prestations, catégories, montants et dates
 * sont repris tels quels, pour que chaque montant reste recalculable. Un morceau inconnu reste en français.
 */
final class Anglais
{
    private const MOIS = [
        'janvier' => 'January', 'février' => 'February', 'mars' => 'March', 'avril' => 'April', 'mai' => 'May', 'juin' => 'June',
        'juillet' => 'July', 'août' => 'August', 'septembre' => 'September', 'octobre' => 'October', 'novembre' => 'November', 'décembre' => 'December',
    ];

    /** Morceaux de phrase, en plus des hypothèses de transport et de frais (Chiffrage\Hypothese::enAnglais). */
    private const MORCEAUX = [
        'Vol aller-retour ' => 'Return flight ',
        'Transferts aéroport – hébergement à ' => 'Airport – accommodation transfers in ',
        'Partir à une période moins chère, ' => 'Travel at a cheaper time, ',
        ', à une période moins chère' => ', at a cheaper time',
        'Réduire à ' => 'Shorten to ',
        ', tarif ' => ', rate for ',
        ' puis ' => ' then ',
    ];

    /** Mots comptés : « 3 nuits », « 1 chambre ». */
    private const QUANTITES = [
        'nuit' => 'night', 'chambre' => 'room', 'unité' => 'unit', 'voyageur' => 'traveller', 'participant' => 'participant',
    ];

    public static function traduire(string $texte): string
    {
        $texte = strtr($texte, Hypothese::enAnglais() + self::MORCEAUX);

        $texte = (string) preg_replace_callback(
            '/\b(\d+) ('.implode('|', array_keys(self::QUANTITES)).')s?\b/u',
            static fn (array $m): string => $m[1].' '.self::QUANTITES[$m[2]].('1' === $m[1] ? '' : 's'),
            $texte,
        );
        // Activités : « 2 participants sur 4 (âge minimum 8 ans) », « organisée en 2 sessions de 12 personnes au plus ».
        $texte = (string) preg_replace('/ sur (\d+) \(âge minimum (\d+) ans\)/u', ' out of $1 (minimum age $2)', $texte);
        $texte = (string) preg_replace('/organisée en (\d+) sessions de (\d+) personnes au plus/u', 'organised in $1 sessions of up to $2 people', $texte);
        // Périodes (Periode::ecrire et Periode::sejour) : « du 14/05/2027 au 17/05/2027 », « d'avril à juin 2027 ».
        $texte = (string) preg_replace('#\bdu (\d{2}/\d{2}/\d{4}) au (\d{2}/\d{2}/\d{4})#u', 'from $1 to $2', $texte);
        $mois = implode('|', array_keys(self::MOIS));
        $texte = (string) preg_replace_callback(
            "/\\b(?:de |d')($mois)(?: (\\d{4}))?(?: à ($mois))?(?= \\d{4}|\\b)/u",
            static fn (array $m): string => self::MOIS[$m[1]].(isset($m[2]) && '' !== $m[2] ? ' '.$m[2] : '').(isset($m[3]) ? ' to '.self::MOIS[$m[3]] : ''),
            $texte,
        );

        // « Nom : détail » devient « Nom: détail ».
        return str_replace(' : ', ': ', $texte);
    }
}
