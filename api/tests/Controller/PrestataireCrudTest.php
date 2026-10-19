<?php

namespace App\Tests\Controller;

use App\Entity\Destination;
use App\Entity\Prestataire;
use App\Entity\Utilisateur;
use App\Enum\TypePrestataire;
use Doctrine\Bundle\FixturesBundle\Loader\SymfonyFixturesLoader;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * CRUD des prestataires du backoffice (#187) : type obligatoire, fourchettes en euros, filtres. Nécessite `make db-test`.
 */
final class PrestataireCrudTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();

        /** @var SymfonyFixturesLoader $loader */
        $loader = static::getContainer()->get('doctrine.fixtures.loader');
        (new ORMExecutor($this->em, new ORMPurger($this->em)))->execute($loader->getFixtures());
        $this->em->clear();

        $this->client->loginUser($this->compte('lea@shortcut.test'));
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testLeTypeEstObligatoire(): void
    {
        $this->envoyerNouveau(['nom' => 'Croisières du Tage', 'type' => '']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('#Prestataire_type.is-invalid');
        self::assertNull($this->em->getRepository(Prestataire::class)->findOneBy(['nom' => 'Croisières du Tage']));
    }

    public function testUnTransporteurSeCreeAvecSesFourchettesEnEuros(): void
    {
        $this->envoyerNouveau(
            ['nom' => 'Tejo Transfers', 'type' => 'transporteur', 'contactNom' => 'Rui Costa', 'email' => 'rui@tejo.example'],
            [['depart' => 'Aéroport de Lisbonne', 'arrivee' => 'Lisbonne', 'minCentimes' => '18,50', 'maxCentimes' => '42']],
        );
        self::assertResponseRedirects();

        $transporteur = $this->em->getRepository(Prestataire::class)->findOneBy(['nom' => 'Tejo Transfers']);
        self::assertNotNull($transporteur);
        self::assertSame(TypePrestataire::Transporteur, $transporteur->getType());
        self::assertSame(
            [['depart' => 'Aéroport de Lisbonne', 'arrivee' => 'Lisbonne', 'minCentimes' => 1850, 'maxCentimes' => 4200]],
            $transporteur->getFourchettesTransport(),
        );

        $this->client->request('GET', '/admin/prestataire/'.$transporteur->getId());
        self::assertSelectorTextContains('body', '18,50 € à 42,00 €');
    }

    public function testLesFourchettesSontReserveesAuxTransporteursEtCoherentes(): void
    {
        $this->envoyerNouveau(
            ['nom' => 'Hôtel Bairro', 'type' => 'hebergeur'],
            [['depart' => 'Paris', 'arrivee' => 'Lisbonne', 'minCentimes' => '100', 'maxCentimes' => '200']],
        );
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Seul un transporteur a des fourchettes de prix de transport.');

        $this->envoyerNouveau(
            ['nom' => 'Air Tage', 'type' => 'transporteur'],
            [['depart' => 'Paris', 'arrivee' => 'Lisbonne', 'minCentimes' => '300', 'maxCentimes' => '120']],
        );
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Le maximum de la fourchette doit être supérieur ou égal au minimum.');
    }

    public function testLesFiltresParTypeEtParDestination(): void
    {
        $this->client->request('GET', '/admin/prestataire', ['filters' => ['type' => ['comparison' => '=', 'value' => 'transporteur']]]);
        self::assertResponseIsSuccessful();
        $attendus = array_map(
            static fn (Prestataire $p): string => (string) $p->getNom(),
            $this->em->getRepository(Prestataire::class)->findBy(['type' => TypePrestataire::Transporteur]),
        );
        sort($attendus);
        self::assertSame($attendus, $this->nomsAffiches());

        $lisbonne = $this->em->getRepository(Destination::class)->findOneBy(['slug' => 'lisbonne']);
        self::assertNotNull($lisbonne);
        $attendus = [];
        foreach ($this->em->getRepository(Prestataire::class)->findAll() as $prestataire) {
            $surPlace = $prestataire->getPrestations()->exists(static fn ($i, $p): bool => $p->getDestination() === $lisbonne);
            $versLisbonne = \in_array('Lisbonne', array_column($prestataire->getFourchettesTransport(), 'arrivee'), true);
            if ($surPlace || $versLisbonne) {
                $attendus[] = (string) $prestataire->getNom();
            }
        }
        sort($attendus);

        $this->client->request('GET', '/admin/prestataire', ['filters' => ['destination' => $lisbonne->getId()]]);
        self::assertResponseIsSuccessful();
        self::assertSame($attendus, $this->nomsAffiches());
        self::assertContains('Autocars Méridien', $attendus);
        self::assertContains('Hôtels Azur Méditerranée', $attendus);
    }

    public function testLeFiltreDestinationTolereLesVariantesDeLaVille(): void
    {
        foreach (['Fado Bus' => 'lisbone', 'Tejo Shuttle' => 'LISBONNE (Portugal)', 'Roma Bus' => 'Roma'] as $nom => $arrivee) {
            $this->em->persist((new Prestataire())->setNom($nom)->setType(TypePrestataire::Transporteur)->setFourchettesTransport([
                ['depart' => 'Paris', 'arrivee' => $arrivee, 'minCentimes' => 10000, 'maxCentimes' => 20000],
            ]));
        }
        $this->em->flush();

        $lisbonne = $this->em->getRepository(Destination::class)->findOneBy(['slug' => 'lisbonne']);
        $rome = $this->em->getRepository(Destination::class)->findOneBy(['slug' => 'rome']);
        self::assertNotNull($lisbonne);
        self::assertNotNull($rome);

        $this->client->request('GET', '/admin/prestataire', ['filters' => ['destination' => $lisbonne->getId()]]);
        self::assertContains('Fado Bus', $this->nomsAffiches());
        self::assertContains('Tejo Shuttle', $this->nomsAffiches());
        self::assertNotContains('Roma Bus', $this->nomsAffiches());

        // « Roma » n'est pas « Rome » : pas de tolérance sur les noms de moins de 5 lettres.
        $this->client->request('GET', '/admin/prestataire', ['filters' => ['destination' => $rome->getId()]]);
        self::assertNotContains('Roma Bus', $this->nomsAffiches());
    }

    public function testLesVillesSontSuggereesALaSaisie(): void
    {
        $crawler = $this->client->request('GET', '/admin/prestataire/new');

        // Les lignes de fourchette sont créées depuis le prototype de la collection.
        $prototype = (string) $crawler->filter('[data-prototype]')->attr('data-prototype');
        self::assertStringContainsString('<datalist', $prototype);
        self::assertStringContainsString('value="Lisbonne"', $prototype);
        self::assertStringContainsString('value="Bordeaux"', $prototype);
    }

    public function testSeulLAdministrateurSupprime(): void
    {
        $prestataire = $this->em->getRepository(Prestataire::class)->findOneBy([]);
        self::assertNotNull($prestataire);

        $this->client->request('POST', '/admin/prestataire/'.$prestataire->getId().'/delete');
        self::assertResponseStatusCodeSame(403);
    }

    /**
     * Soumet le formulaire de création, avec des fourchettes ajoutées comme le ferait le bouton « Ajouter ».
     *
     * @param array<string, string>             $champs
     * @param list<array<string, string|float>> $fourchettes
     */
    private function envoyerNouveau(array $champs, array $fourchettes = []): void
    {
        $crawler = $this->client->request('GET', '/admin/prestataire/new');
        self::assertResponseIsSuccessful();

        $valeurs = $crawler->filter('form[name="Prestataire"]')->form()->getPhpValues();
        foreach ($champs as $champ => $valeur) {
            $valeurs['Prestataire'][$champ] = $valeur;
        }
        $valeurs['Prestataire']['fourchettesTransport'] = $fourchettes;
        $this->client->request('POST', '/admin/prestataire/new', $valeurs);
    }

    /** @return list<string> */
    private function nomsAffiches(): array
    {
        $ids = array_map('intval', $this->client->getCrawler()->filter('table.datagrid tbody tr[data-id]')->each(
            static fn ($ligne) => $ligne->attr('data-id'),
        ));
        $noms = array_map(
            fn (int $id): string => (string) $this->em->getRepository(Prestataire::class)->find($id)?->getNom(),
            $ids,
        );
        sort($noms);

        return $noms;
    }

    private function compte(string $email): Utilisateur
    {
        $compte = $this->em->getRepository(Utilisateur::class)->findOneBy(['email' => $email]);
        self::assertNotNull($compte);

        return $compte;
    }
}
