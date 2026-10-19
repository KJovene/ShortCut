<?php

namespace App\Tests\Controller;

use App\DataFixtures\Donnees\Catalogue;
use App\Entity\Destination;
use App\Entity\PeriodeClimatique;
use App\Entity\Utilisateur;
use Doctrine\Bundle\FixturesBundle\Loader\SymfonyFixturesLoader;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * CRUD des destinations du backoffice (#186) : création, filtre climat, droits. Nécessite `make db-test`.
 */
final class DestinationCrudTest extends WebTestCase
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
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testLeFiltreMaiAuMoins25DegresRenvoieLesBonnesDestinations(): void
    {
        $attendues = [];
        foreach (Catalogue::destinations() as $donnees) {
            if ($donnees['temperatures'][4] >= 25) {
                $attendues[] = $donnees['ville'];
            }
        }
        sort($attendues);
        self::assertNotEmpty($attendues);

        $this->client->loginUser($this->compte('lea@shortcut.test'));
        $this->client->request('GET', '/admin/destination', ['filters' => ['climat' => ['mois' => 5, 'temperatureMin' => 25]]]);
        self::assertResponseIsSuccessful();
        self::assertSame($attendues, $this->villesAffichees());

        // Sans filtre, les huit destinations du jeu de démo.
        $this->client->request('GET', '/admin/destination');
        self::assertCount(8, $this->villesAffichees());
    }

    public function testLeMoisValePourToutesLesAnnees(): void
    {
        // Une période à cheval sur deux années : de novembre 2027 à février 2028, 28 °C.
        $destination = (new Destination())->setPays('Australie')->setVille('Cairns')->setSlug('cairns');
        $destination->addPeriodeClimatique((new PeriodeClimatique())
            ->setDebut(new \DateTimeImmutable('2027-11-01'))
            ->setFin(new \DateTimeImmutable('2028-02-29'))
            ->setTemperatureMoyenne(28));
        $this->em->persist($destination);
        $this->em->persist($destination->getPeriodesClimatiques()->first() ?: throw new \LogicException());
        $this->em->flush();

        $this->client->loginUser($this->compte('lea@shortcut.test'));
        $this->client->request('GET', '/admin/destination', ['filters' => ['climat' => ['mois' => 1, 'temperatureMin' => 25]]]);
        self::assertContains('Cairns', $this->villesAffichees());

        $this->client->request('GET', '/admin/destination', ['filters' => ['climat' => ['mois' => 3, 'temperatureMin' => 25]]]);
        self::assertNotContains('Cairns', $this->villesAffichees());
    }

    public function testUnConseillerCreeUneDestination(): void
    {
        $this->client->loginUser($this->compte('lea@shortcut.test'));
        $crawler = $this->client->request('GET', '/admin/destination/new');
        self::assertResponseIsSuccessful();

        $formulaire = $crawler->filter('form[name="Destination"]')->form([
            'Destination[ville]' => 'Naples',
            'Destination[pays]' => 'Italie',
            'Destination[region]' => 'Campanie',
            'Destination[slug]' => 'naples',
            'Destination[description]' => 'Pizza, Vésuve et côte amalfitaine.',
        ]);
        // Les lignes de collection sont ajoutées par JavaScript dans le navigateur : on les envoie directement.
        $valeurs = $formulaire->getPhpValues();
        $valeurs['Destination']['trajets'] = [['ville' => 'Paris', 'minutes' => 135], ['ville' => 'Lyon', 'minutes' => 120]];
        $valeurs['Destination']['pointsForts'] = ['Côte amalfitaine', 'Pompéi'];
        $this->client->request('POST', $formulaire->getUri(), $valeurs);
        self::assertResponseRedirects();

        $naples = $this->em->getRepository(Destination::class)->findOneBy(['slug' => 'naples']);
        self::assertNotNull($naples);
        self::assertSame(['Paris' => 135, 'Lyon' => 120], $naples->getTempsDeTrajet());
        self::assertSame(['Côte amalfitaine', 'Pompéi'], $naples->getPointsForts());
        self::assertFalse($naples->isPubliee());
    }

    public function testUneSaisieInvalideAfficheLErreurSurLeChamp(): void
    {
        $this->client->loginUser($this->compte('lea@shortcut.test'));
        $crawler = $this->client->request('GET', '/admin/destination/new');
        $valeurs = $crawler->filter('form[name="Destination"]')->form([
            'Destination[ville]' => 'Lisbonne bis',
            'Destination[pays]' => 'Portugal',
            'Destination[slug]' => 'lisbonne',
        ])->getPhpValues();
        $valeurs['Destination']['trajets'] = [['ville' => 'Paris', 'minutes' => 0]];
        $this->client->request('POST', '/admin/destination/new', $valeurs);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Cette valeur est déjà utilisée.');
        self::assertSelectorExists('#Destination_trajets_0_minutes.is-invalid');
    }

    public function testLaFicheMontreLeClimatEtLeLienDeSaisie(): void
    {
        $lisbonne = $this->em->getRepository(Destination::class)->findOneBy(['slug' => 'lisbonne']);
        self::assertNotNull($lisbonne);

        $this->client->loginUser($this->compte('lea@shortcut.test'));
        $crawler = $this->client->request('GET', '/admin/destination/'.$lisbonne->getId());
        self::assertResponseIsSuccessful();
        self::assertCount(12, $crawler->filter('table.bo-tableau tbody tr'));
        self::assertSelectorTextContains('body', 'Paris : 2 h 30');
        self::assertSelectorExists('a[href$="/admin/destinations/'.$lisbonne->getId().'/periode"]');
    }

    public function testSeulLAdministrateurSupprime(): void
    {
        $lisbonne = $this->em->getRepository(Destination::class)->findOneBy(['slug' => 'lisbonne']);
        self::assertNotNull($lisbonne);

        $this->client->loginUser($this->compte('lea@shortcut.test'));
        $this->client->request('POST', '/admin/destination/'.$lisbonne->getId().'/delete');
        self::assertResponseStatusCodeSame(403);

        $this->client->loginUser($this->compte('client@shortcut.test'));
        $this->client->request('GET', '/admin/destination');
        self::assertResponseStatusCodeSame(403);
    }

    /** @return list<string> */
    private function villesAffichees(): array
    {
        $ids = array_map('intval', $this->client->getCrawler()->filter('table.datagrid tbody tr[data-id]')->each(
            static fn ($ligne) => $ligne->attr('data-id'),
        ));
        $villes = array_map(
            fn (int $id): string => (string) $this->em->getRepository(Destination::class)->find($id)?->getVille(),
            $ids,
        );
        sort($villes);

        return $villes;
    }

    private function compte(string $email): Utilisateur
    {
        $compte = $this->em->getRepository(Utilisateur::class)->findOneBy(['email' => $email]);
        self::assertNotNull($compte);

        return $compte;
    }
}
