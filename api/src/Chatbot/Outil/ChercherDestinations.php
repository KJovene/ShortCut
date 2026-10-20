<?php

namespace App\Chatbot\Outil;

use App\Chatbot\ContexteAgent;
use App\Chiffrage\Montant;
use App\Entity\Destination;
use App\Entity\PeriodeClimatique;
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

        $prestations = [];
        foreach ($this->prestations->duCatalogue($candidates) as $prestation) {
            $prestations[$prestation->getDestination()?->getId()][] = $prestation;
        }

        $retenues = [];
        foreach ($candidates as $destination) {
            $prestationsDeLaDestination = $prestations[$destination->getId()] ?? [];
            $climat = null === $debut ? [] : self::climatAuxDates($destination, $debut, $debut->modify(\sprintf('+%d days', $nuits)));
            $logements = self::logementsPourLeGroupe($prestationsDeLaDestination, $groupe);
            $raison = null === $debut ? self::raisonDAccueil($logements, $groupe) : self::raison($destination, $logements, $groupe, $debut, $nuits, $temperatureMin, $temperatureMax);
            if (null !== $raison) {
                // Pas de logement pour le groupe : aucune autre date n'y changerait rien.
                $resultat['ecartees'][] = ['slug' => $destination->getSlug(), 'raison' => $raison] + (null === $debut || null !== self::raisonDAccueil($logements, $groupe) ? [] : [
                    'climatAuxDates' => $climat,
                    ...$this->ajustements($destination, $logements, $groupe, $debut, $nuits, $temperatureMin, $temperatureMax),
                ]);
                continue;
            }
            $aPartirDe = null === $debut ? null : self::aPartirDeParNuit($logements, $debut, $nuits);

            $enviesDeLaDestination = array_merge(...array_map(static fn (Prestation $p): array => $p->motsCles(), $prestationsDeLaDestination));
            $correspondantes = Correspondance::communs($envies, array_values(array_unique($enviesDeLaDestination)));
            $retenues[] = [[-\count($correspondantes), $destination->getVille()], [
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

        return $resultat;
    }

    /**
     * Les logements de la destination qui peuvent accueillir le groupe (mêmes règles que chercher_logements) ; tous
     * si le nombre de voyageurs n'est pas donné.
     *
     * @param list<Prestation>                             $prestations
     * @param array{voyageurs: int|null, accessible: bool} $groupe
     *
     * @return list<Prestation>
     */
    private static function logementsPourLeGroupe(array $prestations, array $groupe): array
    {
        return array_values(array_filter($prestations, static fn (Prestation $p): bool => TypePrestation::Logement === $p->getType()
            && (null === $groupe['voyageurs'] || null === ChercherLogements::raisonDAccueil($p, $groupe['voyageurs'], $groupe['accessible']))));
    }

    /**
     * @param list<Prestation>                             $logements logements pour le groupe
     * @param array{voyageurs: int|null, accessible: bool} $groupe
     */
    private static function raisonDAccueil(array $logements, array $groupe): ?string
    {
        if (null === $groupe['voyageurs'] || [] !== $logements) {
            return null;
        }

        return \sprintf(
            'Aucun logement %spour %s : proposer d\'autres destinations ou passer la main au conseiller.',
            $groupe['accessible'] ? 'accessible ' : '',
            Montant::quantite($groupe['voyageurs'], 'voyageur'),
        );
    }

    /**
     * Pourquoi la destination ne convient pas à ces dates, ou null si elle convient : un logement pour le groupe,
     * les températures sur chaque mois du voyage, puis un logement avec un tarif pour chaque nuit.
     *
     * @param list<Prestation>                             $logements logements pour le groupe
     * @param array{voyageurs: int|null, accessible: bool} $groupe
     */
    private static function raison(Destination $destination, array $logements, array $groupe, \DateTimeImmutable $debut, int $nuits, ?int $temperatureMin, ?int $temperatureMax): ?string
    {
        $fin = $debut->modify(\sprintf('+%d days', $nuits));
        $raison = self::raisonDAccueil($logements, $groupe)
            ?? self::raisonClimat(self::climatAuxDates($destination, $debut, $fin), $temperatureMin, $temperatureMax);
        if (null === $raison && null === self::aPartirDeParNuit($logements, $debut, $nuits)) {
            $raison = \sprintf(
                'Aucun logement n\'a de tarif pour chaque nuit du %s au %s : proposer d\'autres dates ou passer la main au conseiller.',
                $debut->format('d/m/Y'),
                $fin->format('d/m/Y'),
            );
        }

        return $raison;
    }

    /**
     * Ce qui rendrait la destination possible, calculé sur les données saisies (ticket #204) : les dates les plus
     * proches, avant et après, où le même voyage convient (dans les six mois, jamais avant aujourd'hui), et la durée
     * la plus longue possible aux dates demandées. L'agent les propose au client ; il n'en invente aucune.
     *
     * @param list<Prestation>                             $logements logements pour le groupe
     * @param array{voyageurs: int|null, accessible: bool} $groupe
     *
     * @return array{datesPossibles: list<array{dateDebut: string, dateFin: string}>, nuitsPossibles?: int}
     */
    private function ajustements(Destination $destination, array $logements, array $groupe, \DateTimeImmutable $debut, int $nuits, ?int $temperatureMin, ?int $temperatureMax): array
    {
        $aujourdhui = $this->horloge->now()->setTime(0, 0);
        $possibles = ['avant' => null, 'apres' => null];
        for ($ecart = 1; $ecart <= self::FENETRE_JOURS && \in_array(null, $possibles, true); ++$ecart) {
            foreach (['apres' => $ecart, 'avant' => -$ecart] as $sens => $decalage) {
                $autre = $debut->modify(\sprintf('%+d days', $decalage));
                if (null === $possibles[$sens] && $autre >= $aujourdhui
                    && null === self::raison($destination, $logements, $groupe, $autre, $nuits, $temperatureMin, $temperatureMax)) {
                    $possibles[$sens] = $autre;
                }
            }
        }

        $ajustements = ['datesPossibles' => array_values(array_map(
            static fn (\DateTimeImmutable $d): array => ['dateDebut' => $d->format('Y-m-d'), 'dateFin' => $d->modify(\sprintf('+%d days', $nuits))->format('Y-m-d')],
            array_filter([$possibles['avant'], $possibles['apres']]),
        ))];
        for ($moins = $nuits - 1; $moins >= 1; --$moins) {
            if (null === self::raison($destination, $logements, $groupe, $debut, $moins, $temperatureMin, $temperatureMax)) {
                $ajustements['nuitsPossibles'] = $moins;
                break;
            }
        }

        return $ajustements;
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
