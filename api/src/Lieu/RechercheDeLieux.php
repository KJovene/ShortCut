<?php

namespace App\Lieu;

use Psr\Log\LoggerInterface;
use Symfony\Component\Intl\Countries;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Villes du monde, cherchées par leur nom dans Photon (géocodeur public sur les données OpenStreetMap), en
 * français : « Lisbonne, Lisbonne, Portugal ». Sert aux menus déroulants du backoffice, pour qu'une ville se
 * choisisse au lieu de s'écrire. Le pays est le nom français de son code ISO (symfony/intl), comme dans la liste
 * des pays ; une réponse est gardée un jour en cache. Sans réponse de Photon, la recherche est vide.
 */
final class RechercheDeLieux
{
    public const URL = 'https://photon.komoot.io/api/';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param ?string $codePays limite aux villes d'un pays (code ISO, ex. « FR » pour les villes de départ), sans
     *                          les villages
     *
     * @return list<array{ville: string, region: ?string, pays: string, codePays: string, latitude: float, longitude: float, type: string}>
     */
    public function villes(string $recherche, ?string $codePays = null): array
    {
        $recherche = trim($recherche);
        if (mb_strlen($recherche) < 2) {
            return [];
        }

        $villes = $this->cache->get('lieux_'.md5(mb_strtolower($recherche)), function (ItemInterface $item) use ($recherche): array {
            $item->expiresAfter(86400);

            return $this->chercher($recherche);
        });

        return array_values(array_filter(
            $villes,
            static fn (array $v): bool => null === $codePays || ($v['codePays'] === $codePays && \in_array($v['type'], ['city', 'town'], true)),
        ));
    }

    /**
     * @return list<array{ville: string, region: ?string, pays: string, codePays: string, latitude: float, longitude: float, type: string}>
     */
    private function chercher(string $recherche): array
    {
        try {
            $reponse = $this->httpClient->request('GET', self::URL, [
                'query' => ['q' => $recherche, 'lang' => 'fr', 'limit' => 12, 'layer' => 'city'],
                'timeout' => 5,
            ])->toArray();
        } catch (\Throwable $e) {
            $this->logger->warning('Recherche de lieux indisponible : {erreur}', ['erreur' => $e->getMessage()]);

            return [];
        }

        $villes = [];
        foreach ($reponse['features'] ?? [] as $lieu) {
            $proprietes = $lieu['properties'] ?? [];
            $coordonnees = $lieu['geometry']['coordinates'] ?? null;
            $code = strtoupper((string) ($proprietes['countrycode'] ?? ''));
            if (!isset($proprietes['name']) || !\is_array($coordonnees) || '' === $code) {
                continue;
            }
            $villes[] = [
                'ville' => (string) $proprietes['name'],
                'region' => $proprietes['state'] ?? $proprietes['county'] ?? null,
                'pays' => Countries::exists($code) ? Countries::getName($code, 'fr') : (string) ($proprietes['country'] ?? $code),
                'codePays' => $code,
                'latitude' => round((float) $coordonnees[1], 4),
                'longitude' => round((float) $coordonnees[0], 4),
                'type' => (string) ($proprietes['osm_value'] ?? ''),
            ];
        }

        return $villes;
    }
}
