<?php

namespace App\Chatbot\Outil;

/**
 * Lecture de l'entrée des outils en mode non strict (les outils de recherche) : l'API ne garantit pas le schéma,
 * le serveur vérifie chaque champ et renvoie au modèle une erreur qu'il peut corriger. Un champ absent, null ou
 * vide vaut null.
 */
final class Entree
{
    /**
     * @param array<mixed> $entree
     */
    public static function chaine(array $entree, string $champ): ?string
    {
        if (!isset($entree[$champ])) {
            return null;
        }
        if (!\is_string($entree[$champ])) {
            throw new OutilException(\sprintf('%s doit être une chaîne.', $champ));
        }

        return '' === trim($entree[$champ]) ? null : trim($entree[$champ]);
    }

    /**
     * @param array<mixed> $entree
     */
    public static function entier(array $entree, string $champ, int $min = \PHP_INT_MIN, int $max = \PHP_INT_MAX): ?int
    {
        if (!isset($entree[$champ])) {
            return null;
        }
        if (!\is_int($entree[$champ])) {
            throw new OutilException(\sprintf('%s doit être un nombre entier.', $champ));
        }
        if ($entree[$champ] < $min || $entree[$champ] > $max) {
            throw new OutilException(\sprintf('%s doit être compris entre %d et %d.', $champ, $min, $max));
        }

        return $entree[$champ];
    }

    /**
     * @param array<mixed> $entree
     */
    public static function booleen(array $entree, string $champ): ?bool
    {
        if (!isset($entree[$champ])) {
            return null;
        }
        if (!\is_bool($entree[$champ])) {
            throw new OutilException(\sprintf('%s doit valoir true ou false.', $champ));
        }

        return $entree[$champ];
    }

    /**
     * @param array<mixed> $entree
     */
    public static function date(array $entree, string $champ): ?\DateTimeImmutable
    {
        $valeur = self::chaine($entree, $champ);
        if (null === $valeur) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $valeur);
        if (false === $date || $date->format('Y-m-d') !== $valeur) {
            throw new OutilException(\sprintf('%s doit être une date au format AAAA-MM-JJ, reçu « %s ».', $champ, $valeur));
        }

        return $date;
    }

    /**
     * @param array<mixed> $entree
     *
     * @return list<string>
     */
    public static function chaines(array $entree, string $champ): array
    {
        $chaines = [];
        foreach (self::liste($entree, $champ) as $valeur) {
            if (!\is_string($valeur)) {
                throw new OutilException(\sprintf('%s doit être une liste de chaînes.', $champ));
            }
            if ('' !== trim($valeur)) {
                $chaines[] = trim($valeur);
            }
        }

        return $chaines;
    }

    /**
     * @param array<mixed> $entree
     *
     * @return list<int>
     */
    public static function entiers(array $entree, string $champ, int $min = \PHP_INT_MIN, int $max = \PHP_INT_MAX): array
    {
        $entiers = [];
        foreach (self::liste($entree, $champ) as $valeur) {
            if (!\is_int($valeur) || $valeur < $min || $valeur > $max) {
                throw new OutilException(\sprintf('%s doit être une liste de nombres entiers entre %d et %d.', $champ, $min, $max));
            }
            $entiers[] = $valeur;
        }

        return $entiers;
    }

    /**
     * @param array<mixed> $entree
     *
     * @return list<mixed>
     */
    private static function liste(array $entree, string $champ): array
    {
        if (!isset($entree[$champ])) {
            return [];
        }
        if (!\is_array($entree[$champ]) || !array_is_list($entree[$champ])) {
            throw new OutilException(\sprintf('%s doit être une liste.', $champ));
        }

        return $entree[$champ];
    }
}
