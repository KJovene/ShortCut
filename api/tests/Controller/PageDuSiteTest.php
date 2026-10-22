<?php

namespace App\Tests\Controller;

use App\Entity\PageDuSite;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Contenus des pages du site lus par le site React (#200), sans connexion. Nécessite `make db-test`.
 */
final class PageDuSiteTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testUnVisiteurLitUnePagePubliee(): void
    {
        $this->creerPage('page-test', publiee: true);

        $this->client->request('GET', '/api/pages/page-test', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseIsSuccessful();
        self::assertEquals([
            'slug' => 'page-test',
            'titre' => 'Page de test',
            'contenu' => '<p>Bonjour</p>',
            'photos' => ['/uploads/pages/agence.jpg'],
            'seoTitre' => 'Titre pour les moteurs',
            'seoDescription' => 'Description pour les moteurs',
        ], json_decode((string) $this->client->getResponse()->getContent(), true));
    }

    public function testUnePageSansContenuDonneUnTexteVide(): void
    {
        $this->creerPage('page-test', publiee: true);
        $this->em->getRepository(PageDuSite::class)->findOneBy(['slug' => 'page-test'])?->setContenu(null);
        $this->em->flush();

        $this->client->request('GET', '/api/pages/page-test', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseIsSuccessful();
        self::assertSame('', json_decode((string) $this->client->getResponse()->getContent(), true)['contenu']);
    }

    public function testUnePageMasqueeOuInconnueRepond404(): void
    {
        $this->creerPage('page-test', publiee: false);

        $this->client->request('GET', '/api/pages/page-test', server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/api/pages/page-inconnue');
        self::assertResponseStatusCodeSame(404);
    }

    public function testLeMenuDonneLesPagesPublieesDansLOrdreDuBackoffice(): void
    {
        $this->creerPage('page-c', publiee: true, ordreMenu: 2);
        $this->creerPage('page-a', publiee: true, ordreMenu: 1);
        $this->creerPage('page-hors-menu', publiee: true);
        $this->creerPage('page-masquee', publiee: false, ordreMenu: 1);

        $this->client->request('GET', '/api/pages', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseIsSuccessful();
        $pages = array_values(array_filter(
            json_decode((string) $this->client->getResponse()->getContent(), true),
            static fn (array $page): bool => str_starts_with($page['slug'], 'page-'),
        ));
        self::assertSame([
            ['slug' => 'page-a', 'ordreMenu' => 1],
            ['slug' => 'page-c', 'ordreMenu' => 2],
            // API Platform omet les champs vides : une page hors du menu arrive sans position.
            ['slug' => 'page-hors-menu'],
        ], $pages);
    }

    private function creerPage(string $slug, bool $publiee, ?int $ordreMenu = null): void
    {
        $page = (new PageDuSite())
            ->setSlug($slug)
            ->setTitre('Page de test')
            ->setContenu('<p>Bonjour</p>')
            ->setPhotos(['agence.jpg'])
            ->setSeoTitre('Titre pour les moteurs')
            ->setSeoDescription('Description pour les moteurs')
            ->setPubliee($publiee)
            ->setOrdreMenu($ordreMenu);
        $this->em->persist($page);
        $this->em->flush();
    }
}
