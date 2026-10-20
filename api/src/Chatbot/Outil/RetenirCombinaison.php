<?php

namespace App\Chatbot\Outil;

use ApiPlatform\Metadata\IriConverterInterface;
use App\Chatbot\ContexteAgent;
use App\Chiffrage\ChiffrageCache;
use App\Chiffrage\Montant;
use App\Entity\Demande;
use App\Entity\Destination;
use App\Entity\Prestation;
use App\Repository\DestinationRepository;
use App\Repository\PrestationRepository;

/**
 * Outil `retenir_combinaison` de l'agent Recherche (ticket #203, docs/outils.md) : la destination, les logements de
 * chaque étape et les activités que l'agent a choisis pour la demande courante.
 *
 * Le serveur ne fait pas confiance au modèle : il refait les trois recherches avec les critères de la fiche demande
 * (zone, dates, températures, voyageurs, âges, accessibilité) et refuse tout choix qu'elles n'auraient pas renvoyé,
 * avec la raison. Il chiffre ensuite les trois niveaux (calculer_budget) : chaque niveau retient sa combinaison de
 * logements et d'activités. La sortie relie chaque prestation à sa fiche et donne les faits qui justifient le choix :
 * températures aux dates, temps de trajet, envies satisfaites.
 */
final class RetenirCombinaison implements Outil
{
    public function __construct(
        private readonly ChercherDestinations $chercherDestinations,
        private readonly ChercherLogements $chercherLogements,
        private readonly ChercherActivites $chercherActivites,
        private readonly CalculerBudget $calculerBudget,
        private readonly ChiffrageCache $cache,
        private readonly DestinationRepository $destinations,
        private readonly PrestationRepository $prestations,
        private readonly IriConverterInterface $iris,
    ) {
    }

    public function nom(): string
    {
        return 'retenir_combinaison';
    }

