<?php

namespace App\Chatbot\Outil;

use App\Chatbot\ContexteAgent;
use App\Entity\Destination;
use App\Entity\PeriodeClimatique;
use App\Enum\TypeDeVoyage;
use App\Repository\DestinationRepository;

/**
 * Destinations publiées compatibles avec une zone, des dates et un climat (docs/outils.md).
 * Version simple du ticket #192 : filtre par zone et par température ; les tarifs aux dates et les envies
 * viennent avec le ticket #202.
 */
final class ChercherDestinations implements Outil
{
    private const LIMITE_PAR_DEFAUT = 5;

    public function __construct(private readonly DestinationRepository $destinations)
    {
    }

    public function nom(): string
    {
        return 'chercher_destinations';
    }

    public function definition(): array
    {
        return [
            'name' => $this->nom(),
            'description' => 'Cherche dans le catalogue de l\'agence les destinations publiées compatibles avec les critères donnés. Ne cite jamais au client une destination qui n\'est pas dans ce résultat.',
            'strict' => false,
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'zone' => ['type' => 'string', 'description' => 'Pays, région, ville ou zone large (« Méditerranée ») ; omettre pour tout le catalogue.'],
                    'dateDebut' => ['type' => 'string', 'description' => 'AAAA-MM-JJ'],
                    'dateFin' => ['type' => 'string', 'description' => 'AAAA-MM-JJ'],
                    'temperatureMin' => ['type' => 'integer'],
                    'temperatureMax' => ['type' => 'integer'],
                    'typeDeVoyage' => ['type' => 'string', 'enum' => array_map(static fn (TypeDeVoyage $t) => $t->value, TypeDeVoyage::cases())],
                    'envies' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'villeDepart' => ['type' => 'string', 'description' => 'Pour renvoyer le temps de trajet.'],
                    'limite' => ['type' => 'integer', 'description' => 'Nombre maximum de résultats (5 par défaut).'],
                ],
                'required' => [],
                'additionalProperties' => false,
            ],
        ];
    }

    public function executer(ContexteAgent $contexte, array $entree): array
    {
        // Mode non strict : le serveur vérifie lui-même l'entrée.
        $zone = self::chaine($entree, 'zone');
        $villeDepart = self::chaine($entree, 'villeDepart');
        $debut = self::date($entree, 'dateDebut');
        $fin = self::date($entree, 'dateFin') ?? $debut;
        $temperatureMin = self::entier($entree, 'temperatureMin');
        $temperatureMax = self::entier($entree, 'temperatureMax');
        $limite = max(1, min(10, self::entier($entree, 'limite') ?? self::LIMITE_PAR_DEFAUT));

        if (null !== $debut && null !== $fin && $fin < $debut) {
            throw new OutilException('dateFin doit être postérieure à dateDebut.');
        }

        $publiees = $this->destinations->findBy(['publiee' => true], ['ville' => 'ASC']);
        $candidates = null === $zone ? $publiees : array_values(array_filter(
            $publiees,
            static fn (Destination $d) => self::correspond($d, $zone),
        ));

        $resultat = ['destinations' => [], 'ecartees' => []];
        if (null !== $zone && [] === $candidates) {
            // Zone large ou inconnue : le modèle choisit lui-même dans le catalogue.
            $resultat['remarque'] = \sprintf('Aucune destination ne porte le nom « %s » : voici tout le catalogue publié.', $zone);
            $candidates = $publiees;
        }

        foreach ($candidates as $destination) {
            $climat = null === $debut ? [] : self::climatAuxDates($destination, $debut, $fin ?? $debut);
            $raison = self::raisonClimat($climat, $temperatureMin, $temperatureMax);
            if (null !== $raison) {
                $resultat['ecartees'][] = ['slug' => $destination->getSlug(), 'raison' => $raison];
                continue;
            }

            if (\count($resultat['destinations']) >= $limite) {
                break;
            }
            $resultat['destinations'][] = [
                'slug' => $destination->getSlug(),
                'ville' => $destination->getVille(),
                'pays' => $destination->getPays(),
                'region' => $destination->getRegion(),
                'pointsForts' => $destination->getPointsForts(),
                'description' => $destination->getDescription(),
                'climatAuxDates' => $climat,
                'tempsDeTrajetMinutes' => null === $villeDepart ? null : self::tempsDeTrajet($destination, $villeDepart),
            ];
        }

        return $resultat;
    }

    private static function correspond(Destination $destination, string $zone): bool
    {
        $cherche = self::normaliser($zone);
        foreach ([$destination->getVille(), $destination->getPays(), $destination->getRegion(), $destination->getSlug()] as $champ) {
            $valeur = self::normaliser((string) $champ);
            if ('' !== $valeur && (str_contains($cherche, $valeur) || str_contains($valeur, $cherche))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{debut: string, fin: string, temperatureMoyenne: float|null, pluieMm: int|null}>
     */
    private static function climatAuxDates(Destination $destination, \DateTimeImmutable $debut, \DateTimeImmutable $fin): array
    {
        $periodes = $destination->getPeriodesClimatiques()->filter(
            static fn (PeriodeClimatique $p) => null !== $p->getDebut() && null !== $p->getFin() && $p->getDebut() <= $fin && $p->getFin() >= $debut,
        );

        return array_values(array_map(static fn (PeriodeClimatique $p) => [
            'debut' => $p->getDebut()?->format('Y-m-d') ?? '',
            'fin' => $p->getFin()?->format('Y-m-d') ?? '',
            'temperatureMoyenne' => $p->getTemperatureMoyenne(),
            'pluieMm' => $p->getPluieMm(),
        ], $periodes->toArray()));
    }

    /**
     * @param list<array{debut: string, fin: string, temperatureMoyenne: float|null, pluieMm: int|null}> $climat
     */
    private static function raisonClimat(array $climat, ?int $min, ?int $max): ?string
    {
        $temperatures = array_filter(array_column($climat, 'temperatureMoyenne'), static fn ($t) => null !== $t);
        if ([] === $temperatures) {
            return null;
        }
        if (null !== $min && max($temperatures) < $min) {
            return \sprintf('%s °C en moyenne aux dates, moins que les %d °C souhaités.', max($temperatures), $min);
        }
        if (null !== $max && min($temperatures) > $max) {
            return \sprintf('%s °C en moyenne aux dates, plus que les %d °C souhaités.', min($temperatures), $max);
        }

        return null;
    }

    private static function tempsDeTrajet(Destination $destination, string $villeDepart): ?int
    {
        $cherche = self::normaliser($villeDepart);
        foreach ($destination->getTempsDeTrajet() as $ville => $minutes) {
            if (self::normaliser($ville) === $cherche) {
                return $minutes;
            }
        }

        return null;
    }

    /** « Méditerranée » → « mediterranee » ; les services ignorent majuscules et accents. */
    private static function normaliser(string $texte): string
    {
        $ascii = \Transliterator::create('Any-Latin; Latin-ASCII; Lower()')?->transliterate($texte);

        return trim(\is_string($ascii) ? $ascii : mb_strtolower($texte));
    }

    /**
     * @param array<mixed> $entree
     */
    private static function chaine(array $entree, string $champ): ?string
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
    private static function entier(array $entree, string $champ): ?int
    {
        if (!isset($entree[$champ])) {
            return null;
        }
        if (!\is_int($entree[$champ])) {
            throw new OutilException(\sprintf('%s doit être un nombre entier.', $champ));
        }

        return $entree[$champ];
    }

    /**
     * @param array<mixed> $entree
     */
    private static function date(array $entree, string $champ): ?\DateTimeImmutable
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
}
