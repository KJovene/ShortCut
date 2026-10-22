<?php

namespace App\Tests\Chatbot;

use App\Chatbot\AgentRecherche;
use App\Chatbot\AgentRedaction;
use App\Entity\Conversation;
use App\Entity\JournalIA;
use App\Entity\Prestation;
use App\Enum\AgentIA;
use App\Enum\StatutDemande;
use App\Enum\StatutProposition;
use App\Enum\TypeDeVoyage;
use App\Enum\TypeLigne;
use Doctrine\Bundle\FixturesBundle\Loader\SymfonyFixturesLoader;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Agents Recherche (#203) et Rédaction (#207) dans le chat, sur le catalogue de démo : après la confirmation du
 * récapitulatif, la recherche voit sa combinaison refusée puis acceptée, puis la rédaction voit sa proposition refusée
 * puis enregistrée ; le site reçoit la combinaison et la proposition. L'API Claude est remplacée par FausseApiClaude.
 * Nécessite `make db-test`.
 */
final class AgentRechercheTest extends WebTestCase
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

        /** @var SymfonyFixturesLoader $loader */
        $loader = static::getContainer()->get('doctrine.fixtures.loader');
        (new ORMExecutor($this->em, new ORMPurger($this->em)))->execute($loader->getFixtures());

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

    public function testLaRechercheRetientLaCombinaisonEtLaRedactionEnregistreLaProposition(): void
    {
        $jeton = $this->demanderLisbonneEnFauteuil();
        $hotel = $this->id('Hôtel Alfama Vista, chambre double');
        $palace = $this->id('Tejo Palace, suite vue fleuve');
        $appartement = $this->id('Appartement Bairro Alto');
        $etapes = static fn (array $ids): array => [['dateDebut' => '2027-05-14', 'nombreDeNuits' => 3, 'logementIds' => $ids]];

        $this->claude->prevoir(
            // Agent Conversation : le client confirme.
            FausseApiClaude::appelOutil('enregistrer_exigences', ['recapitulatifConfirme' => true]),
            FausseApiClaude::texte('Parfait, je cherche votre voyage.'),
            // Agent Recherche : une recherche, un choix refusé (appartement non accessible), puis le bon.
            FausseApiClaude::appelOutil('chercher_destinations', ['zone' => 'Lisbonne', 'dateDebut' => '2027-05-14', 'dateFin' => '2027-05-17']),
            FausseApiClaude::appelOutil('retenir_combinaison', ['destination' => 'lisbonne', 'etapes' => $etapes([$hotel, $appartement]), 'activitesDemandees' => [], 'activitesSuggerees' => [], 'pourquoi' => 'Lisbonne à deux.']),
            FausseApiClaude::appelOutil('retenir_combinaison', ['destination' => 'lisbonne', 'etapes' => $etapes([$hotel, $palace]), 'activitesDemandees' => [], 'activitesSuggerees' => [], 'pourquoi' => 'Lisbonne à deux, de plain-pied.']),
            FausseApiClaude::texte('Je vous propose **Lisbonne** en mai.'),
            // Agent Rédaction : un programme de 3 jours refusé (3 nuits, donc 4 jours), puis le bon ; pas de réponse finale.
            $this->claude->appelOutilSelon('enregistrer_proposition', fn (array $requete): array => $this->proposition($requete, [$hotel, $palace], 3)),
            $this->claude->appelOutilSelon('enregistrer_proposition', fn (array $requete): array => $this->proposition($requete, [$hotel, $palace], 4)),
        );

        $evenements = $this->envoyer($jeton, 'Oui, c’est tout bon');

        self::assertSame(
            ['outil', 'demande', 'texte', 'texte', 'texte', 'outil', 'outil', 'outil', 'combinaison', 'texte', 'outil', 'outil', 'texte', 'fin'],
            array_column($evenements, 0),
        );
        $combinaison = current(array_filter($evenements, static fn (array $e) => 'combinaison' === $e[0]))[1];
        self::assertSame('lisbonne', $combinaison['destination']['slug']);
        self::assertSame(['Hôtel Alfama Vista, chambre double'], array_column($combinaison['niveaux']['essentiel']['logements'], 'nom'));
        self::assertSame(['Tejo Palace, suite vue fleuve'], array_column($combinaison['niveaux']['confort']['logements'], 'nom'));
        self::assertSame('/api/logements/'.$palace, $combinaison['niveaux']['confort']['logements'][0]['fiche']);
        self::assertStringStartsWith('Prix estimés le ', $combinaison['avertissement']);

        // Le refus est renvoyé au modèle avec sa raison, pour qu'il corrige son choix.
        $apresRefus = $this->claude->requetes[4]['corps']['messages'];
        $refus = end($apresRefus)['content'][0];
        self::assertTrue($refus['is_error']);
        self::assertStringContainsString('Appartement Bairro Alto, non accessible', json_decode($refus['content'], true)['erreur']);

        $conversation = $this->conversation($jeton);
        // Le gras markdown est retiré : le widget affiche du texte brut.
        $messages = $conversation->getMessages();
        self::assertSame('Je vous propose Lisbonne en mai.', $messages[\count($messages) - 2]['contenu']);
        $recherche = $this->em->getRepository(JournalIA::class)->findBy(['demande' => $conversation->getDemande(), 'agent' => AgentIA::Recherche]);
        self::assertCount(4, $recherche);
        self::assertSame(AgentRecherche::MODELE, $this->claude->requetes[2]['corps']['model']);

        // La rédaction reçoit le calendrier du voyage : 3 nuits, 4 jours.
        self::assertSame(AgentRedaction::MODELE, $this->claude->requetes[6]['corps']['model']);
        self::assertSame(['enregistrer_proposition', 'transmettre_au_conseiller'], array_column($this->claude->requetes[6]['corps']['tools'], 'name'));
        $calendrier = $this->claude->requetes[6]['corps']['messages'][0]['content'][0]['text'];
        self::assertStringContainsString('Jour 1, vendredi 14 mai 2027 : arrivée', $calendrier);
        self::assertStringContainsString('Jour 4, lundi 17 mai 2027 : départ', $calendrier);
        $messages = $this->claude->requetes[7]['corps']['messages'];
        $refus = end($messages)['content'][0];
        self::assertTrue($refus['is_error']);
        self::assertStringContainsString('Le programme compte 3 jours ; le voyage en compte 4', json_decode($refus['content'], true)['erreur']);
        self::assertCount(8, $this->claude->requetes, 'Pas d\'appel après l\'enregistrement réussi.');

        // La proposition est un brouillon que le conseiller relira : le client en est seulement prévenu.
        self::assertSame("\n\n".AgentRedaction::MESSAGE_BROUILLON, $evenements[\count($evenements) - 2][1]['texte']);
        $conversation = $this->conversation($jeton);
        $messagesAuClient = $conversation->getMessages();
        self::assertSame(AgentRedaction::MESSAGE_BROUILLON, end($messagesAuClient)['contenu']);

        $demande = $conversation->getDemande() ?? throw new \LogicException();
        self::assertSame(StatutDemande::Nouvelle, $demande->getStatut());
        $proposition = $demande->getPropositions()->first() ?: throw new \LogicException('Aucune proposition.');
        self::assertSame(1, $proposition->getVersion());
        self::assertSame(StatutProposition::Brouillon, $proposition->getStatut());
        self::assertCount(4, $proposition->getProgramme());
        self::assertSame('lisbonne', $proposition->getDestination()?->getSlug());
        self::assertSame(['2027-05-14', '2027-05-15', '2027-05-16', '2027-05-17'], array_column($proposition->getProgramme(), 'date'));
        self::assertSame([$hotel, $palace], array_column($proposition->getPrestations(), 'id'));
        self::assertContains('essentiel', $proposition->getPrestations()[0]['niveaux']);
        self::assertSame(['Plain-pied', 'Chambre adaptée'], $proposition->getPrestations()[0]['pointsForts']);
        // Montants du chiffrage, jamais du modèle : le total du niveau détaillé et ses lignes.
        self::assertEquals($combinaison['niveaux'][$proposition->getNiveauBudget()->value]['totalEuros'] * 100, $proposition->getTotalCentimes());
        $lignes = array_values($proposition->getLignes()->toArray());
        self::assertSame([TypeLigne::Logement, TypeLigne::Transport, TypeLigne::Transport], array_map(static fn ($l) => $l->getType(), $lignes));
        self::assertSame(3, $lignes[0]->getQuantite());
        self::assertSame('2027-05-17', $lignes[0]->getDateFin()?->format('Y-m-d'));
        self::assertNotNull($lignes[0]->getPrestation());
        self::assertCount(2, $this->em->getRepository(JournalIA::class)->findBy(['demande' => $demande, 'agent' => AgentIA::Chiffrage]));
    }

    /** #207 : une rédaction qui échoue laisse la demande à un conseiller, et le client en est prévenu. */
    public function testUneRedactionSansSuiteEstTransmiseAuConseiller(): void
    {
        $jeton = $this->demanderLisbonneEnFauteuil();
        $hotel = $this->id('Hôtel Alfama Vista, chambre double');
        $this->claude->prevoir(
            FausseApiClaude::appelOutil('enregistrer_exigences', ['recapitulatifConfirme' => true]),
            FausseApiClaude::texte('Parfait, je cherche votre voyage.'),
            FausseApiClaude::appelOutil('retenir_combinaison', ['destination' => 'lisbonne', 'etapes' => [['dateDebut' => '2027-05-14', 'nombreDeNuits' => 3, 'logementIds' => [$hotel]]], 'activitesDemandees' => [], 'activitesSuggerees' => [], 'pourquoi' => 'Lisbonne à deux.']),
            FausseApiClaude::texte('Je vous propose Lisbonne en mai.'),
            FausseApiClaude::erreur(529, 'Overloaded'),
        );

        $evenements = $this->envoyer($jeton, 'Oui, c’est tout bon');

        self::assertNotContains('proposition', array_column($evenements, 0));
        self::assertNotContains('erreur', array_column($evenements, 0));
        $conversation = $this->conversation($jeton);
        self::assertTrue($conversation->isRepriseParConseiller());
        self::assertCount(0, $conversation->getDemande()?->getPropositions() ?? []);
        $messages = $conversation->getMessages();
        self::assertSame('Je vous propose Lisbonne en mai.', $messages[\count($messages) - 2]['contenu']);
        self::assertStringStartsWith('Je n\'ai pas pu mettre en forme votre programme détaillé. Un conseiller', end($messages)['contenu']);
    }

    /** #204 : une recherche qui échoue sans rien dire au client transmet d'elle-même la demande au conseiller. */
    public function testUneRechercheSansSuiteEstTransmiseAuConseiller(): void
    {
        $jeton = $this->demanderLisbonneEnFauteuil();
        $this->claude->prevoir(
            FausseApiClaude::appelOutil('enregistrer_exigences', ['recapitulatifConfirme' => true]),
            FausseApiClaude::texte('Parfait, je cherche votre voyage.'),
            FausseApiClaude::erreur(529, 'Overloaded'),
        );

        $evenements = $this->envoyer($jeton, 'Oui, c’est tout bon');

        self::assertContains('erreur', array_column($evenements, 0));
        self::assertSame('fin', end($evenements)[0]);
        $conversation = $this->conversation($jeton);
        self::assertTrue($conversation->isRepriseParConseiller());
        self::assertTrue($conversation->getDemande()?->isPrioritaire());
        $messages = $conversation->getMessages();
        self::assertSame('Je n\'ai pas pu terminer votre proposition. Un conseiller de l\'agence reprend votre demande et vous recontacte sous 2 jours ouvrés.', end($messages)['contenu']);
    }

    /** Une conversation dont la fiche est complète : il ne manque que la confirmation du client. */
    private function demanderLisbonneEnFauteuil(): string
    {
        $this->client->request('POST', '/api/chat/conversations');
        $jeton = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR)['jeton'];

        $demande = $this->em->getRepository(Conversation::class)->findOneBy(['jeton' => $jeton])?->getDemande() ?? throw new \LogicException();
        $demande->setTypeDeVoyage(TypeDeVoyage::Sejour)->setLieuOuZone('Lisbonne')->setVilleDepart('Lyon')
            ->setDateDebut(new \DateTimeImmutable('2027-05-14'))->setDateFin(new \DateTimeImmutable('2027-05-17'))->setNombreDeJours(3)
            ->setAdultes(2)->setBudgetCentimes(130000)->setEnvies(['gastronomie'])->setContraintes(['fauteuil roulant']);
        $this->em->flush();

        return $jeton;
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
        self::assertResponseIsSuccessful();

        $evenements = [];
        foreach (explode("\n\n", trim((string) $this->client->getInternalResponse()->getContent())) as $bloc) {
            if (preg_match('/^event: (\S+)\ndata: (.*)$/s', $bloc, $m)) {
                $evenements[] = [$m[1], json_decode($m[2], true, flags: \JSON_THROW_ON_ERROR)];
            }
        }

        return $evenements;
    }

    /**
     * Ce que l'agent Rédaction enverrait, sur le chiffrage donné dans son premier message.
     *
     * @param array<string, mixed> $requete   corps de la requête reçue par l'API
     * @param list<int>            $logements
     *
     * @return array<string, mixed>
     */
    private function proposition(array $requete, array $logements, int $jours): array
    {
        preg_match('/chiffrageId : (chf_[0-9a-f]+).*niveauDetaille\) : (\w+)/s', $requete['messages'][0]['content'][0]['text'], $m);

        return [
            'chiffrageId' => $m[1],
            'niveauDetaille' => $m[2],
            'pourquoi' => 'Lisbonne se visite très bien en fauteuil, et sa cuisine vous attend.',
            'argumentaire' => [['exigence' => 'bien manger', 'reponse' => 'Les quartiers gourmands de l\'Alfama.']],
            'prestations' => array_map(static fn (int $id): array => ['id' => $id, 'pointsForts' => ['Plain-pied', 'Chambre adaptée']], $logements),
            'programme' => array_map(static fn (int $jour): array => ['jour' => $jour, 'titre' => 'Jour '.$jour, 'description' => 'Découverte de Lisbonne.', 'activiteIds' => []], range(1, $jours)),
        ];
    }

    private function id(string $nom): int
    {
        return (int) $this->em->getRepository(Prestation::class)->findOneBy(['nom' => $nom])?->getId();
    }

    private function conversation(string $jeton): Conversation
    {
        $this->em->clear();

        return $this->em->getRepository(Conversation::class)->findOneBy(['jeton' => $jeton]) ?? throw new \LogicException();
    }
}