    public function definition(): array
    {
        $ids = ['type' => 'array', 'items' => ['type' => 'integer']];

        return [
            'name' => $this->nom(),
            'description' => 'Retient pour la demande en cours la destination, les logements de chaque étape et les activités que tu as choisis parmi les résultats des outils de recherche. Le serveur vérifie chaque choix contre la fiche demande, puis chiffre les trois niveaux de budget : chaque niveau retient ses logements et ses activités. Si un choix est refusé, corrige-le avec la raison donnée.',
            // Outil qui écrit (cache du chiffrage) : mode strict, voir docs/outils.md « Schémas ».
            'strict' => true,
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'destination' => ['type' => 'string', 'description' => 'Slug renvoyé par chercher_destinations (ex. « santorin »).'],
                    'etapes' => [
                        'type' => 'array',
                        'description' => 'Une étape pour un séjour, plusieurs pour un circuit, dans l\'ordre du voyage et sans trou entre elles.',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'dateDebut' => ['type' => 'string', 'description' => 'Première nuit de l\'étape, AAAA-MM-JJ.'],
                                'nombreDeNuits' => ['type' => 'integer'],
                                'logementIds' => $ids + ['description' => 'Tous les logements compatibles retenus pour l\'étape, du plus simple au plus confortable : chaque niveau de budget choisit le sien.'],
                            ],
                            'required' => ['dateDebut', 'nombreDeNuits', 'logementIds'],
                            'additionalProperties' => false,
                        ],
                    ],
                    'activitesDemandees' => $ids + ['description' => 'Activités que le client a explicitement demandées (incluses à tous les niveaux).'],
                    'activitesSuggerees' => $ids + ['description' => 'Activités en accord avec ses envies (ajoutées selon le niveau et le budget).'],
                    'pourquoi' => ['type' => 'string', 'description' => 'Pourquoi cette destination et ces prestations répondent au client, en deux ou trois phrases, sans prix.'],
                ],
                'required' => ['destination', 'etapes', 'activitesDemandees', 'activitesSuggerees', 'pourquoi'],
                'additionalProperties' => false,
            ],
        ];
    }

    public function executer(ContexteAgent $contexte, array $entree): array
    {
        $demande = $contexte->demande;
        $pourquoi = Entree::chaine($entree, 'pourquoi') ?? throw new OutilException('Explique dans pourquoi ce qui fait de cette combinaison la bonne réponse au client.');
        $destination = $this->destinations->publieeCorrespondant((string) Entree::chaine($entree, 'destination'))
            ?? throw new OutilException(\sprintf('La destination « %s » n\'existe pas dans le catalogue.', (string) Entree::chaine($entree, 'destination')));
        $etapes = $this->etapes($demande, $entree['etapes'] ?? null);
        $premiereNuit = $etapes[0]['debut'];
        $nuits = array_sum(array_column($etapes, 'nuits'));
        $slug = (string) $destination->getSlug();
        $accessible = $demande->aBesoinDAccessibilite();

        // 1. La destination, avec les critères de la fiche : zone, groupe, températures et tarifs aux dates.
        $recherche = $this->chercherDestinations->rechercher(array_filter([
            'zone' => $demande->getLieuOuZone(),
            'dateDebut' => $premiereNuit->format('Y-m-d'),
            'dateFin' => $premiereNuit->modify(\sprintf('+%d days', $nuits))->format('Y-m-d'),
            'temperatureMin' => $demande->getTemperatureMin(),
            'temperatureMax' => $demande->getTemperatureMax(),
            'envies' => $demande->getEnvies(),
            'villeDepart' => $demande->getVilleDepart(),
            'voyageurs' => $demande->getAdultes() + $demande->getEnfants(),
            'accessibleObligatoire' => $accessible,
        ], static fn ($v): bool => null !== $v), null);
        $resultatDestination = self::parSlug($recherche['destinations'], $slug);
        if (null === $resultatDestination) {
            $ecartee = self::parSlug($recherche['ecartees'], $slug);
            throw new OutilException(\sprintf('%s ne convient pas : %s', $destination->getVille(), $ecartee['raison'] ?? \sprintf('elle n\'est pas dans la zone demandée (« %s »).', $demande->getLieuOuZone())));
        }

        // 2. Les logements de chaque étape, 3. les activités : chaque identifiant doit être dans les résultats.
        $refus = [];
        $retenues = [];
        foreach ($etapes as $index => $etape) {
            $resultat = $this->chercherLogements->executer($contexte, [
                'destination' => $slug,
                'dateDebut' => $etape['debut']->format('Y-m-d'),
                'nombreDeNuits' => $etape['nuits'],
                'voyageurs' => $demande->getAdultes() + $demande->getEnfants(),
                'accessibleObligatoire' => $accessible,
            ]);
            $refus = [...$refus, ...$this->refus($etape['logementIds'], $resultat['logements'], 'logementId', $resultat['ecartes'], $destination, \sprintf('Étape %d', $index + 1))];
            $retenues = [...$retenues, ...$etape['logementIds']];
        }
        $activites = $this->chercherActivites->executer($contexte, [
            'destination' => $slug,
            'date' => $premiereNuit->format('Y-m-d'),
            'adultes' => $demande->getAdultes(),
            'agesEnfants' => $demande->agesDesEnfants(),
            'accessibleObligatoire' => $accessible,
        ]);
        $demandees = Entree::entiers($entree, 'activitesDemandees');
        $suggerees = Entree::entiers($entree, 'activitesSuggerees');
        $refus = [...$refus, ...$this->refus([...$demandees, ...$suggerees], $activites['activites'], 'activiteId', $activites['ecartees'], $destination, 'Activités')];
        if ([] !== $refus) {
            throw new OutilException("Combinaison refusée :\n- ".implode("\n- ", array_unique($refus)));
        }

        // 4. Le chiffrage : chaque niveau choisit ses logements et ses activités parmi ces candidats.
        $chiffrage = $this->calculerBudget->executer($contexte, [
            'destination' => $slug,
            'etapes' => array_map(static fn (array $e): array => ['dateDebut' => $e['debut']->format('Y-m-d'), 'nombreDeNuits' => $e['nuits'], 'logementIds' => $e['logementIds']], $etapes),
            'activitesDemandees' => $demandees,
            'activitesSuggerees' => $suggerees,
        ]);
        if (!\is_string($chiffrage['chiffrageId'] ?? null) || !\is_array($chiffrage['niveaux'] ?? null)) {
            $raison = $chiffrage['nonChiffrable'] ?? null;
            throw new OutilException(\sprintf('Combinaison non chiffrable : %s', \is_array($raison) ? $raison['prestation'].', '.$raison['raison'] : 'aucun niveau calculé.'));
        }

        $fiches = $this->fiches([...$retenues, ...$demandees, ...$suggerees]);
        $combinaison = [
            'destination' => [
                'slug' => $slug,
                'ville' => $destination->getVille(),
                'pays' => $destination->getPays(),
                'fiche' => $this->iris->getIriFromResource($destination),
            ],
            'etapes' => array_map(static fn (array $e): array => [
                'dateDebut' => $e['debut']->format('Y-m-d'),
                'nombreDeNuits' => $e['nuits'],
                'logements' => array_map(static fn (int $id): array => $fiches[$id], $e['logementIds']),
            ], $etapes),
            'justification' => self::justification($demande, $resultatDestination, $pourquoi),
            'niveaux' => array_map(static fn (array $niveau): array => [
                'logements' => array_map(static fn (string $nom): array => self::parNom($fiches, $nom), $niveau['logements']),
                'activites' => array_map(static fn (string $nom): array => self::parNom($fiches, $nom), $niveau['activites']),
                // Seule différence entre deux niveaux aux mêmes prestations : l'estimation du transport.
                'transport' => $niveau['transport'],
                'totalEuros' => $niveau['totalEuros'],
                'parPersonneEuros' => $niveau['parPersonneEuros'],
            ], $chiffrage['niveaux']),
        ];

        // La combinaison rejoint le chiffrage en cache : l'agent de rédaction la retrouve par le même identifiant.
        $this->cache->enregistrer($demande, $chiffrage + ['combinaison' => $combinaison], $chiffrage['chiffrageId']);

        return [
            'chiffrageId' => $chiffrage['chiffrageId'],
            ...$combinaison,
            'budgetClient' => $chiffrage['budgetClient'],
            'budgetInsuffisant' => $chiffrage['budgetInsuffisant'],
            'arbitrages' => array_map(static fn (array $a): array => [
                'libelle' => $a['libelle'],
                'totalEuros' => $a['totalEuros'],
                'tientDansLeBudget' => $a['tientDansLeBudget'],
            ], $chiffrage['arbitrages'] ?? []),
            'avertissement' => CalculerBudget::AVERTISSEMENT,
        ];
    }

    /**
     * Les étapes de l'entrée, contrôlées contre la fiche : elles se suivent sans trou, totalisent le nombre de
     * nuits demandé et commencent à la date demandée (sauf dates flexibles).
     *
     * @return non-empty-list<array{debut: \DateTimeImmutable, nuits: int, logementIds: list<int>}>
     */
    private function etapes(Demande $demande, mixed $etapes): array
    {
        $debutDemande = $demande->getDateDebut()
            ?? throw new OutilException('La fiche demande n\'a pas encore de date de début : la recherche attend que le client ait confirmé ses dates.');
        $nuitsDemandees = $demande->getNombreDeJours()
            ?? (null === $demande->getDateFin() ? null : (int) $debutDemande->diff($demande->getDateFin())->days)
            ?? throw new OutilException('La fiche demande n\'a ni nombre de nuits ni date de fin.');
        if (!\is_array($etapes) || [] === $etapes || !array_is_list($etapes)) {
            throw new OutilException('Donne au moins une étape, avec ses logements.');
        }

        $liste = [];
        $attendue = null;
        foreach ($etapes as $index => $etape) {
            $numero = $index + 1;
            $etape = \is_array($etape) ? $etape : [];
            $debut = Entree::date($etape, 'dateDebut') ?? throw new OutilException(\sprintf('Étape %d : indique dateDebut.', $numero));
            $nuits = Entree::entier($etape, 'nombreDeNuits', 1, 60) ?? throw new OutilException(\sprintf('Étape %d : indique nombreDeNuits.', $numero));
            $logementIds = array_values(array_unique(Entree::entiers($etape, 'logementIds')));
            if ([] === $logementIds) {
                throw new OutilException(\sprintf('Étape %d : donne au moins un logement (logementIds).', $numero));
            }
            if (null !== $attendue && $debut != $attendue) {
                throw new OutilException(\sprintf('Étape %d : elle doit commencer le %s, au lendemain de la dernière nuit de l\'étape précédente.', $numero, $attendue->format('d/m/Y')));
            }
            $liste[] = ['debut' => $debut, 'nuits' => $nuits, 'logementIds' => $logementIds];
            $attendue = $debut->modify(\sprintf('+%d days', $nuits));
        }

        $nuits = array_sum(array_column($liste, 'nuits'));
        if ($nuits !== $nuitsDemandees) {
            throw new OutilException(\sprintf('Les étapes comptent %s, le client en a demandé %d.', Montant::quantite($nuits, 'nuit'), $nuitsDemandees));
        }
        if (!$demande->isDatesFlexibles() && $liste[0]['debut'] != $debutDemande) {
            throw new OutilException(\sprintf('Le client part le %s (dates fixes), pas le %s.', $debutDemande->format('d/m/Y'), $liste[0]['debut']->format('d/m/Y')));
        }

        return $liste;
    }

    /**
     * Les identifiants choisis qui ne sont pas dans les résultats de la recherche, avec la raison de la recherche.
     *
     * @param list<int>                                     $ids
     * @param list<array<string, mixed>>                    $compatibles
     * @param list<array{nom: string|null, raison: string}> $ecartes
     *
     * @return list<string>
     */
    private function refus(array $ids, array $compatibles, string $cle, array $ecartes, Destination $destination, string $ou): array
    {
        $refus = [];
        foreach (array_diff($ids, array_column($compatibles, $cle)) as $id) {
            $prestation = $this->prestations->find($id);
            if (null === $prestation || $prestation->getDestination()?->getId() !== $destination->getId()) {
                $refus[] = \sprintf('%s : %d n\'existe pas à %s, utilise les identifiants renvoyés par les outils de recherche.', $ou, $id, $destination->getVille());
                continue;
            }
            $raison = current(array_filter($ecartes, static fn (array $e): bool => $e['nom'] === $prestation->getNom()));
            $refus[] = \sprintf('%s : %s, %s', $ou, $prestation->getNom(), false === $raison ? 'ne convient pas à la demande.' : lcfirst($raison['raison']));
        }

        return $refus;
    }

    /**
     * Nom, identifiant et fiche (IRI de l'API, consultable par les conseillers) de chaque prestation retenue.
     *
     * @param list<int> $ids
     *
     * @return array<int, array{id: int, nom: string, fiche: string}>
     */
    private function fiches(array $ids): array
    {
        $fiches = [];
        foreach ($this->prestations->findBy(['id' => array_values(array_unique($ids))]) as $prestation) {
            $fiches[(int) $prestation->getId()] = [
                'id' => (int) $prestation->getId(),
                'nom' => (string) $prestation->getNom(),
                'fiche' => $this->iris->getIriFromResource($prestation),
            ];
        }

        return $fiches;
    }

    /**
     * Les faits qui justifient le choix, repris des recherches : jamais inventés par le modèle.
     *
     * @param array<string, mixed> $destination résultat de chercher_destinations pour la destination retenue
     *
     * @return array<string, mixed>
     */
    private static function justification(Demande $demande, array $destination, string $pourquoi): array
    {
        $correspondantes = $destination['enviesCorrespondantes'];

        return [
            'climatAuxDates' => $destination['climatAuxDates'],
            'temperatureSouhaitee' => ['min' => $demande->getTemperatureMin(), 'max' => $demande->getTemperatureMax()],
            'villeDepart' => $demande->getVilleDepart(),
            'tempsDeTrajetMinutes' => $destination['tempsDeTrajetMinutes'],
            'enviesCorrespondantes' => $correspondantes,
            'enviesSansReponse' => array_values(array_diff($demande->getEnvies(), $correspondantes)),
            'pourquoi' => $pourquoi,
        ];
    }

    /**
     * @param list<array<string, mixed>> $liste
     *
     * @return array<string, mixed>|null
     */
    private static function parSlug(array $liste, string $slug): ?array
    {
        return current(array_filter($liste, static fn (array $d): bool => $d['slug'] === $slug)) ?: null;
    }

    /**
     * @param array<int, array{id: int, nom: string, fiche: string}> $fiches
     *
     * @return array{id: int, nom: string, fiche: string}
     */
    private static function parNom(array $fiches, string $nom): array
    {
        return current(array_filter($fiches, static fn (array $f): bool => $f['nom'] === $nom))
            ?: throw new \LogicException(\sprintf('Prestation « %s » chiffrée mais pas retenue.', $nom));
    }
}
