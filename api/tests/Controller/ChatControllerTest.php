<?php

namespace App\Tests\Controller;

use App\Controller\ChatController;
use App\Entity\Demande;
use App\Entity\Utilisateur;
use App\Enum\Langue;
use App\Enum\Role;
use App\Enum\StatutDemande;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Ouverture d'une conversation du chatbot et de sa demande. L'envoi des messages (jeton, limite de débit) est
 * couvert par ChatEnvoiTest.
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

    public function testLeChatbotOuvreUneConversationEtSaDemande(): void
    {
        ['jeton' => $jeton, 'demande' => $demande] = $this->demarrer();

        self::assertSame(64, \strlen($jeton));
        self::assertSame(StatutDemande::Nouvelle, $this->em->find(Demande::class, $demande)?->getStatut());
    }

    public function testLaDemandeDUnClientConnecteLuiEstRattachee(): void
    {
        $client = $this->creerCompte('zoe.fontaine@exemple.test', Role::Client);
        $this->client->loginUser($client);

        ['demande' => $demande] = $this->demarrer();

        self::assertSame($client, $this->em->find(Demande::class, $demande)?->getClient());
    }

    public function testLesPreferencesDuQuestionnaireSontEnregistreesDansLaDemandeDuClient(): void
    {
        $client = $this->creerCompte('zoe.fontaine@exemple.test', Role::Client);
        $this->client->loginUser($client);

        $this->client->request(
            'POST',
            '/api/chat/conversations',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'questionnaire' => ['typeDeVoyage' => 'noces', 'voyageurs' => 'deux', 'budget' => 'de_1500_a_3000'],
            ], \JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(201);
        $demande = $this->em->find(Demande::class, $this->reponse()['demande']);

        self::assertNotNull($demande);
        self::assertSame('noces', $demande->getTypeDeVoyage()?->value);
        self::assertSame(2, $demande->getAdultes());
        self::assertSame(300000, $demande->getBudgetCentimes());
    }

    /** « Plus de 3 000 € » n'est pas une cible (D26) : l'assistant proposera trois fourchettes. */
    public function testPlusDeTroisMilleEurosNeFixePasDeBudget(): void
    {
        $this->client->request(
            'POST',
            '/api/chat/conversations',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{"questionnaire": {"typeDeVoyage": "sejour", "voyageurs": "famille", "budget": "plus_de_3000"}}',
        );
        self::assertResponseStatusCodeSame(201);
        $demande = $this->em->find(Demande::class, $this->reponse()['demande']);

        self::assertSame('sejour', $demande?->getTypeDeVoyage()?->value);
        self::assertNull($demande->getBudgetCentimes());
    }

    /** 3 voyages en cours au plus par client : le 4e attend qu'un autre soit accepté ou perdu. */
    public function testUnClientNOuvrePasUnQuatriemeVoyageEnCours(): void
    {
        $client = $this->creerCompte('zoe.fontaine@exemple.test', Role::Client);
        $this->client->loginUser($client);
        $this->demarrer();
        $this->demarrer();
        $this->demarrer();

        $this->client->request('POST', '/api/chat/conversations');
        self::assertResponseStatusCodeSame(409);
        self::assertSame(ChatController::MESSAGE_VOYAGES_MAX, $this->reponse()['erreur']);

        // Un voyage perdu ne compte plus : la place se libère.
        $this->em->getRepository(Demande::class)->findOneBy(['client' => $client])?->setStatut(StatutDemande::Perdue);
        $this->em->flush();
        $this->demarrer();
    }

    /** Bouton FR | EN du site : la conversation commence dans la langue choisie, en français sinon. */
    public function testLaConversationCommenceDansLaLangueDuSite(): void
    {
        ['demande' => $sansLangue] = $this->demarrer();

        $this->client->request('POST', '/api/chat/conversations', server: ['CONTENT_TYPE' => 'application/json'], content: '{"langue":"en"}');
        self::assertResponseStatusCodeSame(201);
        $enAnglais = $this->reponse()['demande'];

        self::assertSame(Langue::Francais, $this->em->find(Demande::class, $sansLangue)?->getLangue());
        self::assertSame(Langue::Anglais, $this->em->find(Demande::class, $enAnglais)?->getLangue());
    }

    public function testLaDemandeDUnVisiteurOuDUnConseillerResteSansClient(): void
    {
        ['demande' => $anonyme] = $this->demarrer();

        $this->client->loginUser($this->creerCompte('lea.dubois@exemple.test', Role::Conseiller));
        ['demande' => $duConseiller] = $this->demarrer();

        self::assertNull($this->em->find(Demande::class, $anonyme)?->getClient());
        self::assertNull($this->em->find(Demande::class, $duConseiller)?->getClient());
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
