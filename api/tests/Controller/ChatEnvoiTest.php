<?php

namespace App\Tests\Controller;

use App\Chatbot\AgentConversation;
use App\Chatbot\AgentRecherche;
use App\Entity\Conversation;
use App\Entity\JournalIA;
use App\Enum\AgentIA;
use App\Tests\Chatbot\FausseApiClaude;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * POST /api/chat : réponse diffusée en Server-Sent Events, boucle des outils côté serveur, clé API et limite
 * de débit. L'API Claude est remplacée par FausseApiClaude. Nécessite la base de test : `make db-test`.
 */
final class ChatEnvoiTest extends WebTestCase
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

    public function testUneConversationDeDixEchangesFonctionneDeBoutEnBout(): void
    {
        $jeton = $this->demarrer();

        for ($i = 1; $i <= 10; ++$i) {
            if (2 === $i) {
                // Le client donne des exigences : Claude les enregistre, puis répond.
                $this->claude->prevoir(
                    FausseApiClaude::appelOutil('enregistrer_exigences', ['lieuOuZone' => 'Lisbonne', 'adultes' => 2, 'budgetEuros' => 1300, 'recapitulatifConfirme' => false]),
                    FausseApiClaude::texte('Lisbonne à deux, très bon choix ! À quelles dates ?'),
                );
            } else {
                $this->claude->prevoir(FausseApiClaude::texte(\sprintf('Réponse n° %d de l’assistant.', $i)));
            }

            $evenements = $this->envoyer($jeton, \sprintf('Message n° %d du client', $i));

            self::assertResponseIsSuccessful();
            self::assertStringStartsWith('text/event-stream', (string) $this->client->getResponse()->headers->get('Content-Type'));
            self::assertSame('fin', array_key_last($this->parType($evenements)));
            $attendu = 2 === $i ? 'Lisbonne à deux, très bon choix ! À quelles dates ?' : \sprintf('Réponse n° %d de l’assistant.', $i);
            self::assertSame($attendu, $this->texte($evenements));
            if (2 === $i) {
                self::assertContains(['outil', ['nom' => 'enregistrer_exigences']], $evenements);
                $demande = $this->parType($evenements)['demande'];
                self::assertSame('Lisbonne', $demande['fiche']['lieuOuZone']);
                self::assertSame(1300, $demande['fiche']['budgetEuros']);
                self::assertFalse($demande['recapitulatifConfirme']);
            }
        }

        // 11 appels à Claude : un par message, plus un après l'appel d'outil.
        self::assertCount(11, $this->claude->requetes);
        $premiere = $this->claude->requetes[0]['corps'];
        self::assertSame(AgentConversation::MODELE, $premiere['model']);
        self::assertTrue($premiere['stream']);
        self::assertSame(['enregistrer_exigences', 'chercher_destinations', 'transmettre_au_conseiller'], array_column($premiere['tools'], 'name'));
        self::assertStringNotContainsString('{{date_du_jour}}', $premiere['system'][0]['text']);
        self::assertStringNotContainsString('<!--', $premiere['system'][0]['text']);

        // L'historique ne fait que s'allonger : chaque requête reprend la précédente à l'identique.
        for ($n = 1; $n < 11; ++$n) {
            $precedents = $this->claude->requetes[$n - 1]['corps']['messages'];
            $suivants = $this->claude->requetes[$n]['corps']['messages'];
            self::assertSame($precedents, \array_slice($suivants, 0, \count($precedents)), \sprintf('Requête %d', $n + 1));
        }

        // Le résultat de l'outil est renvoyé à Claude, la réflexion aussi (avec sa signature).
        $apresOutil = $this->claude->requetes[2]['corps']['messages'];
        $resultat = end($apresOutil)['content'][0];
        self::assertSame('tool_result', $resultat['type']);
        self::assertFalse($resultat['is_error']);
        self::assertSame('Lisbonne', json_decode($resultat['content'], true)['fiche']['lieuOuZone']);
        self::assertSame('thinking', $this->claude->requetes[1]['corps']['messages'][1]['content'][0]['type']);
        self::assertStringStartsWith('signature-', $this->claude->requetes[1]['corps']['messages'][1]['content'][0]['signature']);

        $conversation = $this->conversation($jeton);
        self::assertCount(22, $conversation->getHistorique());
        self::assertCount(20, $conversation->getMessages());
        self::assertSame(['client', 'assistant'], array_column(\array_slice($conversation->getMessages(), 0, 2), 'role'));
        $demande = $conversation->getDemande();
        self::assertNotNull($demande);
        self::assertSame('Lisbonne', $demande->getLieuOuZone());
        self::assertSame(130000, $demande->getBudgetCentimes());
        self::assertCount(11, $this->em->getRepository(JournalIA::class)->findBy(['demande' => $demande]));
    }

    public function testLaCleApiNEstEnvoyeeQuALApiClaude(): void
    {
        $jeton = $this->demarrer();
        $this->claude->prevoir(FausseApiClaude::texte('Bonjour !'));

        $this->envoyer($jeton, 'Bonjour');

        $cle = (string) $_SERVER['ANTHROPIC_API_KEY'];
        self::assertNotSame('', $cle);
        self::assertSame('https://api.anthropic.com/v1/messages', $this->claude->requetes[0]['url']);
        self::assertSame($cle, $this->claude->requetes[0]['cle']);
        self::assertStringNotContainsString($cle, (string) $this->client->getInternalResponse()->getContent());
    }

    public function testLaConfirmationDuRecapitulatifEnchaineSurLAgentRecherche(): void
    {
        $jeton = $this->demarrer();
        $this->claude->prevoir(
            FausseApiClaude::appelOutil('enregistrer_exigences', ['recapitulatifConfirme' => true]),
            FausseApiClaude::texte('Merci, votre demande est complète.'),
            FausseApiClaude::texte('Je vous propose Lisbonne.'),
        );

        $evenements = $this->envoyer($jeton, 'Oui, c’est bon');

        self::assertTrue($this->parType($evenements)['demande']['recapitulatifConfirme']);
        self::assertSame("Merci, votre demande est complète.\n\nJe vous propose Lisbonne.", $this->texte($evenements));
        self::assertSame('fin', array_key_last($this->parType($evenements)));

        // Le 3e appel est celui de l'agent Recherche : Sonnet 5.5, ses outils, la fiche dans son premier message.
        $recherche = $this->claude->requetes[2]['corps'];
        self::assertSame(AgentRecherche::MODELE, $recherche['model']);
        self::assertSame(['chercher_destinations', 'chercher_logements', 'chercher_activites', 'retenir_combinaison', 'transmettre_au_conseiller'], array_column($recherche['tools'], 'name'));
        self::assertCount(1, $recherche['messages']);
        $premier = $recherche['messages'][0]['content'][0]['text'];
        self::assertStringStartsWith("Le client vient de confirmer sa demande.\n\nFiche demande :\n{", $premier);
        self::assertStringEndsWith("Messages du client, dans l'ordre :\n- Oui, c’est bon", $premier);

        $conversation = $this->conversation($jeton);
        self::assertNotNull($conversation->getFinLe());
        self::assertSame(['Oui, c’est bon', 'Merci, votre demande est complète.', 'Je vous propose Lisbonne.'], array_column($conversation->getMessages(), 'contenu'));
        // L'agent Conversation saura, au message suivant, ce que l'agent Recherche a proposé.
        $historique = $conversation->getHistorique();
        $derniere = end($historique);
        self::assertInstanceOf(\stdClass::class, $derniere);
        self::assertSame('assistant', $derniere->role);
        self::assertSame("\n\nJe vous propose Lisbonne.", end($derniere->content)->text);
        $journal = $this->em->getRepository(JournalIA::class)->findBy(['demande' => $conversation->getDemande()], ['id' => 'ASC']);
        self::assertSame([AgentIA::Conversation, AgentIA::Conversation, AgentIA::Recherche], array_map(static fn (JournalIA $j) => $j->getAgent(), $journal));
    }

    public function testUneErreurDeLApiEstAnnonceeSansDetailTechnique(): void
    {
        $jeton = $this->demarrer();
        $this->claude->prevoir(FausseApiClaude::erreur(500, 'Détail interne de l’API'));

        $evenements = $this->envoyer($jeton, 'Bonjour');

        $parType = $this->parType($evenements);
        self::assertStringStartsWith('L\'assistant est momentanément indisponible.', $parType['erreur']['erreur']);
        self::assertArrayHasKey('fin', $parType);
        self::assertStringNotContainsString('Détail interne', (string) $this->client->getInternalResponse()->getContent());

        // Le message du client est gardé : la conversation peut reprendre.
        $this->claude->prevoir(FausseApiClaude::texte('Me revoilà.'));
        self::assertSame('Me revoilà.', $this->texte($this->envoyer($jeton, 'Vous êtes là ?')));
    }

    public function testUneEntreeRefuseeParUnOutilEstRenvoyeeAuModele(): void
    {
        $jeton = $this->demarrer();
        $this->claude->prevoir(
            FausseApiClaude::appelOutil('enregistrer_exigences', ['dateDebut' => '14/05/2027', 'recapitulatifConfirme' => false]),
            FausseApiClaude::texte('Pouvez-vous me redonner la date ?'),
        );

        $this->envoyer($jeton, 'Le 14 mai');

        $messages = $this->claude->requetes[1]['corps']['messages'];
        $resultat = end($messages)['content'][0];
        self::assertTrue($resultat['is_error']);
        self::assertStringContainsString('AAAA-MM-JJ', json_decode($resultat['content'], true)['erreur']);
        self::assertNull($this->conversation($jeton)->getDemande()?->getDateDebut());
    }

    public function testLeClientPeutDemanderUnConseiller(): void
    {
        $jeton = $this->demarrer();
        $this->claude->prevoir(
            FausseApiClaude::appelOutil('transmettre_au_conseiller', ['motif' => 'demande_du_client', 'resume' => 'Le client veut parler à quelqu’un.']),
            FausseApiClaude::texte('Un conseiller va vous recontacter.'),
        );

        $this->envoyer($jeton, 'Je veux parler à un conseiller');

        $conversation = $this->conversation($jeton);
        self::assertTrue($conversation->isRepriseParConseiller());
        self::assertTrue($conversation->getDemande()?->isPrioritaire());
    }

    public function testUnMessageVideOuTropLongEstRefuse(): void
    {
        $jeton = $this->demarrer();

        $this->envoyer($jeton, '   ');
        self::assertResponseStatusCodeSame(400);

        $this->envoyer($jeton, str_repeat('a', 2001));
        self::assertResponseStatusCodeSame(400);

        self::assertSame([], $this->claude->requetes);
    }

    public function testSansJetonLEnvoiEstRefuse(): void
    {
        $this->client->request('POST', '/api/chat', content: '{"message": "Bonjour"}');
        self::assertResponseStatusCodeSame(401);

        $this->client->request('POST', '/api/chat', server: ['HTTP_X_CONVERSATION_TOKEN' => 'jeton-invente'], content: '{"message": "Bonjour"}');
        self::assertResponseStatusCodeSame(401);

        self::assertSame([], $this->claude->requetes);
    }

    public function testLaLimiteDeDebitSAppliqueAuxMessages(): void
    {
        $jeton = $this->demarrer();

        for ($i = 0; $i < 20; ++$i) {
            $this->claude->prevoir(FausseApiClaude::texte('D’accord.'));
            $this->envoyer($jeton, 'Message');
            self::assertResponseIsSuccessful();
        }

        $this->envoyer($jeton, 'Un message de trop');
        self::assertResponseStatusCodeSame(429);
        self::assertResponseHasHeader('Retry-After');
        self::assertCount(20, $this->claude->requetes);
    }

    private function demarrer(): string
    {
        $this->client->request('POST', '/api/chat/conversations');
        self::assertResponseStatusCodeSame(201);

        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR)['jeton'];
    }

    /**
     * @return list<array{0: string, 1: mixed}> événements SSE reçus : [type, données décodées]
     */
    private function envoyer(string $jeton, string $message): array
    {
        $this->client->request(
            'POST',
            '/api/chat',
            server: ['HTTP_X_CONVERSATION_TOKEN' => $jeton, 'CONTENT_TYPE' => 'application/json'],
            content: json_encode(['message' => $message], \JSON_THROW_ON_ERROR),
        );

        $evenements = [];
        foreach (explode("\n\n", trim((string) $this->client->getInternalResponse()->getContent())) as $bloc) {
            if (preg_match('/^event: (\S+)\ndata: (.*)$/s', $bloc, $m)) {
                $evenements[] = [$m[1], json_decode($m[2], true, flags: \JSON_THROW_ON_ERROR)];
            }
        }

        return $evenements;
    }

    /**
     * @param list<array{0: string, 1: mixed}> $evenements
     *
     * @return array<string, mixed> dernier événement de chaque type, dans l'ordre d'arrivée
     */
    private function parType(array $evenements): array
    {
        $parType = [];
        foreach ($evenements as [$type, $donnees]) {
            unset($parType[$type]);
            $parType[$type] = $donnees;
        }

        return $parType;
    }

    /**
     * @param list<array{0: string, 1: mixed}> $evenements
     */
    private function texte(array $evenements): string
    {
        return implode('', array_map(
            static fn (array $e) => 'texte' === $e[0] ? $e[1]['texte'] : '',
            $evenements,
        ));
    }

    private function conversation(string $jeton): Conversation
    {
        $this->em->clear();

        return $this->em->getRepository(Conversation::class)->findOneBy(['jeton' => $jeton])
            ?? throw new \LogicException('Conversation introuvable.');
    }
}
