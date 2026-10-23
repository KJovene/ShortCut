<?php

namespace App\Tests\Lieu;

use App\Lieu\RechercheDeLieux;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class RechercheDeLieuxTest extends TestCase
{
    /** Réponse de Photon pour « porto » : une ville du Portugal, un village de France. */
    public static function photon(): JsonMockResponse
    {
        return new JsonMockResponse(['features' => [
            ['properties' => ['name' => 'Porto', 'state' => 'Porto', 'country' => 'Portugal', 'countrycode' => 'PT', 'osm_value' => 'city'], 'geometry' => ['coordinates' => [-8.61031, 41.15018]]],
            ['properties' => ['name' => 'Porto-Vecchio', 'state' => 'Corse', 'country' => 'France', 'countrycode' => 'FR', 'osm_value' => 'town'], 'geometry' => ['coordinates' => [9.2794, 41.5911]]],
            ['properties' => ['name' => 'Portout', 'county' => 'Ain', 'country' => 'France', 'countrycode' => 'FR', 'osm_value' => 'village'], 'geometry' => ['coordinates' => [5.3, 46.1]]],
        ]]);
    }

    public function testLesVillesArriventEnFrancaisAvecLeurRegionEtLeurPosition(): void
    {
        $requetes = [];
        $client = new MockHttpClient(static function (string $methode, string $url) use (&$requetes): JsonMockResponse {
            $requetes[] = $url;

            return self::photon();
        });
        $recherche = new RechercheDeLieux($client, new ArrayAdapter(), new NullLogger());

        $villes = $recherche->villes('porto');
        self::assertSame(['ville' => 'Porto', 'region' => 'Porto', 'pays' => 'Portugal', 'codePays' => 'PT', 'latitude' => 41.1502, 'longitude' => -8.6103, 'type' => 'city'], $villes[0]);
        self::assertSame('Ain', $villes[2]['region'], 'Sans région, le département.');
        self::assertStringContainsString('lang=fr', $requetes[0]);

        // Les villes de départ : en France, sans les villages ; la même recherche sort du cache.
        self::assertSame(['Porto-Vecchio'], array_column($recherche->villes('Porto ', 'FR'), 'ville'));
        self::assertCount(1, $requetes);
    }

    public function testSansReponseDePhotonLaRechercheEstVide(): void
    {
        $recherche = new RechercheDeLieux(new MockHttpClient(new MockResponse('', ['http_code' => 503])), new ArrayAdapter(), new NullLogger());

        self::assertSame([], $recherche->villes('porto'));
        self::assertSame([], $recherche->villes('p'), 'Moins de deux lettres : pas de recherche.');
    }
}
