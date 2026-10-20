<?php

namespace App\Tests\Controller;

use App\Entity\Demande;
use App\Entity\Utilisateur;
use App\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Profil chatbot de bout en bout : jeton de conversation, cloisonnement des demandes, limite de débit.
 * Nécessite la base de test : `make db-test`.
 */
final class ChatControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Même noyau pour toutes les requêtes du test, pour les garder dans une transaction annulée.
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();

        foreach (['chatbot_messages', 'chatbot_conversations'] as $limiteur) {
            /** @var RateLimiterFactoryInterface $fabrique */
            $fabrique = static::getContainer()->get(\sprintf('limiter.%s', $limiteur));
            $fabrique->create('127.0.0.1')->reset();
        }
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testLeChatbotOuvreUneConversationEtLitSaDemande(): void
    {
        ['jeton' => $jeton, 'demande' => $demande] = $this->demarrer();

        self::assertSame(64, \strlen($jeton));

        $this->client->request('GET', '/api/chat/demandes/'.$demande, server: ['HTTP_X_CONVERSATION_TOKEN' => $jeton]);
        self::assertResponseIsSuccessful();
        self::assertSame('nouvelle', $this->reponse()['statut']);
    }

    public function testLaDemandeDUnClientConnecteLuiEstRattachee(): void
    {
        $client = $this->creerCompte('zoe.fontaine@exemple.test', Role::Client);
        $this->client->loginUser($client);

        ['demande' => $demande] = $this->demarrer();

        self::assertSame($client, $this->em->find(Demande::class, $demande)?->getClient());
    }

    public function testLaDemandeDUnVisiteurOuDUnConseillerResteSansClient(): void
    {
        ['demande' => $anonyme] = $this->demarrer();

        $this->client->loginUser($this->creerCompte('lea.dubois@exemple.test', Role::Conseiller));
        ['demande' => $duConseiller] = $this->demarrer();

        self::assertNull($this->em->find(Demande::class, $anonyme)?->getClient());
        self::assertNull($this->em->find(Demande::class, $duConseiller)?->getClient());
    }

    public function testLeChatbotNePeutPasLireLaDemandeDUnAutreClient(): void
    {
        ['jeton' => $jeton] = $this->demarrer();
        ['demande' => $demandeDUnAutre] = $this->demarrer();

        $this->client->request('GET', '/api/chat/demandes/'.$demandeDUnAutre, server: ['HTTP_X_CONVERSATION_TOKEN' => $jeton]);
        self::assertResponseStatusCodeSame(403);
        self::assertArrayHasKey('erreur', $this->reponse());
    }

    public function testSansJetonValideLeChatbotEstRefuse(): void
    {
        ['demande' => $demande] = $this->demarrer();

        $this->client->request('GET', '/api/chat/demandes/'.$demande);
        self::assertResponseStatusCodeSame(401);

        $this->client->request('GET', '/api/chat/demandes/'.$demande, server: ['HTTP_X_CONVERSATION_TOKEN' => 'jeton-invente']);
        self::assertResponseStatusCodeSame(401);
    }

    public function testAuDelaDeLaLimiteLeChatbotRecoitUne429Claire(): void
    {
        ['jeton' => $jeton, 'demande' => $demande] = $this->demarrer();

        for ($i = 0; $i < 20; ++$i) {
            $this->client->request('GET', '/api/chat/demandes/'.$demande, server: ['HTTP_X_CONVERSATION_TOKEN' => $jeton]);
            self::assertResponseIsSuccessful();
        }

        $this->client->request('GET', '/api/chat/demandes/'.$demande, server: ['HTTP_X_CONVERSATION_TOKEN' => $jeton]);
        self::assertResponseStatusCodeSame(429);
        self::assertResponseHasHeader('Retry-After');
        self::assertStringStartsWith('Vous envoyez trop de messages.', $this->reponse()['erreur']);
    }

    private function creerCompte(string $email, Role $role): Utilisateur
    {
        $utilisateur = (new Utilisateur())->setEmail($email)->setNom('Test')->setRole($role);
        $this->em->persist($utilisateur);
        $this->em->flush();

        return $utilisateur;
    }

    /**
     * @return array{jeton: string, demande: int}
     */
    private function demarrer(): array
    {
        $this->client->request('POST', '/api/chat/conversations');
        self::assertResponseStatusCodeSame(201);

        /* @var array{jeton: string, demande: int} */
        return $this->reponse();
    }

    /**
     * @return array<string, mixed>
     */
    private function reponse(): array
    {
        $donnees = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($donnees);

        return $donnees;
    }
}
