<?php

namespace App\Chatbot\Outil;

use App\Chatbot\ContexteAgent;
use App\Chiffrage\Montant;
use App\Entity\Destination;
use App\Entity\Prestation;
use App\Entity\TarifParPeriode;
use App\Enum\TypePrestation;
use App\Repository\DestinationRepository;
use App\Repository\PrestationRepository;
use App\Texte\Correspondance;

/**
 * Logements d'une destination compatibles avec un séjour (docs/outils.md, ticket #202) : capacité, accessibilité,
 * catégorie, séjour minimum et un tarif pour chaque nuit. Les logements écartés sont renvoyés avec leur raison,
 * pour que l'agent puisse l'expliquer ou proposer d'autres dates.
 */
final class ChercherLogements implements Outil
{
    public const NOM = 'chercher_logements';

    /**
     * Au-delà de 4 chambres d'un même type, il faut un tarif de groupe : l'agent cherche un logement pour groupes
     * ou passe la main au conseiller. Un logement de plusieurs chambres (riad, villa, bloc) est loué entier, une
     * seule fois.
     */
    public const UNITES_MAX = 4;

    public function __construct(
        private readonly DestinationRepository $destinations,
        private readonly PrestationRepository $prestations,
    ) {
    }

    public function nom(): string
    {
        return self::NOM;
    }

