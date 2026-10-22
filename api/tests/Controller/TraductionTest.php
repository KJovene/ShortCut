<?php

namespace App\Tests\Controller;

use App\Entity\PageDuSite;
use App\Entity\Traduction;
use App\Tests\Chatbot\FausseApiClaude;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Site public en anglais : une page lue avec `?langue=en` est traduite par Claude une seule fois, puis servie depuis
 * la base. L'API Claude est remplacée par FausseApiClaude. Nécessite `make db-test`.
 */
final class TraductionTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private FausseApiClaude $claude;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();
        $this->claude = static::getContainer()->get(FausseApiClaude::class);

        $this->em->persist((new PageDuSite())
            ->setSlug('page-test')
            ->setTitre('Notre agence')
            ->setContenu('<p>Bonjour</p>')
            ->setSeoTitre('Agence de voyage')
            ->setPubliee(true));
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testEnFrancaisRienNeChange(): void
    {
        $this->client->request('GET', '/api/pages/page-test', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseIsSuccessful();
        self::assertSame('Notre agence', $this->page()['titre']);
        self::assertSame([], $this->claude->requetes);
    }

    public function testEnAnglaisLaPageEstTraduiteUneSeuleFois(): void
    {
        $anglais = ['Notre agence' => 'Our agency', '<p>Bonjour</p>' => '<p>Hello</p>', 'Agence de voyage' => 'Travel agency'];
        $this->claude->prevoir($this->claude->texteSelon(static fn (array $corps) => json_encode([
            'traductions' => array_map(static fn (string $f) => $anglais[$f], json_decode($corps['messages'][0]['content'], true)),
        ], \JSON_THROW_ON_ERROR)));

        $this->client->request('GET', '/api/pages/page-test?langue=en', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseIsSuccessful();
        $page = $this->page();
        self::assertSame('page-test', $page['slug']);
        self::assertSame('Our agency', $page['titre']);
        self::assertSame('<p>Hello</p>', $page['contenu']);
        self::assertSame('Travel agency', $page['seoTitre']);
        self::assertCount(1, $this->claude->requetes);
        self::assertSame('claude-haiku-5-5', $this->claude->requetes[0]['corps']['model']);

        // Deuxième visite : servie depuis la base, sans appel à Claude.
        $this->client->request('GET', '/api/pages/page-test?langue=en', server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertSame('Our agency', $this->page()['titre']);
        self::assertCount(1, $this->claude->requetes);
    }

    public function testUneTraductionCorrigeeDansLeBackofficeEstServie(): void
    {
        foreach (['Notre agence' => 'About us', '<p>Bonjour</p>' => '<p>Welcome</p>', 'Agence de voyage' => 'Tailor-made travel'] as $francais => $anglais) {
            $this->em->persist(new Traduction($francais, $anglais));
        }
        $this->em->flush();

        $this->client->request('GET', '/api/pages/page-test?langue=en', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertSame('About us', $this->page()['titre']);
        self::assertSame([], $this->claude->requetes);
    }

    public function testSansReponseDeClaudeLaPageResteEnFrancais(): void
    {
        $this->claude->prevoir(FausseApiClaude::texte('pas du JSON'));

        $this->client->request('GET', '/api/pages/page-test?langue=en', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseIsSuccessful();
        self::assertSame('Notre agence', $this->page()['titre']);
    }

    /**
     * @return array<string, mixed>
     */
    private function page(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
