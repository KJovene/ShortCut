<?php

namespace App\Tests\Controller;

use App\Entity\Utilisateur;
use App\Lieu\RechercheDeLieux;
use App\Tests\Lieu\RechercheDeLieuxTest;
use Doctrine\Bundle\FixturesBundle\Loader\SymfonyFixturesLoader;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;

/** Résultats des menus de lieux du backoffice (LieuType), Photon simulé. Nécessite `make db-test`. */
final class LieuxControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();

        /** @var SymfonyFixturesLoader $loader */
        $loader = static::getContainer()->get('doctrine.fixtures.loader');
        (new ORMExecutor($this->em, new ORMPurger($this->em)))->execute($loader->getFixtures());
        $this->em->clear();

        static::getContainer()->set(RechercheDeLieux::class, new RechercheDeLieux(
            new MockHttpClient(static fn () => RechercheDeLieuxTest::photon()),
            new ArrayAdapter(),
            new NullLogger(),
        ));
        $lea = $this->em->getRepository(Utilisateur::class)->findOneBy(['email' => 'lea@shortcut.test']);
        self::assertNotNull($lea);
        $this->client->loginUser($lea);
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testUneVilleDuMondePorteSaRegionSonPaysEtSaPosition(): void
    {
        $resultats = $this->chercher('ville', 'porto');

        self::assertSame('Porto', $resultats[0]['entityId']);
        self::assertSame('Porto, Portugal', $resultats[0]['entityAsString'], 'La région qui répète la ville n\'est pas écrite deux fois.');
        self::assertSame(['Portugal', 41.1502, -8.6103], [$resultats[0]['pays'], $resultats[0]['latitude'], $resultats[0]['longitude']]);
        self::assertSame('Porto-Vecchio, Corse, France', $resultats[1]['entityAsString']);
    }

    public function testLesDepartsConnusPassentAvantLesVillesDeFrance(): void
    {
        // Photon simulé répond la même chose à toute recherche : Porto-Vecchio suit les départs connus.
        self::assertSame(['Paris', 'Porto-Vecchio'], array_column($this->chercher('depart', 'par'), 'entityId'));
        self::assertSame(['Porto-Vecchio'], array_column($this->chercher('depart', 'porto'), 'entityId'));
        self::assertContains('Aéroport de Lisbonne', array_column($this->chercher('transport', 'lisb'), 'entityId'));
    }

    public function testUnAutreMenuNExistePas(): void
    {
        $this->client->request('GET', '/admin/lieux', ['lieux' => 'pays', 'query' => 'por']);
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function chercher(string $lieux, string $texte): array
    {
        $this->client->request('GET', '/admin/lieux', ['lieux' => $lieux, 'query' => $texte]);
        self::assertResponseIsSuccessful();

        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR)['results'];
    }
}
