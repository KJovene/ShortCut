<?php

namespace App\Tests\Controller;

use App\Chatbot\AgentConversation;
use App\Chatbot\AgentRecherche;
use App\Entity\Conversation;
use App\Entity\Destination;
use App\Entity\JournalIA;
use App\Entity\PeriodeClimatique;
use App\Entity\Prestataire;
use App\Entity\Prestation;
use App\Entity\Utilisateur;
use App\Enum\AgentIA;
use App\Enum\MotifTransmission;
use App\Enum\Role;
use App\Enum\TypePrestataire;
use App\Enum\TypePrestation;
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
        self::assertSame(['enregistrer_exigences', 'chercher_destinations', 'transmettre_au_conseiller', 'choisir_options', 'consulter_catalogue'], array_column($premiere['tools'], 'name'));
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
            FausseApiClaude::appelOutil('enregistrer_exigences', ['typeDeVoyage' => 'sejour', 'lieuOuZone' => 'Lisbonne', 'dateDebut' => '2027-05-14', 'recapitulatifConfirme' => true]),
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
        // Chaque appel garde sa durée et les outils que Claude a appelés.
        self::assertSame(['enregistrer_exigences'], $journal[0]->getOutils());
        self::assertNotContains(null, array_map(static fn (JournalIA $j) => $j->getDureeMs(), $journal));
    }

    public function testLesLogementsRecommandesSAffichentEnCartesSousLeMessage(): void
    {
        $this->creerDestinationAvecClimat('2027-11-01', '2028-02-29');
        $destination = $this->em->getRepository(Destination::class)->findOneBy(['slug' => 'cairns']);
        $hebergeur = (new Prestataire())->setNom('Hôtels du Queensland')->setType(TypePrestataire::Hebergeur);
        $villa = (new Prestation())->setType(TypePrestation::Logement)->setNom('Villa Palm Cove')->setCategorie('Villa')
            ->setCapacite(6)->setChambres(3)->setEquipements(['piscine privée'])->setEnvies(['famille'])->setPhotos(['villa.jpg'])
            ->setDestination($destination)->setPrestataire($hebergeur);
        $this->em->persist($hebergeur);
        $this->em->persist($villa);
        $this->em->flush();
        $jeton = $this->demarrer();
        $this->claude->prevoir(
            FausseApiClaude::appelOutil('consulter_catalogue', ['destination' => 'cairns', 'type' => 'logement', 'envies' => ['famille'], 'voyageurs' => 4]),
            FausseApiClaude::texte('La villa Palm Cove a de la place pour toute la famille : cliquez sur sa carte pour la découvrir.'),
        );

        $evenements = $this->envoyer($jeton, 'Où dormir à Cairns avec nos deux enfants ?');

        // Les cartes arrivent après le message qui les présente.
        self::assertSame(['outil', 'texte', 'suggestions', 'fin'], array_keys($this->parType($evenements)));
        $suggestions = $this->parType($evenements)['suggestions'];
        self::assertSame('suggestions', $suggestions['type']);
        self::assertCount(1, $suggestions['elements']);
        $villa = $suggestions['elements'][0];
        self::assertSame('Villa Palm Cove', $villa['nom']);
        self::assertSame('logement', $villa['type']);
        self::assertSame(['slug' => 'cairns', 'ville' => 'Cairns', 'pays' => 'Australie'], $villa['destination']);
        self::assertSame(['/uploads/prestations/villa.jpg'], $villa['photos']);
        self::assertContains('piscine privée', $villa['equipements']);
        self::assertArrayNotHasKey('prix', $villa);
        self::assertFalse($this->parType($evenements)['fin']['terminee']);

        // La carte est gardée avec le message : elle se réaffiche quand la conversation est reprise.
        $messages = $this->conversation($jeton)->getMessages();
        self::assertSame($suggestions, end($messages)['carte']);
    }

    public function testUnLieuHorsCatalogueConfirmeEstTransmisSansRecherche(): void
    {
        $jeton = $this->demarrer();
        $this->claude->prevoir(
            FausseApiClaude::appelOutil('enregistrer_exigences', ['typeDeVoyage' => 'sejour', 'lieuOuZone' => 'Bora Bora', 'dateDebut' => '2027-05-14', 'recapitulatifConfirme' => true]),
            FausseApiClaude::appelOutil('transmettre_au_conseiller', ['motif' => 'hors_catalogue', 'resume' => 'Bora Bora demandé, les Maldives proposées à la place, le client tient à Bora Bora.']),
            FausseApiClaude::texte('Un conseiller reprend votre demande pour Bora Bora et vous recontacte sous 2 jours ouvrés.'),
        );

        $evenements = $this->envoyer($jeton, 'Oui, c’est bon');

        self::assertCount(3, $this->claude->requetes, 'L\'agent Recherche n\'est pas appelé.');
        self::assertArrayNotHasKey('piste', $this->parType($evenements));
        self::assertTrue($this->parType($evenements)['fin']['terminee']);
        $demande = $this->conversation($jeton)->getDemande();
        self::assertSame('Bora Bora', $demande?->getLieuOuZone());
        self::assertSame(MotifTransmission::HorsCatalogue, $demande->getMotifTransmission());
        self::assertTrue($demande->isPrioritaire());
    }

    public function testUnDossierSuiviParUnConseillerEstMisAJourSansNouvelleRecherche(): void
    {
        $jeton = $this->demarrer();
        $lea = (new Utilisateur())->setEmail('lea.chat@shortcut.test')->setPrenom('Léa')->setNom('Dubois')->setRole(Role::Conseiller);
        $this->em->persist($lea);
        $this->em->flush();
        $this->conversation($jeton)->getDemande()?->setConseiller($this->em->getReference(Utilisateur::class, $lea->getId()));
        $this->em->flush();
        $this->claude->prevoir(
            FausseApiClaude::appelOutil('enregistrer_exigences', ['typeDeVoyage' => 'sejour', 'lieuOuZone' => 'Lisbonne', 'dateDebut' => '2027-05-14', 'adultes' => 4, 'recapitulatifConfirme' => true]),
            FausseApiClaude::texte('C’est noté : vous serez 4. Léa en tient compte.'),
        );

        $evenements = $this->envoyer($jeton, 'Finalement nous serons 4');

        self::assertSame('C’est noté : vous serez 4. Léa en tient compte.', $this->texte($evenements));
        // Le modèle sait que le dossier est suivi (prompts/conversation.md, « Dossier suivi par un conseiller »).
        $message = $this->claude->requetes[0]['corps']['messages'][0]['content'];
        self::assertContains('<dossier_suivi>Ce dossier est suivi par Léa Dubois, conseiller de l\'agence.</dossier_suivi>', array_column($message, 'text'));
        // La fiche est mise à jour, sans relancer l'agent Recherche : c'est le conseiller qui prépare la suite.
        self::assertCount(2, $this->claude->requetes);
        self::assertSame(4, $this->conversation($jeton)->getDemande()?->getAdultes());
    }

    public function testUnRecapitulatifSurUneFicheVideNEstPasConfirme(): void
    {
        $jeton = $this->demarrer();
        $this->claude->prevoir(
            FausseApiClaude::appelOutil('enregistrer_exigences', ['lieuOuZone' => 'Lisbonne', 'recapitulatifConfirme' => true]),
            FausseApiClaude::texte('Je reprends votre demande.'),
        );

        $this->envoyer($jeton, 'Oui, c’est bon');

        $messages = $this->claude->requetes[1]['corps']['messages'];
        $resultat = end($messages)['content'][0];
        self::assertTrue($resultat['is_error']);
        self::assertStringContainsString('le type de voyage, les dates', json_decode($resultat['content'], true)['erreur']);
        $conversation = $this->conversation($jeton);
        self::assertNull($conversation->getFinLe());
        // Ce qui a été donné reste enregistré.
        self::assertSame('Lisbonne', $conversation->getDemande()?->getLieuOuZone());
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

        // US13 : l'appel en échec figure au journal, avec l'erreur et sa durée, pour le responsable.
        $journal = $this->em->getRepository(JournalIA::class)->findBy(['demande' => $this->conversation($jeton)->getDemande()]);
        self::assertCount(1, $journal);
        self::assertSame(AgentIA::Conversation, $journal[0]->getAgent());
        self::assertNotNull($journal[0]->getErreur());
        self::assertNotNull($journal[0]->getDureeMs());

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

    public function testUnAppelIdentiqueNEstPasRefaitDansLeMemeTour(): void
    {
        $jeton = $this->demarrer();
        $this->claude->prevoir(
            FausseApiClaude::appelOutil('enregistrer_exigences', ['envies' => ['neige'], 'recapitulatifConfirme' => false]),
            FausseApiClaude::appelOutil('enregistrer_exigences', ['envies' => ['neige'], 'recapitulatifConfirme' => false]),
            FausseApiClaude::texte('De la neige, c’est noté.'),
        );

        $this->envoyer($jeton, 'Je veux de la neige');

        $messages = $this->claude->requetes[2]['corps']['messages'];
        $resultat = end($messages)['content'][0];
        self::assertTrue($resultat['is_error']);
        self::assertStringContainsString('Appel identique', json_decode($resultat['content'], true)['erreur']);
        self::assertSame(['neige'], $this->conversation($jeton)->getDemande()?->getEnvies());
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
        $demande = $conversation->getDemande();
        self::assertTrue($demande?->isPrioritaire());
        self::assertSame(MotifTransmission::DemandeDuClient, $demande->getMotifTransmission());
        self::assertSame('Le client veut parler à quelqu’un.', $demande->getResumeTransmission());
        self::assertNotNull($demande->getTransmiseLe());

        // Reprise par un conseiller : le chatbot ne répond plus, le message est gardé pour le conseiller.
        $requetes = \count($this->claude->requetes);
        $evenements = $this->envoyer($jeton, 'Finalement, je préfère partir en juillet.');

        self::assertResponseIsSuccessful();
        self::assertSame(AgentConversation::MESSAGE_REPRISE, $this->texte($evenements));
        self::assertSame('fin', array_key_last($this->parType($evenements)));
        self::assertCount($requetes, $this->claude->requetes, 'Claude n\'est plus appelé.');
        self::assertContains('Finalement, je préfère partir en juillet.', array_column($this->conversation($jeton)->getMessages(), 'contenu'));
    }

    public function testUnePeriodeSansClimatSaisiRendLaDemandePrioritaire(): void
    {
        $this->creerDestinationAvecClimat('2027-11-01', '2028-02-29');
        $jeton = $this->demarrer();
        $this->claude->prevoir(
            FausseApiClaude::appelOutil('chercher_destinations', ['zone' => 'Cairns', 'dateDebut' => '2028-02-20', 'dateFin' => '2028-03-05']),
            FausseApiClaude::texte('Nous n’avons pas encore ces dates : un conseiller est prévenu.'),
        );

        $this->envoyer($jeton, 'Cairns du 20 février au 5 mars 2028');

        $messages = $this->claude->requetes[1]['corps']['messages'];
        $sortie = json_decode(end($messages)['content'][0]['content'], true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(['cairns'], $sortie['donneesManquantes']['destinations']);

        $conversation = $this->conversation($jeton);
        $demande = $conversation->getDemande();
        self::assertTrue($demande?->isPrioritaire());
        self::assertSame(MotifTransmission::DonneesManquantes, $demande->getMotifTransmission());
        self::assertStringContainsString('Cairns', (string) $demande->getResumeTransmission());
        self::assertFalse($conversation->isRepriseParConseiller(), 'Le client peut continuer avec d\'autres dates.');
    }

    public function testUnePeriodeCouverteNeTransmetPasLaDemande(): void
    {
        $this->creerDestinationAvecClimat('2027-11-01', '2028-02-29');
        $jeton = $this->demarrer();
        $this->claude->prevoir(
            FausseApiClaude::appelOutil('chercher_destinations', ['zone' => 'Cairns', 'dateDebut' => '2027-12-20', 'dateFin' => '2028-01-05']),
            FausseApiClaude::texte('Cairns est idéal à cette période.'),
        );

        $this->envoyer($jeton, 'Cairns pour les fêtes');

        $messages = $this->claude->requetes[1]['corps']['messages'];
        $sortie = json_decode(end($messages)['content'][0]['content'], true, flags: \JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('donneesManquantes', $sortie);
        // Sans logement tarifé, Cairns est écartée (#202) : son climat est saisi, la demande n'est pas transmise.
        self::assertSame('cairns', $sortie['ecartees'][0]['slug']);
        self::assertFalse($this->conversation($jeton)->getDemande()?->isPrioritaire());
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

    private function creerDestinationAvecClimat(string $debut, string $fin): void
    {
        $destination = (new Destination())->setPays('Australie')->setVille('Cairns')->setSlug('cairns')->setPubliee(true);
        $periode = (new PeriodeClimatique())
            ->setDebut(new \DateTimeImmutable($debut))
            ->setFin(new \DateTimeImmutable($fin))
            ->setTemperatureMoyenne(28);
        $destination->addPeriodeClimatique($periode);
        $this->em->persist($destination);
        $this->em->persist($periode);
        $this->em->flush();
    }

    private function conversation(string $jeton): Conversation
    {
        $this->em->clear();

        return $this->em->getRepository(Conversation::class)->findOneBy(['jeton' => $jeton])
            ?? throw new \LogicException('Conversation introuvable.');
    }
}