    public function definition(): array
    {
        return [
            'name' => $this->nom(),
            'description' => 'Liste les logements compatibles d\'une destination du catalogue pour un séjour donné, avec le prix de chaque nuit. Utilise l\'identifiant renvoyé (logementId) pour calculer le budget. Les prix sont des prix d\'achat, hors marge : ne les annonce jamais au client, seuls les montants de calculer_budget le sont.',
            'strict' => false,
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'destination' => ['type' => 'string', 'description' => 'Slug renvoyé par chercher_destinations, en minuscules (ex. « santorin », « costa-rica »).'],
                    'dateDebut' => ['type' => 'string', 'description' => 'Première nuit, AAAA-MM-JJ.'],
                    'nombreDeNuits' => ['type' => 'integer'],
                    'voyageurs' => ['type' => 'integer', 'description' => 'Adultes et enfants.'],
                    'accessibleObligatoire' => ['type' => 'boolean', 'description' => 'true si un voyageur a besoin d\'un logement accessible.'],
                    'envies' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Les logements qui y répondent le mieux sont listés en premier.'],
                    'categorie' => ['type' => 'string', 'description' => 'Ex. « Villa », « Hôtel 4 étoiles » ; omettre pour toutes.'],
                ],
                'required' => ['destination', 'dateDebut', 'nombreDeNuits', 'voyageurs', 'accessibleObligatoire'],
                'additionalProperties' => false,
            ],
        ];
    }

    public function executer(ContexteAgent $contexte, array $entree): array
    {
        $destination = $this->destination(Entree::chaine($entree, 'destination'));
        $premiereNuit = Entree::date($entree, 'dateDebut') ?? throw new OutilException('Indique dateDebut, la première nuit (AAAA-MM-JJ).');
        $nuits = Entree::entier($entree, 'nombreDeNuits', 1, 60) ?? throw new OutilException('Indique nombreDeNuits, entre 1 et 60.');
        $voyageurs = Entree::entier($entree, 'voyageurs', 1, 1000) ?? throw new OutilException('Indique voyageurs, le nombre d\'adultes et d\'enfants.');
        $accessible = Entree::booleen($entree, 'accessibleObligatoire') ?? throw new OutilException('Indique accessibleObligatoire (true ou false).');
        $envies = Entree::chaines($entree, 'envies');
        $categorie = Entree::chaine($entree, 'categorie');

        $logements = [];
        $ecartes = [];
        foreach ($this->prestations->duCatalogue([$destination], TypePrestation::Logement) as $logement) {
            if (null !== $categorie && !Correspondance::correspondent($categorie, (string) $logement->getCategorie())) {
                continue;
            }

            $unites = self::unites($logement, $voyageurs);
            $tarifs = $logement->tarifsDesNuits($premiereNuit, $nuits);
            $raison = self::raison($logement, $voyageurs, $accessible, $premiereNuit, $nuits, $tarifs);
            if (null !== $raison) {
                $ecartes[] = ['nom' => $logement->getNom(), 'raison' => $raison];
                continue;
            }

            $tarifs = array_values(array_filter($tarifs)); // aucune nuit sans tarif, vérifié par raison()
            $correspondantes = Correspondance::communs($envies, $logement->motsCles());
            $cout = $unites * array_sum(array_map(static fn (TarifParPeriode $t): int => (int) $t->getPrixCentimes(), $tarifs));
            $logements[] = [[-\count($correspondantes), $cout, $logement->getNom()], [
                'logementId' => $logement->getId(),
                'nom' => $logement->getNom(),
                'prestataire' => $logement->getPrestataire()?->getNom(),
                'categorie' => $logement->getCategorie(),
                'description' => $logement->getDescription(),
                'capacite' => $logement->getCapacite(),
                'chambres' => $logement->getChambres(),
                'unitesNecessaires' => $unites,
                'equipements' => $logement->getEquipements(),
                'accessible' => $logement->isAccessible(),
                'envies' => $logement->getEnvies(),
                'enviesCorrespondantes' => $correspondantes,
                'photos' => $logement->getPhotos(),
                'sejourMinimumNuits' => $tarifs[0]->getSejourMinimumNuits(),
                'tarifs' => self::tarifsDuSejour($premiereNuit, $tarifs),
            ]];
        }

        // Les envies d'abord, puis le moins cher pour le séjour.
        usort($logements, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return ['logements' => array_column($logements, 1), 'ecartes' => $ecartes];
    }

    private function destination(?string $slug): Destination
    {
        if (null === $slug) {
            throw new OutilException('Indique la destination (slug renvoyé par chercher_destinations).');
        }

        return $this->destinations->publieeCorrespondant($slug)
            ?? throw new OutilException(\sprintf('La destination « %s » n\'existe pas dans le catalogue.', $slug));
    }

    /**
     * Pourquoi le logement ne convient pas, ou null s'il convient.
     *
     * @param list<TarifParPeriode|null> $tarifs
     */
    private static function raison(Prestation $logement, int $voyageurs, bool $accessible, \DateTimeImmutable $premiereNuit, int $nuits, array $tarifs): ?string
    {
        $raison = self::raisonDAccueil($logement, $voyageurs, $accessible);
        if (null !== $raison) {
            return $raison;
        }

        $sansTarif = array_search(null, $tarifs, true);
        if (false !== $sansTarif) {
            return \sprintf('Aucun tarif saisi pour la nuit du %s.', $premiereNuit->modify(\sprintf('+%d days', $sansTarif))->format('d/m/Y'));
        }

        // Le séjour minimum est celui de la période de la première nuit, comme pour le chiffrage.
        $minimum = $tarifs[0]?->getSejourMinimumNuits();
        if (null !== $minimum && $minimum > $nuits) {
            return \sprintf('Séjour minimum de %s pour un séjour de %s.', Montant::quantite($minimum, 'nuit'), Montant::quantite($nuits, 'nuit'));
        }

        return null;
    }

    /**
     * Pourquoi le logement ne peut pas accueillir le groupe, quelles que soient les dates : accessibilité, puis
     * capacité. Partagé avec chercher_destinations, qui écarte une destination sans logement pour le groupe.
     */
    public static function raisonDAccueil(Prestation $logement, int $voyageurs, bool $accessible): ?string
    {
        if ($accessible && !$logement->isAccessible()) {
            return 'Non accessible : un voyageur a besoin d\'un logement accessible.';
        }

        $unites = self::unites($logement, $voyageurs);
        $chambres = $logement->getChambres() ?? 1;
        if ($unites > 1 && $chambres > 1) {
            return \sprintf(
                'Capacité de %s pour %s : ce logement de %s se loue entier, une seule fois.',
                Montant::quantite($logement->getCapacite(), 'personne'),
                Montant::quantite($voyageurs, 'voyageur'),
                Montant::quantite($chambres, 'chambre'),
            );
        }
        if ($unites > self::UNITES_MAX) {
            return \sprintf(
                'Capacité de %s : il faudrait %s, au-delà de %d il faut un tarif de groupe.',
                Montant::quantite($logement->getCapacite(), 'personne'),
                Montant::quantite($unites, 'chambre'),
                self::UNITES_MAX,
            );
        }

        return null;
    }

    /** Chambres ou unités nécessaires : ⌈ voyageurs ÷ capacité ⌉, comme pour le chiffrage. */
    private static function unites(Prestation $logement, int $voyageurs): int
    {
        return (int) ceil($voyageurs / max(1, $logement->getCapacite()));
    }

    /**
     * Les nuits regroupées par prix : [{du: 2027-06-28, au: 2027-07-01, nuits: 3, prixParNuitEuros: 420}, …],
     * « au » étant le lendemain de la dernière nuit.
     *
     * @param list<TarifParPeriode> $tarifs
     *
     * @return list<array{du: string, au: string, nuits: int, prixParNuitEuros: int|float}>
     */
    private static function tarifsDuSejour(\DateTimeImmutable $premiereNuit, array $tarifs): array
    {
        $tranches = [];
        foreach ($tarifs as $index => $tarif) {
            $prix = (int) $tarif->getPrixCentimes();
            $lendemain = $premiereNuit->modify(\sprintf('+%d days', $index + 1))->format('Y-m-d');
            $derniere = array_key_last($tranches);
            if (null !== $derniere && $tranches[$derniere]['centimes'] === $prix) {
                $tranches[$derniere]['au'] = $lendemain;
                ++$tranches[$derniere]['nuits'];
                continue;
            }
            $tranches[] = ['du' => $premiereNuit->modify(\sprintf('+%d days', $index))->format('Y-m-d'), 'au' => $lendemain, 'nuits' => 1, 'centimes' => $prix];
        }

        return array_map(static fn (array $t): array => [
            'du' => $t['du'],
            'au' => $t['au'],
            'nuits' => $t['nuits'],
            'prixParNuitEuros' => Montant::nombre($t['centimes']),
        ], $tranches);
    }
}
