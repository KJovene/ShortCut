<?php

namespace App\Chatbot\Outil;

use App\Chatbot\ContexteAgent;
use App\Entity\Prestation;
use App\Enum\TypePrestation;
use App\Repository\DestinationRepository;
use App\Repository\PrestationRepository;
use App\Texte\Correspondance;

/**
 * Logements ou activités d'une destination, pour l'agent Conversation (docs/outils.md) : le client demande des
 * recommandations avant sa proposition. Mêmes fiches du backoffice que l'agent Recherche, mais sans dates ni prix :
 * le chatbot ne donne ni disponibilité ni prix avant la proposition. Les fiches renvoyées s'affichent au client en
 * cartes cliquables après le message de l'agent (Suggestions).
 */
final class ConsulterCatalogue implements Outil
{
    private const LIMITE_PAR_DEFAUT = 3;
    private const LIMITE_MAX = 6;

    public function __construct(
        private readonly DestinationRepository $destinations,
        private readonly PrestationRepository $prestations,
    ) {
    }

    public function nom(): string
    {
        return 'consulter_catalogue';
    }

    public function definition(): array
    {
        return [
            'name' => $this->nom(),
            'description' => 'Fiches des logements ou des activités d\'une destination du catalogue, les plus proches des envies du client en premier, sans dates ni prix. Les fiches renvoyées s\'affichent au client en cartes cliquables juste après ton message : ne recopie pas leurs détails.',
            'strict' => false,
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'destination' => ['type' => 'string', 'description' => 'Slug ou ville renvoyé par chercher_destinations (ex. « santorin »).'],
                    'type' => ['type' => 'string', 'enum' => array_map(static fn (TypePrestation $t) => $t->value, TypePrestation::cases())],
                    'envies' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Ce que le client recherche (« piscine », « plongée ») : les fiches qui y répondent viennent en premier.'],
                    'voyageurs' => ['type' => 'integer', 'description' => 'Logements : écarte ceux qui ne peuvent pas accueillir le groupe.'],
                    'accessibleObligatoire' => ['type' => 'boolean'],
                    'limite' => ['type' => 'integer', 'description' => \sprintf('Nombre de fiches (%d par défaut, %d au plus).', self::LIMITE_PAR_DEFAUT, self::LIMITE_MAX)],
                ],
                'required' => ['destination', 'type'],
                'additionalProperties' => false,
            ],
        ];
    }

    public function executer(ContexteAgent $contexte, array $entree): array
    {
        $slug = Entree::chaine($entree, 'destination') ?? throw new OutilException('Indique la destination (slug renvoyé par chercher_destinations).');
        $destination = $this->destinations->publieeCorrespondant($slug)
            ?? throw new OutilException(\sprintf('La destination « %s » n\'existe pas dans le catalogue : cherche d\'abord avec chercher_destinations.', $slug));
        $type = TypePrestation::tryFrom((string) Entree::chaine($entree, 'type')) ?? throw new OutilException('Indique type : « logement » ou « activite ».');
        $envies = Entree::chaines($entree, 'envies');
        $voyageurs = Entree::entier($entree, 'voyageurs', 1, 1000);
        $accessible = Entree::booleen($entree, 'accessibleObligatoire') ?? false;
        $limite = max(1, min(self::LIMITE_MAX, Entree::entier($entree, 'limite') ?? self::LIMITE_PAR_DEFAUT));

        $fiches = [];
        $ecartees = 0;
        foreach ($this->prestations->duCatalogue([$destination], $type) as $prestation) {
            if (null !== self::raison($prestation, $voyageurs, $accessible)) {
                ++$ecartees;
                continue;
            }
            $correspondantes = Correspondance::communs($envies, $prestation->motsCles());
            $fiches[] = [[-\count($correspondantes), $prestation->getNom()], [
                'id' => $prestation->getId(),
                'nom' => $prestation->getNom(),
                'categorie' => $prestation->getCategorie(),
                'description' => $prestation->getDescription(),
                'capacite' => $prestation->getCapacite(),
                'accessible' => $prestation->isAccessible(),
                'equipements' => $prestation->getEquipements(),
                'envies' => $prestation->getEnvies(),
                'enviesCorrespondantes' => $correspondantes,
            ] + (TypePrestation::Logement === $type
                ? ['chambres' => $prestation->getChambres()]
                : ['dureeMinutes' => $prestation->getDureeMinutes(), 'ageMinimum' => $prestation->getAgeMinimum()])];
        }
        usort($fiches, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return [
            'destination' => ['slug' => $destination->getSlug(), 'ville' => $destination->getVille(), 'pays' => $destination->getPays()],
            'type' => $type->value,
            'prestations' => array_column(\array_slice($fiches, 0, $limite), 1),
            'ecartees' => $ecartees,
        ];
    }

    /** Pourquoi la fiche ne convient pas au groupe, ou null : les mêmes règles que chercher_logements et chercher_activites. */
    private static function raison(Prestation $prestation, ?int $voyageurs, bool $accessible): ?string
    {
        if (TypePrestation::Logement === $prestation->getType()) {
            return ChercherLogements::raisonDAccueil($prestation, $voyageurs ?? 1, $accessible);
        }

        return $accessible && !$prestation->isAccessible() ? 'Non accessible.' : null;
    }
}
