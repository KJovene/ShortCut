<?php

namespace App\Chatbot\Outil;

use App\Chatbot\ContexteAgent;
use App\Chiffrage\Montant;
use App\Entity\Destination;
use App\Entity\PeriodeClimatique;
use App\Enum\MotifTransmission;
use App\Entity\Prestation;
use App\Entity\TarifParPeriode;
use App\Enum\TypeDeVoyage;
use App\Enum\TypePrestation;
use App\Repository\DestinationRepository;
use App\Repository\PrestationRepository;
use App\Texte\Correspondance;
use Psr\Clock\ClockInterface;

/**
 * Destinations publiées compatibles avec une zone, des dates, un climat et des envies (docs/outils.md).
 * Aux dates données, une destination n'est retenue que si chaque mois du voyage respecte les températures
 * souhaitées et qu'au moins un logement a un tarif pour chaque nuit (#202) ; sinon elle est rangée dans
 * `ecartees` avec la raison, pour que l'agent propose d'autres dates ou passe la main au conseiller.
 */
final class ChercherDestinations implements Outil
{
    private const LIMITE_PAR_DEFAUT = 5;
    /** Au-delà, ce ne sont plus les dates d'un voyage mais une saison : l'agent doit préciser. */
    private const NUITS_MAX = 60;
    /** Jusqu'où chercher d'autres dates pour une destination écartée, de part et d'autre des dates demandées. */
    public const FENETRE_JOURS = 182;

