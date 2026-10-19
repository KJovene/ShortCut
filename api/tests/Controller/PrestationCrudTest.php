<?php

namespace App\Tests\Controller;

use App\Entity\Destination;
use App\Entity\Prestataire;
use App\Entity\Prestation;
use App\Entity\Utilisateur;
use App\Enum\TypePrestataire;
use App\Enum\TypePrestation;
use App\Enum\UniteTarif;
use Doctrine\Bundle\FixturesBundle\Loader\SymfonyFixturesLoader;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * CRUD des logements et activités du backoffice (#188) : photos, tarifs, caractéristiques par type.
 * Nécessite `make db-test`.
 */
final class PrestationCrudTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Utilisateur $lea;
    private string $dossierPhotos;
    /** @var list<string> */
    private array $photosAvant;

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

        $this->dossierPhotos = static::getContainer()->getParameter('kernel.project_dir').'/public/uploads/prestations';
        $this->photosAvant = scandir($this->dossierPhotos) ?: [];

        $lea = $this->em->getRepository(Utilisateur::class)->findOneBy(['email' => 'lea@shortcut.test']);
        self::assertNotNull($lea);
        $this->lea = $lea;
        $this->client->loginUser($lea);
    }

    protected function tearDown(): void
    {
        // Les photos téléversées par les tests ne restent pas sur le disque.
        foreach (array_diff(scandir($this->dossierPhotos) ?: [], $this->photosAvant) as $fichier) {
            unlink($this->dossierPhotos.'/'.$fichier);
        }
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testChaqueListeNeMontreQueSonType(): void
    {
        $this->client->request('GET', '/admin/logement');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', '30 résultats');

        $this->client->request('GET', '/admin/activite');
        self::assertSelectorTextContains('body', '20 résultats');

        $activite = $this->em->getRepository(Prestation::class)->findOneBy(['type' => TypePrestation::Activite]);
        self::assertNotNull($activite);
        $this->client->request('GET', '/admin/logement/'.$activite->getId());
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/admin/activite/'.$activite->getId());
        self::assertResponseIsSuccessful();
    }

    public function testUnLogementSeCreeAvecTroisPhotosEtSesTarifs(): void
    {
        $valeurs = $this->formulaire('/admin/logement/new');
        $valeurs['Prestation'] = array_merge($valeurs['Prestation'], [
            'nom' => 'Casa Alfama, appartement',
            'destination' => (string) $this->destination('lisbonne')->getId(),
            'prestataire' => (string) $this->prestataire(TypePrestataire::Hebergeur)->getId(),
            'categorie' => 'Appartement',
            'capacite' => '4',
            'chambres' => '2',
            'accessible' => '1',
            'equipements' => ['wifi', 'cuisine équipée'],
            'envies' => ['Culture', 'famille'],
            'tarifs' => [
                ['debut' => '2027-04-01', 'fin' => '2027-06-30', 'prixCentimes' => '120,50', 'sejourMinimumNuits' => '2'],
                ['debut' => '2027-07-01', 'fin' => '2027-08-31', 'prixCentimes' => '160'],
            ],
        ]);
        $this->client->request('POST', '/admin/logement/new', $valeurs, ['Prestation' => ['photos' => ['file' => [
            $this->photo('hotel-chambre'), $this->photo('appartement'), $this->photo('riad'),
        ]]]]);
        self::assertResponseRedirects();

        $logement = $this->em->getRepository(Prestation::class)->findOneBy(['nom' => 'Casa Alfama, appartement']);
        self::assertNotNull($logement);
        self::assertSame(TypePrestation::Logement, $logement->getType());
        self::assertSame(2, $logement->getChambres());
        self::assertSame(['culture', 'famille'], $logement->getEnvies());

        self::assertCount(3, $logement->getPhotos());
        foreach ($logement->getPhotos() as $photo) {
            self::assertFileExists($this->dossierPhotos.'/'.$photo);
        }

        $tarifs = $logement->getTarifs()->toArray();
        self::assertCount(2, $tarifs);
        self::assertSame([12050, 16000], array_map(static fn ($t) => $t->getPrixCentimes(), $tarifs));
        self::assertSame(UniteTarif::ParNuit, $tarifs[0]->getUnite());
        self::assertSame(2, $tarifs[0]->getSejourMinimumNuits());
        self::assertSame($this->lea->getId(), $tarifs[0]->getSaisiPar()?->getId());

        // La fiche montre les trois photos (servies sous /uploads/prestations) et les tarifs.
        $crawler = $this->client->request('GET', '/admin/logement/'.$logement->getId());
        self::assertCount(3, $crawler->filter('.ea-lightbox-thumbnail img[src*="/uploads/prestations/"]'));
        self::assertSelectorTextContains('body', '120,50 € par nuit');
    }

    public function testUnePhotoAjouteeEnModificationSAjouteAuxAutres(): void
    {
        $logement = $this->em->getRepository(Prestation::class)->findOneBy(['nom' => 'Riad Dar Yasmine, riad entier']);
        self::assertNotNull($logement);
        $avant = $logement->getPhotos();

        $uri = '/admin/logement/'.$logement->getId().'/edit';
        $this->client->request('POST', $uri, $this->formulaire($uri), ['Prestation' => ['photos' => ['file' => [$this->photo('hotel-piscine')]]]]);
        self::assertResponseRedirects();

        $this->em->clear();
        $apres = $this->em->getRepository(Prestation::class)->find($logement->getId())?->getPhotos() ?? [];
        self::assertCount(\count($avant) + 1, $apres);
        self::assertSame($avant, \array_slice($apres, 0, \count($avant)));
    }

    public function testUneActiviteSeTarifeParPersonneEtExigeUneDuree(): void
    {
        $valeurs = $this->formulaire('/admin/activite/new');
        $valeurs['Prestation'] = array_merge($valeurs['Prestation'], [
            'nom' => 'Balade en tuk-tuk',
            'destination' => (string) $this->destination('lisbonne')->getId(),
            'prestataire' => (string) $this->prestataire(TypePrestataire::Guide)->getId(),
            'capacite' => '6',
            'ageMinimum' => '6',
            'tarifs' => [['debut' => '2027-01-01', 'fin' => '2027-12-31', 'prixCentimes' => '35']],
        ]);
        $this->client->request('POST', '/admin/activite/new', $valeurs);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Indiquez la durée de l\'activité, en minutes.');

        $valeurs['Prestation']['dureeMinutes'] = '90';
        $this->client->request('POST', '/admin/activite/new', $valeurs);
        self::assertResponseRedirects();

        $activite = $this->em->getRepository(Prestation::class)->findOneBy(['nom' => 'Balade en tuk-tuk']);
        self::assertNotNull($activite);
        self::assertSame(TypePrestation::Activite, $activite->getType());
        $tarif = $activite->getTarifs()->first();
        self::assertNotFalse($tarif);
        self::assertSame(UniteTarif::ParPersonne, $tarif->getUnite());
        self::assertSame(3500, $tarif->getPrixCentimes());
    }

    public function testLeChampPrestataireNeProposeQueLesBonsMetiers(): void
    {
        $crawler = $this->client->request('GET', '/admin/logement/new');
        $proposes = $crawler->filter('select[name="Prestation[prestataire]"] option[value!=""]')->each(static fn ($o) => $o->text());
        $hebergeurs = array_map(
            static fn (Prestataire $p) => (string) $p->getNom(),
            $this->em->getRepository(Prestataire::class)->findBy(['type' => TypePrestataire::Hebergeur]),
        );
        sort($proposes);
        sort($hebergeurs);
        self::assertSame($hebergeurs, $proposes);
    }

    public function testLApiExposeLesChambres(): void
    {
        $this->client->request('GET', '/api/logements?chambres[gte]=6', server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();
        $logements = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertNotEmpty($logements);
        foreach ($logements as $logement) {
            self::assertGreaterThanOrEqual(6, $logement['chambres']);
        }
    }

    /**
     * Valeurs du formulaire vide (dont le jeton CSRF).
     *
     * @return array<string, mixed>
     */
    private function formulaire(string $uri): array
    {
        $crawler = $this->client->request('GET', $uri);
        self::assertResponseIsSuccessful();

        return $crawler->filter('form[name="Prestation"]')->form()->getPhpValues();
    }

    /** Copie d'une photo du jeu de démo, envoyée comme un fichier téléversé. */
    private function photo(string $nom): UploadedFile
    {
        $source = static::getContainer()->getParameter('kernel.project_dir').'/fixtures/images/prestations/'.$nom.'.jpg';
        $copie = tempnam(sys_get_temp_dir(), 'photo');
        copy($source, $copie);

        return new UploadedFile($copie, $nom.'.jpg', 'image/jpeg', null, true);
    }

    private function destination(string $slug): Destination
    {
        $destination = $this->em->getRepository(Destination::class)->findOneBy(['slug' => $slug]);
        self::assertNotNull($destination);

        return $destination;
    }

    private function prestataire(TypePrestataire $type): Prestataire
    {
        $prestataire = $this->em->getRepository(Prestataire::class)->findOneBy(['type' => $type]);
        self::assertNotNull($prestataire);

        return $prestataire;
    }
}
