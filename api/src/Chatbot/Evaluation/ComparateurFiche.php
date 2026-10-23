<?php

namespace App\Chatbot\Evaluation;

/**
 * Compare, champ par champ, la fiche remplie par le chatbot à la fiche attendue d'une demande test (#195).
 * Les champs en texte libre (lieu, envies, contraintes) ne se comparent pas mot à mot : ils sont rendus
 * « à juger » et tranchés par le Juge.
 */
final class ComparateurFiche
{
    public const OK = 'ok';
    public const PARTIEL = 'partiel';
    public const MANQUANT = 'manquant';
    public const FAUX = 'faux';
    public const EN_TROP = 'en trop';
    public const A_JUGER = 'à juger';

    /** Champs comparés à l'identique (les listes d'âges sans tenir compte de l'ordre). */
    private const EXACTS = [
        'typeDeVoyage', 'dateDebut', 'dateFin', 'datesFlexibles', 'nombreDeJours',
        'adultes', 'enfants', 'agesEnfants', 'budgetEuros', 'budgetParPersonne', 'transportInclus',
    ];

    /** Champs en texte libre, jugés par un modèle. */
    public const SEMANTIQUES = ['lieuOuZone', 'envies', 'contraintes'];

    /**
     * @param array<string, mixed> $attendue fiche attendue (exigencesAttendues du fichier de test)
     * @param array<string, mixed> $obtenue  fiche remplie (EnregistrerExigences::fiche)
     *
     * @return array<string, array{statut: string, attendu: mixed, obtenu: mixed}> par champ, dans l'ordre de la fiche attendue
     */
    public static function comparer(array $attendue, array $obtenue): array
    {
        $resultat = [];
        foreach ($attendue as $champ => $valeurAttendue) {
            $valeurObtenue = $obtenue[$champ] ?? null;
            $resultat[$champ] = [
                'statut' => self::statut($champ, $valeurAttendue, $valeurObtenue),
                'attendu' => $valeurAttendue,
                'obtenu' => $valeurObtenue,
            ];
        }

        return $resultat;
    }

    /**
     * Exigence exprimée par le client : une valeur attendue non vide. Un booléen compte toujours (le client a dit
     * « transport compris » ou non) ; une valeur attendue nulle ou vide ne sert qu'à repérer les valeurs inventées.
     */
    public static function estExigence(mixed $valeurAttendue): bool
    {
        return null !== $valeurAttendue && [] !== $valeurAttendue && '' !== $valeurAttendue;
    }

    private static function statut(string $champ, mixed $attendu, mixed $obtenu): string
    {
        $vide = null === $obtenu || [] === $obtenu || '' === $obtenu;

        if (!self::estExigence($attendu)) {
            return $vide ? self::OK : (\in_array($champ, self::SEMANTIQUES, true) ? self::A_JUGER : self::EN_TROP);
        }
        if ($vide) {
            return self::MANQUANT;
        }
        if (\in_array($champ, self::SEMANTIQUES, true)) {
            return self::A_JUGER;
        }
        if ('villeDepart' === $champ) {
            return self::normaliser((string) $attendu) === self::normaliser((string) $obtenu) ? self::OK : self::FAUX;
        }
        if (\in_array($champ, self::EXACTS, true) && \is_array($attendu) && \is_array($obtenu)) {
            sort($attendu);
            sort($obtenu);
        }

        return $attendu === $obtenu ? self::OK : self::FAUX;
    }

    /** « Bordeaux », « bordeaux », « Bordeaux (Mérignac) » : même ville de départ. */
    private static function normaliser(string $texte): string
    {
        $ascii = \Transliterator::create('Any-Latin; Latin-ASCII; Lower()')?->transliterate($texte);
        $texte = \is_string($ascii) ? $ascii : mb_strtolower($texte);

        return trim((string) preg_replace('/\s*\(.*\)$/', '', $texte));
    }
}
