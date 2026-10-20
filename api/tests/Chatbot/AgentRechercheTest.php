<?php

namespace App\Tests\Chatbot;

use App\Chatbot\AgentRecherche;
use App\Entity\Conversation;
use App\Entity\JournalIA;
use App\Entity\Prestation;
use App\Enum\AgentIA;
use App\Enum\TypeDeVoyage;
use Doctrine\Bundle\FixturesBundle\Loader\SymfonyFixturesLoader;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Agent Recherche (#203) dans le chat, sur le catalogue de démo : après la confirmation du récapitulatif, il
 * cherche, voit sa combinaison refusée puis acceptée, et le site reçoit la combinaison retenue. L'API Claude est
 * remplacée par FausseApiClaude. Nécessite `make db-test`.
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

    public function testLaRechercheCorrigeUnChoixRefuseEtRetientLaCombinaison(): void
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
        );

        $evenements = $this->envoyer($jeton, 'Oui, c’est tout bon');

        self::assertSame(
            ['outil', 'demande', 'texte', 'texte', 'texte', 'outil', 'outil', 'outil', 'combinaison', 'texte', 'fin'],
            array_column($evenements, 0),
        );
        $combinaison = current(array_filter($evenements, static fn (array $e) => 'combinaison' === $e[0]))[1];
        self::assertSame('lisbonne', $combinaison['destination']['slug']);
        self::assertSame(['Hôtel Alfama Vista, chambre double'], array_column($combinaison['niveaux']['essentiel']['logements'], 'nom'));
        self::assertSame(['Tejo Palace, suite vue fleuve'], array_column($combinaison['niveaux']['confort']['logements'], 'nom'));
        self::assertSame('/api/logements/'.$palace, $combinaison['niveaux']['confort']['logements'][0]['fiche']);
        self::assertStringStartsWith('Première proposition, prix estimés', $combinaison['avertissement']);

        // Le refus est renvoyé au modèle avec sa raison, pour qu'il corrige son choix.
        $apresRefus = $this->claude->requetes[4]['corps']['messages'];
        $refus = end($apresRefus)['content'][0];
        self::assertTrue($refus['is_error']);
        self::assertStringContainsString('Appartement Bairro Alto, non accessible', json_decode($refus['content'], true)['erreur']);

        $conversation = $this->conversation($jeton);
        // Le gras markdown est retiré : le widget affiche du texte brut.
        $messages = $conversation->getMessages();
        self::assertSame('Je vous propose Lisbonne en mai.', end($messages)['contenu']);
        $recherche = $this->em->getRepository(JournalIA::class)->findBy(['demande' => $conversation->getDemande(), 'agent' => AgentIA::Recherche]);
        self::assertCount(4, $recherche);
        self::assertSame(AgentRecherche::MODELE, $this->claude->requetes[2]['corps']['model']);
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