    public function __construct(
        private readonly DestinationRepository $destinations,
        private readonly EntityManagerInterface $em,
        private readonly PrestationRepository $prestations,
        private readonly ClockInterface $horloge,
    ) {
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
                    'dateDebut' => ['type' => 'string', 'description' => 'Jour du départ, AAAA-MM-JJ.'],
                    'dateFin' => ['type' => 'string', 'description' => 'Jour du retour, AAAA-MM-JJ.'],
                    'temperatureMin' => ['type' => 'integer', 'description' => 'Température moyenne minimale, en °C ; demande les dates.'],
                    'temperatureMax' => ['type' => 'integer'],
                    'typeDeVoyage' => ['type' => 'string', 'enum' => array_map(static fn (TypeDeVoyage $t) => $t->value, TypeDeVoyage::cases())],
                    'envies' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Les destinations qui y répondent le mieux sont listées en premier.'],
                    'villeDepart' => ['type' => 'string', 'description' => 'Pour renvoyer le temps de trajet.'],
                    'voyageurs' => ['type' => 'integer', 'description' => 'Adultes et enfants : écarte les destinations sans logement pour le groupe.'],
                    'accessibleObligatoire' => ['type' => 'boolean', 'description' => 'true si un voyageur a besoin d\'un logement accessible.'],
                    'limite' => ['type' => 'integer', 'description' => 'Nombre maximum de résultats (5 par défaut).'],
                ],
                'required' => [],
                'additionalProperties' => false,
            ],
        ];
    }

    public function executer(ContexteAgent $contexte, array $entree): array
    {
        return $this->rechercher($entree, max(1, min(10, Entree::entier($entree, 'limite') ?? self::LIMITE_PAR_DEFAUT)));
    }

    /**
     * La recherche, sans limite si $limite est null : retenir_combinaison vérifie ainsi une destination même
     * au-delà des premiers résultats.
     *
     * @param array<mixed> $entree
     *
     * @return array<string, mixed>
     */
    public function rechercher(array $entree, ?int $limite): array
    {
        // Mode non strict : le serveur vérifie lui-même l'entrée.
        $zone = Entree::chaine($entree, 'zone');
        $villeDepart = Entree::chaine($entree, 'villeDepart');
        $debut = Entree::date($entree, 'dateDebut');
        $fin = Entree::date($entree, 'dateFin') ?? $debut;
        $temperatureMin = Entree::entier($entree, 'temperatureMin');
        $temperatureMax = Entree::entier($entree, 'temperatureMax');
        $envies = Entree::chaines($entree, 'envies');
        $groupe = ['voyageurs' => Entree::entier($entree, 'voyageurs', 1, 1000), 'accessible' => Entree::booleen($entree, 'accessibleObligatoire') ?? false];

        if (null !== $debut && null !== $fin && $fin < $debut) {
            throw new OutilException('dateFin doit être postérieure à dateDebut.');
        }
        if (null === $debut && (null !== $temperatureMin || null !== $temperatureMax)) {
            throw new OutilException('Donne dateDebut et dateFin pour filtrer par température.');
        }
        // Les nuits du voyage : du départ à la veille du retour, au moins une.
        $nuits = null === $debut || null === $fin ? 0 : max(1, (int) $debut->diff($fin)->days);
        if ($nuits > self::NUITS_MAX) {
            throw new OutilException(\sprintf('Plus de %d nuits entre dateDebut et dateFin : donne les dates du voyage, pas une saison.', self::NUITS_MAX));
        }

        $publiees = $this->destinations->publiees();
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

        $sansDonnees = [];
        $uneCouverte = false;
        foreach ($candidates as $destination) {
            $climat = null === $debut ? [] : self::climatAuxDates($destination, $debut, $fin ?? $debut);
            if (null !== $debut) {
                if (self::couvre($climat, $debut, $fin ?? $debut)) {
                    $uneCouverte = true;
                } else {
                    $sansDonnees[] = $destination;
                }
            }

            $raison = self::raisonClimat($climat, $temperatureMin, $temperatureMax);
            if (null !== $raison) {
                // Pas de logement pour le groupe : aucune autre date n'y changerait rien.
                $resultat['ecartees'][] = ['slug' => $destination->getSlug(), 'raison' => $raison] + (null === $debut || null !== self::raisonDAccueil($logements, $groupe) ? [] : [
                    'climatAuxDates' => $climat,
                    ...$this->ajustements($destination, $logements, $groupe, $debut, $nuits, $temperatureMin, $temperatureMax),
                ]);
                continue;
            }
            $aPartirDe = null === $debut ? null : self::aPartirDeParNuit($logements, $debut, $nuits);

            // Les suivantes sont encore parcourues : leur climat compte pour les données manquantes.
            if (\count($resultat['destinations']) >= $limite) {
                continue;
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
                'enviesCorrespondantes' => $correspondantes,
                'aPartirDeParNuitEuros' => null === $aPartirDe ? null : Montant::nombre($aPartirDe),
            ]];
        }

        // Les envies d'abord : l'agent présente en premier ce qui répond le mieux au client.
        usort($retenues, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $resultat['destinations'] = array_column(\array_slice($retenues, 0, $limite), 1);

        // Rien dans la zone demandée : les destinations du catalogue qui conviennent aux mêmes dates et critères.
        if (null !== $zone && !isset($resultat['remarque']) && [] === $resultat['destinations'] && [] !== $resultat['ecartees']) {
            $ailleurs = $this->rechercher(['zone' => null] + $entree, 3)['destinations'];
            $resultat['autresDestinations'] = array_map(static fn (array $d): array => [
                'slug' => $d['slug'],
                'ville' => $d['ville'],
                'pays' => $d['pays'],
                'enviesCorrespondantes' => $d['enviesCorrespondantes'],
                'aPartirDeParNuitEuros' => $d['aPartirDeParNuitEuros'],
            ], $ailleurs);
        }

        if (null !== $debut && [] !== $sansDonnees && !$uneCouverte) {
            $resultat['donneesManquantes'] = [
                'destinations' => array_map(static fn (Destination $d) => $d->getSlug(), $sansDonnees),
                'consigne' => 'L\'agence n\'a pas encore de données pour ces dates. Dis-le simplement au client, propose-lui d\'autres dates si elles lui conviennent, et précise qu\'un conseiller a été prévenu et complétera sa demande.',
            ];
            $this->signalerAuConseiller($contexte, $sansDonnees, $debut, $fin ?? $debut);
        }

        return $resultat;
    }

    /**
     * Rend la demande prioritaire (US06). Une demande déjà transmise garde son premier motif et son résumé.
     *
     * @param list<Destination> $sansDonnees
     */
    private function signalerAuConseiller(ContexteAgent $contexte, array $sansDonnees, \DateTimeImmutable $debut, \DateTimeImmutable $fin): void
    {
        if (null !== $contexte->demande->getMotifTransmission()) {
            return;
        }

        $contexte->demande->transmettre(MotifTransmission::DonneesManquantes, \sprintf(
            'Aucun climat saisi du %s au %s pour : %s. Saisir la période dans le catalogue, puis compléter la demande.',
            $debut->format('d/m/Y'),
            $fin->format('d/m/Y'),
            implode(', ', array_map(static fn (Destination $d) => (string) $d->getVille(), $sansDonnees)),
        ));
        $this->em->flush();
    }

    /**
     * Les périodes climatiques couvrent-elles chaque jour du séjour ?
     *
     * @param list<array{debut: string, fin: string, temperatureMoyenne: float|null, pluieMm: int|null}> $climat
     */
    private static function couvre(array $climat, \DateTimeImmutable $debut, \DateTimeImmutable $fin): bool
    {
        usort($climat, static fn (array $a, array $b) => $a['debut'] <=> $b['debut']);

        // Dates au format AAAA-MM-JJ : l'ordre des chaînes est celui des dates.
        $jour = $debut->format('Y-m-d');
        foreach ($climat as $periode) {
            if ($periode['debut'] > $jour) {
                return false;
            }
            if ($periode['fin'] >= $jour) {
                $jour = (new \DateTimeImmutable($periode['fin']))->modify('+1 day')->format('Y-m-d');
            }
        }

        return $jour > $fin->format('Y-m-d');
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
     * Chaque période climatique du voyage doit respecter les températures souhaitées : un voyage à cheval sur un
     * mois trop frais est écarté. Sans données de climat aux dates, la température ne peut pas être garantie.
     *
     * @param list<array{debut: string, fin: string, temperatureMoyenne: float|null, pluieMm: int|null}> $climat
     */
    private static function raisonClimat(array $climat, ?int $min, ?int $max): ?string
    {
        if (null === $min && null === $max) {
            return null;
        }
        $temperatures = array_filter(array_column($climat, 'temperatureMoyenne'), static fn ($t) => null !== $t);
        if ([] === $temperatures) {
            return 'Aucune donnée de climat aux dates : la température ne peut pas être vérifiée.';
        }
        if (null !== $min && min($temperatures) < $min) {
            return \sprintf('%s °C en moyenne aux dates, moins que les %d °C souhaités.', min($temperatures), $min);
        }
        if (null !== $max && max($temperatures) > $max) {
            return \sprintf('%s °C en moyenne aux dates, plus que les %d °C souhaités.', max($temperatures), $max);
        }

        return null;
    }

    /**
     * Le prix de nuit le plus bas parmi les logements qui ont un tarif pour chaque nuit, en centimes ; null si
     * aucun logement n'est réservable aux dates.
     *
     * @param list<Prestation> $prestations
     */
    private static function aPartirDeParNuit(array $prestations, \DateTimeImmutable $premiereNuit, int $nuits): ?int
    {
        $prix = [];
        foreach ($prestations as $prestation) {
            if (TypePrestation::Logement !== $prestation->getType()) {
                continue;
            }
            $tarifs = $prestation->tarifsDesNuits($premiereNuit, $nuits);
            if (!\in_array(null, $tarifs, true)) {
                $prix[] = min(array_map(static fn (TarifParPeriode $t): int => (int) $t->getPrixCentimes(), $tarifs));
            }
        }

        return [] === $prix ? null : min($prix);
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
}
