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

        $this->client->request('GET', '/api/pages/page-test');

        self::assertResponseIsSuccessful();
        self::assertSame([
            'slug' => 'page-test',
            'titre' => 'Page de test',
            'contenu' => '<p>Bonjour</p>',
            'photos' => ['/uploads/pages/agence.jpg'],
            'seoTitre' => 'Titre pour les moteurs',
            'seoDescription' => 'Description pour les moteurs',
        ], json_decode((string) $this->client->getResponse()->getContent(), true));
    }

    public function testUnePageMasqueeOuInconnueRepond404(): void
    {
        $this->creerPage('page-test', publiee: false);

        $this->client->request('GET', '/api/pages/page-test');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/api/pages/page-inconnue');
        self::assertResponseStatusCodeSame(404);
    }

    private function creerPage(string $slug, bool $publiee): void
    {
        $page = (new PageDuSite())
            ->setSlug($slug)
            ->setTitre('Page de test')
            ->setContenu('<p>Bonjour</p>')
            ->setPhotos(['agence.jpg'])
            ->setSeoTitre('Titre pour les moteurs')
            ->setSeoDescription('Description pour les moteurs')
            ->setPubliee($publiee);
        $this->em->persist($page);
        $this->em->flush();
    }
}
