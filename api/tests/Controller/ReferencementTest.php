<?php

namespace App\Tests\Controller;

use App\Entity\Destination;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Plan du site et robots.txt (#219). Nécessite `make db-test`.
 */
final class ReferencementTest extends WebTestCase
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

    public function testLePlanListeLesPagesEtLesSeulesDestinationsPubliees(): void
    {
        $this->creerDestination('ville-publiee-test', true);
        $this->creerDestination('ville-masquee-test', false);

        $this->client->request('GET', '/sitemap.xml');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/xml; charset=UTF-8');
        $urls = $this->urls();
        foreach (['/', '/agence', '/contact', '/types-de-voyages', '/destinations/ville-publiee-test'] as $chemin) {
            self::assertContains('http://localhost:5173'.$chemin, $urls);
        }
        self::assertNotContains('http://localhost:5173/destinations/ville-masquee-test', $urls);
        self::assertNotContains('http://localhost:5173/espace', $urls);
    }

    public function testRobotsIndiqueLePlanEtExclutLesPagesPersonnelles(): void
    {
        $this->client->request('GET', '/robots.txt');

        self::assertResponseIsSuccessful();
        $robots = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Sitemap: http://localhost:5173/sitemap.xml', $robots);
        self::assertStringContainsString('Disallow: /espace', $robots);
    }

    /** @return list<string> */
    private function urls(): array
    {
        $xml = new \SimpleXMLElement((string) $this->client->getResponse()->getContent());
        $xml->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        return array_map('strval', $xml->xpath('//s:loc') ?: []);
    }

    private function creerDestination(string $slug, bool $publiee): void
    {
        $destination = (new Destination())
            ->setSlug($slug)
            ->setVille(ucfirst($slug))
            ->setPays('Testland')
            ->setPubliee($publiee);
        $this->em->persist($destination);
        $this->em->flush();
    }
}
