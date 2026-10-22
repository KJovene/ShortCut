<?php

namespace App\Tests\Chatbot;

use App\Chatbot\AgentRedaction;
use App\Chatbot\Outil\EnregistrerExigences;
use App\Entity\Conversation;
use App\Entity\Demande;
use App\Entity\Prestation;
use App\Entity\Proposition;
use App\Entity\Utilisateur;
use App\Enum\NiveauBudget;
use App\Enum\StatutDemande;
use App\Enum\StatutProposition;
use App\Enum\TypeDeVoyage;
use App\Proposition\PropositionService;
use Doctrine\Bundle\FixturesBundle\Loader\SymfonyFixturesLoader;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Modification après une proposition (#211), dans le chat, sur le catalogue de démo : le client a reçu la version 1
 * (Lisbonne, du 14 au 17 mai 2027) et change sa demande. L'API Claude est remplacée par FausseApiClaude.
 * Nécessite `make db-test`.
 */
final class NouvelleVersionTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private FausseApiClaude $claude;
    private string $jeton;
    private Proposition $version1;
    /** @var list<int> */
    private array $logements;

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

        $this->logements = [$this->id('Hôtel Alfama Vista, chambre double'), $this->id('Tejo Palace, suite vue fleuve')];
        $this->recevoirLaVersion1();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testDeNouvellesDatesRecalculentLaMemeCombinaisonEtPreparentLaVersion2PourLeConseiller(): void
    {
        $this->claude->prevoir(
            FausseApiClaude::appelOutil('enregistrer_exigences', ['dateDebut' => '2027-05-21', 'dateFin' => '2027-05-24', 'recapitulatifConfirme' => true]),
            FausseApiClaude::texte('C’est noté, je mets votre proposition à jour.'),
            // Pas d'agent Recherche : la même combinaison est recalculée. La rédaction écrit la version 2.
            $this->claude->appelOutilSelon('enregistrer_proposition', fn (array $requete): array => $this->proposition($requete, 4)),
        );

        $evenements = $this->envoyer('Finalement, décalez d’une semaine : du 21 au 24 mai.');

        self::assertSame(
            ['outil', 'demande', 'texte', 'texte', 'outil', 'combinaison', 'texte', 'outil', 'texte', 'fin'],
            array_column($evenements, 0),
        );
        self::assertCount(3, $this->claude->requetes, 'Conversation (2 appels) puis rédaction, sans recherche.');
        $redaction = $this->claude->requetes[2]['corps']['messages'][0]['content'][0]['text'];
        self::assertStringContainsString('Rédige la version suivante', $redaction);
        self::assertStringContainsString('C\'est la version 2', $redaction);
        self::assertStringContainsString('Programme de la version 1', $redaction);
        self::assertStringContainsString('Jour 1, vendredi 21 mai 2027 : arrivée', $redaction);

        $conversation = $this->conversation();
        $messages = array_column($conversation->getMessages(), 'contenu');
        $presentation = $messages[\count($messages) - 2];
        self::assertStringStartsWith('Voici votre proposition mise à jour (dates : du 21/05/2027 au 24/05/2027, 3 nuits) :', $presentation);
        self::assertStringContainsString('- Essentiel : Hôtel Alfama Vista, chambre double, ', $presentation);
        self::assertMatchesRegularExpression('/\nPrix estimés le .+ à \d+ h( \d{2})? d\'après nos tarifs : votre conseiller vérifie chaque prix et chaque place avant de vous les confirmer\.$/', $presentation);
        self::assertSame(\sprintf(AgentRedaction::MESSAGE_NOUVELLE_VERSION, 2, 1), end($messages));

        // La version 2 attend le conseiller, en brouillon : le client garde la version 1.
        $demande = $conversation->getDemande() ?? throw new \LogicException();
        [$v1, $v2] = $this->versions($demande);
        self::assertSame(StatutProposition::Envoyee, $v1->getStatut());
        self::assertSame(StatutProposition::Brouillon, $v2->getStatut());
        self::assertNull($v2->getEnvoyeeLe());
        self::assertSame('2027-05-21', $v2->getChoix()['etapes'][0]['dateDebut'] ?? null);
        self::assertSame('2027-05-21', $v2->getExigences()['dateDebut']);
        self::assertSame(StatutDemande::PropositionEnvoyee, $demande->getStatut());
        $this->client->request('GET', '/api/propositions/'.$v1->getJetonPublic());
        self::assertSame(1, json_decode((string) $this->client->getResponse()->getContent(), true)['version']);

        // Le conseiller l'envoie : la version 1 est remplacée mais reste consultable.
        $em = static::getContainer()->get(EntityManagerInterface::class);
        static::getContainer()->get(PropositionService::class)->envoyer($em->find(Proposition::class, $v2->getId()) ?? throw new \LogicException());
        $em->flush();
        $em->clear();
        self::assertSame(StatutProposition::Remplacee, $em->find(Proposition::class, $v1->getId())?->getStatut());
        self::assertSame(StatutProposition::Envoyee, $em->find(Proposition::class, $v2->getId())?->getStatut());

        $this->client->request('GET', '/api/propositions/'.$v1->getJetonPublic());
        $page = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame(2, $page['version']);
        self::assertSame([1, 2], array_column($page['versions'], 'version'));
        $this->client->request('GET', '/api/propositions/'.$v2->getJetonPublic().'/versions/1');
        self::assertResponseIsSuccessful();
        $precedente = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame(['version' => 1, 'statut' => 'remplacee'], ['version' => $precedente['version'], 'statut' => $precedente['statut']]);
        self::assertSame('2027-05-14', $precedente['programme'][0]['date']);
    }

    public function testDeNouvellesEnviesRepartentDeLaCombinaisonPrecedente(): void
    {
        $this->claude->prevoir(
            FausseApiClaude::appelOutil('enregistrer_exigences', ['envies' => ['gastronomie', 'culture'], 'recapitulatifConfirme' => true]),
            FausseApiClaude::texte('Je mets votre proposition à jour.'),
            FausseApiClaude::appelOutil('retenir_combinaison', ['destination' => 'lisbonne', 'etapes' => [['dateDebut' => '2027-05-14', 'nombreDeNuits' => 3, 'logementIds' => $this->logements]], 'activitesDemandees' => [], 'activitesSuggerees' => [], 'pourquoi' => 'Lisbonne, avec la culture en plus.']),
            FausseApiClaude::texte('Même Lisbonne, avec plus de culture.'),
            $this->claude->appelOutilSelon('enregistrer_proposition', fn (array $requete): array => $this->proposition($requete, 4)),
        );

        $this->envoyer('Ajoutez la culture à nos envies.');

        $recherche = $this->claude->requetes[2]['corps']['messages'][0]['content'][0]['text'];
        self::assertStringStartsWith('Le client a modifié sa demande après sa proposition (version 1)', $recherche);
        self::assertStringContainsString('"envies":{"avant":["gastronomie"],"apres":["gastronomie","culture"]}', $recherche);
        self::assertStringContainsString('{"id":'.$this->logements[0].',"nom":"Hôtel Alfama Vista, chambre double"}', $recherche);
        self::assertStringNotContainsString('a été refusée', $recherche);
        [, $v2] = $this->versions($this->conversation()->getDemande() ?? throw new \LogicException());
        self::assertSame(StatutProposition::Brouillon, $v2->getStatut());
    }

    public function testUneCombinaisonQuiNeConvientPlusPasseParLaRecherche(): void
    {
        // Aucun tarif saisi en 2028 : la même combinaison est refusée, la recherche en connaît la raison.
        $this->claude->prevoir(
            FausseApiClaude::appelOutil('enregistrer_exigences', ['dateDebut' => '2028-05-14', 'dateFin' => '2028-05-17', 'recapitulatifConfirme' => true]),
            FausseApiClaude::texte('Je mets votre proposition à jour.'),
            FausseApiClaude::texte('L’agence n’a pas encore les tarifs de mai 2028.'),
        );

        $this->envoyer('Et plutôt en mai 2028 ?');

        self::assertCount(3, $this->claude->requetes);
        $recherche = $this->claude->requetes[2]['corps']['messages'][0]['content'][0]['text'];
        self::assertStringContainsString('Recalculée telle quelle avec la demande modifiée, cette combinaison a été refusée : Lisbonne ne convient pas', $recherche);
        self::assertCount(1, $this->versions($this->conversation()->getDemande() ?? throw new \LogicException()));
    }

    public function testUneConfirmationSansChangementGardeLaProposition(): void
    {
        $this->claude->prevoir(
            FausseApiClaude::appelOutil('enregistrer_exigences', ['recapitulatifConfirme' => true]),
            FausseApiClaude::texte('Très bien.'),
        );

        $this->envoyer('C’est toujours bon pour moi.');

        self::assertCount(2, $this->claude->requetes);
        $messages = array_column($this->conversation()->getMessages(), 'contenu');
        self::assertSame('Votre demande n\'a pas changé : votre proposition (version 1) reste valable.', end($messages));
    }

    /** Le client a confirmé Lisbonne en fauteuil et reçu la version 1, chiffrée sur l'hôtel et le palace. */
    private function recevoirLaVersion1(): void
    {
        $this->client->request('POST', '/api/chat/conversations');
        $this->jeton = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR)['jeton'];

        $demande = $this->em->getRepository(Conversation::class)->findOneBy(['jeton' => $this->jeton])?->getDemande() ?? throw new \LogicException();
        $demande->setTypeDeVoyage(TypeDeVoyage::Sejour)->setLieuOuZone('Lisbonne')->setVilleDepart('Lyon')
            ->setDateDebut(new \DateTimeImmutable('2027-05-14'))->setDateFin(new \DateTimeImmutable('2027-05-17'))->setNombreDeJours(3)
            ->setAdultes(2)->setBudgetCentimes(130000)->setEnvies(['gastronomie'])->setContraintes(['fauteuil roulant'])
            ->setStatut(StatutDemande::PropositionEnvoyee)
            // Une proposition n'est envoyée que par le conseiller à qui la demande est attribuée.
            ->setConseiller($this->em->getRepository(Utilisateur::class)->findOneBy(['email' => 'lea@shortcut.test']));
        $this->version1 = (new Proposition())
            ->setVersion(1)
            ->setStatut(StatutProposition::Envoyee)
            ->setEnvoyeeLe(new \DateTimeImmutable('-1 hour'))
            ->setNiveauBudget(NiveauBudget::Equilibre)
            ->setChoix(['destination' => 'lisbonne', 'etapes' => [['dateDebut' => '2027-05-14', 'nombreDeNuits' => 3, 'logementIds' => $this->logements]], 'activitesDemandees' => [], 'activitesSuggerees' => [], 'pourquoi' => 'Lisbonne à deux, de plain-pied.'])
            ->setExigences(EnregistrerExigences::fiche($demande))
            ->setProgramme(array_map(static fn (int $jour): array => ['jour' => $jour, 'date' => \sprintf('2027-05-%d', 13 + $jour), 'titre' => 'Jour '.$jour, 'description' => 'Lisbonne.', 'activiteIds' => []], range(1, 4)));
        $demande->addProposition($this->version1);
        $this->em->persist($this->version1);
        $this->em->flush();
    }

    /**
     * @return list<array{0: string, 1: mixed}>
     */
    private function envoyer(string $message): array
    {
        $this->client->request(
            'POST',
            '/api/chat',
            server: ['HTTP_X_CONVERSATION_TOKEN' => $this->jeton, 'CONTENT_TYPE' => 'application/json'],
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
     * @param array<string, mixed> $requete corps de la requête reçue par l'API
     *
     * @return array<string, mixed>
     */
    private function proposition(array $requete, int $jours): array
    {
        preg_match('/chiffrageId : (chf_[0-9a-f]+).*niveauDetaille\) : (\w+)/s', $requete['messages'][0]['content'][0]['text'], $m);

        return [
            'chiffrageId' => $m[1],
            'niveauDetaille' => $m[2],
            'pourquoi' => 'Lisbonne se visite très bien en fauteuil.',
            'argumentaire' => [['exigence' => 'bien manger', 'reponse' => 'Les quartiers gourmands de l\'Alfama.']],
            'prestations' => array_map(static fn (int $id): array => ['id' => $id, 'pointsForts' => ['Plain-pied', 'Chambre adaptée']], $this->logements),
            'programme' => array_map(static fn (int $jour): array => ['jour' => $jour, 'titre' => 'Jour '.$jour, 'description' => 'Découverte de Lisbonne.', 'activiteIds' => []], range(1, $jours)),
        ];
    }

    /** @return list<Proposition> */
    private function versions(Demande $demande): array
    {
        return $this->em->getRepository(Proposition::class)->findBy(['demande' => $demande], ['version' => 'ASC']);
    }

    private function id(string $nom): int
    {
        return (int) $this->em->getRepository(Prestation::class)->findOneBy(['nom' => $nom])?->getId();
    }

    private function conversation(): Conversation
    {
        $this->em->clear();

        return $this->em->getRepository(Conversation::class)->findOneBy(['jeton' => $this->jeton]) ?? throw new \LogicException();
    }
}
