<?php

namespace App\Tests\Controller;

use App\Confirmation\ConfirmationImpossible;
use App\Confirmation\ConfirmationService;
use App\Confirmation\PropositionClient;
use App\Entity\Demande;
use App\Entity\Proposition;
use App\Entity\Utilisateur;
use App\Enum\StatutDemande;
use App\Enum\StatutProposition;
use Doctrine\Bundle\FixturesBundle\Loader\SymfonyFixturesLoader;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Statuts et version confirmée (#210), sur la proposition de démo (Marrakech) : le client demande la confirmation,
 * le conseiller confirme, le lien du client mène à la version confirmée, le client l'accepte. Nécessite `make db-test`.
 */
final class PropositionTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Demande $demande;
    private Proposition $estimation;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();

        /** @var SymfonyFixturesLoader $loader */
        $loader = static::getContainer()->get('doctrine.fixtures.loader');
        (new ORMExecutor($this->em, new ORMPurger($this->em)))->execute($loader->getFixtures());

        $this->demande = $this->em->getRepository(Demande::class)->findOneBy(['lieuOuZone' => 'Marrakech']) ?? throw new \LogicException();
        $this->estimation = $this->demande->getPropositions()->first() ?: throw new \LogicException();
        // Comme après l'envoi par le chatbot : le client n'a pas encore demandé la confirmation.
        $this->demande->setStatut(StatutDemande::PropositionEnvoyee);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testLaPageMontreUneEstimationSansLesPrixDAchat(): void
    {
        $proposition = $this->get($this->estimation->getJetonPublic());

        self::assertSame('estimee', $proposition['etat']);
        self::assertSame('envoyee', $proposition['statut']);
        self::assertSame(PropositionClient::MENTION_ESTIMEE, $proposition['mention']);
        self::assertSame(945200, $proposition['totalCentimes']);
        self::assertNull($proposition['versionDemandee']);
        self::assertSame(['demanderConfirmation' => true, 'accepter' => false], $proposition['actions']);
        self::assertCount(5, $proposition['prestations']);
        self::assertSame('Riad Dar Yasmine, riad entier', $proposition['prestations'][0]['libelle']);
        self::assertStringStartsWith('/api/logements/', $proposition['prestations'][0]['fiche']);
        // Les lignes sont des prix d'achat : jamais montrés au client.
        self::assertStringNotContainsString('prix', strtolower((string) json_encode($proposition['prestations'])));
    }

    public function testLeClientDemandeLaConfirmationUneSeuleFois(): void
    {
        $proposition = $this->post($this->estimation->getJetonPublic(), 'demande-de-confirmation');
        self::assertResponseIsSuccessful();
        self::assertSame('a_confirmer', $proposition['demande']['statut']);
        self::assertFalse($proposition['actions']['demanderConfirmation']);

        $refus = $this->post($this->estimation->getJetonPublic(), 'demande-de-confirmation');
        self::assertResponseStatusCodeSame(409);
        self::assertSame('Votre demande de confirmation est déjà transmise à votre conseiller.', $refus['erreur']);
    }

    public function testUneEstimationNeSAcceptePas(): void
    {
        $refus = $this->post($this->estimation->getJetonPublic(), 'acceptation');

        self::assertResponseStatusCodeSame(409);
        self::assertSame('Seule une proposition confirmée par votre conseiller peut être acceptée.', $refus['erreur']);
        $this->em->clear();
        self::assertSame(StatutDemande::PropositionEnvoyee, $this->em->find(Demande::class, $this->demande->getId())?->getStatut());
    }

    public function testLeLienDuClientMeneALaVersionConfirmeeQuIlAccepte(): void
    {
        $this->post($this->estimation->getJetonPublic(), 'demande-de-confirmation');
        $confirmee = $this->confirmer();

        // Le lien de l'estimation, gardé par le client, mène à la version confirmée.
        $proposition = $this->get($this->estimation->getJetonPublic());
        self::assertSame('confirmee', $proposition['etat']);
        self::assertSame(2, $proposition['version']);
        self::assertSame(1, $proposition['versionDemandee']);
        self::assertSame($confirmee->getJetonPublic(), $proposition['jeton']);
        self::assertSame(PropositionClient::MENTION_CONFIRMEE, $proposition['mention']);
        self::assertSame('confirmee', $proposition['demande']['statut']);
        self::assertSame(['demanderConfirmation' => false, 'accepter' => true], $proposition['actions']);
        $this->em->clear();
        self::assertSame(StatutProposition::Remplacee, $this->em->find(Proposition::class, $this->estimation->getId())?->getStatut());

        $acceptee = $this->post($this->estimation->getJetonPublic(), 'acceptation');
        self::assertResponseIsSuccessful();
        self::assertSame('acceptee', $acceptee['statut']);
        self::assertSame('acceptee', $acceptee['demande']['statut']);
        $this->em->clear();
        self::assertSame(StatutDemande::Acceptee, $this->em->find(Demande::class, $this->demande->getId())?->getStatut());
        self::assertSame(StatutProposition::Acceptee, $this->em->find(Proposition::class, $confirmee->getId())?->getStatut());

        $refus = $this->post($confirmee->getJetonPublic(), 'acceptation');
        self::assertResponseStatusCodeSame(409);
        self::assertSame('Vous avez déjà accepté cette proposition.', $refus['erreur']);
    }

    public function testUneDemandePerdueNeSeConfirmePas(): void
    {
        $this->demande->setStatut(StatutDemande::Perdue);
        $this->em->flush();

        $this->expectException(ConfirmationImpossible::class);
        $this->expectExceptionMessage('La demande est « Perdue »');
        $this->confirmer();
    }

    public function testUnBrouillonOuUnJetonInconnuRestentIntrouvables(): void
    {
        $brouillon = static::getContainer()->get(ConfirmationService::class)->versionDeConfirmation($this->demande, $this->estimation);

        $this->client->request('GET', '/api/propositions/'.$brouillon->getJetonPublic());
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/api/propositions/'.str_repeat('0', 64));
        self::assertResponseStatusCodeSame(404);
    }

    /** Le conseiller confirme tous les prix tels qu'estimés. */
    private function confirmer(): Proposition
    {
        $service = static::getContainer()->get(ConfirmationService::class);
        $demande = $this->em->find(Demande::class, $this->demande->getId()) ?? throw new \LogicException();
        $estimation = $service->estimation($demande) ?? throw new \LogicException();
        $version = $service->versionDeConfirmation($demande, $estimation);
        $prix = [];
        foreach ($version->getLignes() as $ligne) {
            $prix[(int) $ligne->getId()] = $ligne->getPrixEstimeCentimes();
        }
        $service->saisir($version, $prix, 20000);
        $lea = $this->em->getRepository(Utilisateur::class)->findOneBy(['email' => 'lea@shortcut.test']) ?? throw new \LogicException();
        $service->confirmer($version, $estimation, $lea);

        return $version;
    }

    /** @return array<string, mixed> */
    private function get(string $jeton): array
    {
        $this->client->request('GET', '/api/propositions/'.$jeton);
        self::assertResponseIsSuccessful();

        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> */
    private function post(string $jeton, string $action): array
    {
        $this->client->request('POST', '/api/propositions/'.$jeton.'/'.$action);

        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
