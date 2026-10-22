<?php

namespace App\Tests\Api;

use App\DataFixtures\Donnees\Catalogue;
use App\Entity\Destination;
use App\Entity\Prestataire;
use App\Entity\Prestation;
use App\Entity\TarifParPeriode;
use App\Entity\Utilisateur;
use App\Enum\TypePrestation;
use Doctrine\Bundle\FixturesBundle\Loader\SymfonyFixturesLoader;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Catalogue exposé avec API Platform (#185) : droits, filtres, validation par champ et documentation OpenAPI,
 * sur le jeu de démo. Nécessite `make db-test`.
 */
final class CatalogueApiTest extends WebTestCase
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

    public function testLeCatalogueEstReserveAuxConseillers(): void
    {
        $this->client->request('GET', '/api/destinations');
        self::assertResponseStatusCodeSame(401);

        $this->client->loginUser($this->compte('client@shortcut.test'));
        foreach (['/api/destinations', '/api/prestataires', '/api/logements', '/api/activites', '/api/tarifs'] as $uri) {
            $this->client->request('GET', $uri);
            self::assertResponseStatusCodeSame(403, $uri);
        }
    }

    public function testSeulLAdministrateurSupprimeUneFiche(): void
    {
        $iri = '/api/destinations/'.$this->destination('lisbonne')->getId();

        $this->client->loginUser($this->compte('lea@shortcut.test'));
        $this->client->request('DELETE', $iri);
        self::assertResponseStatusCodeSame(403);

        $this->client->loginUser($this->compte('admin@shortcut.test'));
        $this->client->request('DELETE', $iri);
        self::assertResponseStatusCodeSame(204);
    }

    public function testLeFiltreClimatRenvoieLesDestinationsDuMoisEtDeLaTemperature(): void
    {
        $attendues = [];
        foreach (Catalogue::destinations() as $donnees) {
            if ($donnees['temperatures'][4] >= 25) {
                $attendues[] = $donnees['ville'];
            }
        }
        sort($attendues);
        self::assertNotEmpty($attendues);

        $villes = array_column($this->liste('/api/destinations?mois='.Catalogue::ANNEE.'-05&temperatureMin=25'), 'ville');
        sort($villes);
        self::assertSame($attendues, $villes);

        // Sans mois, la température porte sur n'importe quelle période : l'Islande n'atteint jamais 25 °C.
        self::assertNotContains('Reykjavik', array_column($this->liste('/api/destinations?temperatureMin=25'), 'ville'));
    }

    public function testLesFiltresDesLogementsNeRenvoientQueDesFichesCompatibles(): void
    {
        $lisbonne = $this->destination('lisbonne');
        $attendus = array_values(array_map(
            static fn (Prestation $p): string => (string) $p->getNom(),
            array_filter(
                $lisbonne->getPrestations()->toArray(),
                static fn (Prestation $p): bool => TypePrestation::Logement === $p->getType() && $p->isAccessible() && $p->getCapacite() >= 2,
            ),
        ));
        sort($attendus);
        self::assertNotEmpty($attendus);

        $logements = $this->liste('/api/logements?destinationSlug=lisbonne&accessible=true&capacite[gte]=2');
        $noms = array_column($logements, 'nom');
        sort($noms);
        self::assertSame($attendus, $noms);
        foreach ($logements as $logement) {
            self::assertSame('logement', $logement['type']);
            self::assertSame('/api/destinations/'.$lisbonne->getId(), $logement['destination']);
        }

        foreach ($this->liste('/api/activites?envie=sport') as $activite) {
            self::assertContains('sport', $activite['envies']);
        }
        foreach ($this->liste('/api/activites?ageMinimum[lte]=8') as $activite) {
            self::assertLessThanOrEqual(8, $activite['ageMinimum'] ?? 0);
        }
    }

    public function testLeFiltreMoisEcarteLesPrestationsSansTarif(): void
    {
        // Jeu de démo : aucun tarif en Islande d'octobre à avril.
        self::assertSame([], $this->liste('/api/logements?destinationSlug=islande&mois='.Catalogue::ANNEE.'-01'));
        self::assertNotEmpty($this->liste('/api/logements?destinationSlug=islande&mois='.Catalogue::ANNEE.'-07'));

        // Cabinas Drake Bay : fermées en septembre et octobre.
        $septembre = array_column($this->liste('/api/logements?destinationSlug=costa-rica&mois='.Catalogue::ANNEE.'-09'), 'nom');
        self::assertNotEmpty($septembre);
        self::assertNotContains('Cabinas Drake Bay', $septembre);

        foreach ($this->liste('/api/tarifs?mois='.Catalogue::ANNEE.'-02') as $tarif) {
            self::assertLessThanOrEqual(Catalogue::ANNEE.'-02-28', $tarif['debut']);
            self::assertGreaterThanOrEqual(Catalogue::ANNEE.'-02-01', $tarif['fin']);
        }

        $this->client->request('GET', '/api/logements?mois=juillet');
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['mois'], array_column($this->json()['violations'], 'propertyPath'));
    }

    public function testLeFiltreEnvieDesDestinationsPasseParLeursPrestations(): void
    {
        $attendues = [];
        foreach ($this->em->getRepository(Destination::class)->findAll() as $destination) {
            foreach ($destination->getPrestations() as $prestation) {
                if (\in_array('plage', $prestation->getEnvies(), true)) {
                    $attendues[] = $destination->getVille();
                    break;
                }
            }
        }
        sort($attendues);
        self::assertNotEmpty($attendues);

        $villes = array_column($this->liste('/api/destinations?envie=Plage'), 'ville');
        sort($villes);
        self::assertSame($attendues, $villes);
    }

    public function testLeFiltreEnvieTolereLesVariantes(): void
    {
        $detente = $this->liste('/api/activites?envie=detente');
        self::assertNotEmpty($detente);
        foreach ($detente as $activite) {
            self::assertContains('détente', $activite['envies']);
        }
        self::assertSame(array_column($this->liste('/api/logements?envie=plage'), 'id'), array_column($this->liste('/api/logements?envie=Plages'), 'id'));
        self::assertSame([], $this->liste('/api/logements?envie=karaoké'));
    }

    public function testLesEnviesEnregistreesSontHarmonisees(): void
    {
        self::assertSame(['culture', 'détente'], (new Prestation())->setEnvies([' Culture', 'culture', 'Détente ', ''])->getEnvies());
    }

    public function testLogementsEtActivitesOntChacunLeurAdresse(): void
    {
        $this->connecterConseiller();
        $this->client->request('GET', '/api/logements', server: ['HTTP_ACCEPT' => 'application/ld+json']);
        self::assertSame(\count(Catalogue::logements()), $this->json()['totalItems']);
        $this->client->request('GET', '/api/activites', server: ['HTTP_ACCEPT' => 'application/ld+json']);
        self::assertSame(\count(Catalogue::activites()), $this->json()['totalItems']);

        $activite = $this->em->getRepository(Prestation::class)->findOneBy(['type' => TypePrestation::Activite]);
        self::assertNotNull($activite);
        $this->client->request('GET', '/api/logements/'.$activite->getId());
        self::assertResponseStatusCodeSame(404);

        $tarif = $activite->getTarifs()->first();
        self::assertInstanceOf(TarifParPeriode::class, $tarif);
        $this->client->request('GET', '/api/tarifs/'.$tarif->getId(), server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertSame('/api/activites/'.$activite->getId(), $this->json()['prestation']);
    }

    public function testLesDonneesInternesRestentSeparees(): void
    {
        $logement = $this->liste('/api/logements?destinationSlug=santorin')[0];

        // Le prestataire n'est qu'un lien : ses contacts ne sortent que par /api/prestataires.
        self::assertIsString($logement['prestataire']);
        self::assertArrayNotHasKey('email', $logement);
        // Les tarifs d'une fiche ne répètent pas la prestation.
        self::assertArrayNotHasKey('prestation', $logement['tarifs'][0]);

        $destination = $this->liste('/api/destinations?slug=santorin')[0];
        self::assertArrayHasKey('publiee', $destination);
        self::assertArrayNotHasKey('prestations', $destination);
        self::assertCount(12, $destination['periodesClimatiques']);
        $champs = array_filter(array_keys($destination['periodesClimatiques'][0]), static fn (string $champ): bool => !str_starts_with($champ, '@'));
        self::assertSame(['debut', 'fin', 'temperatureMoyenne', 'pluieMm'], array_values($champs));
    }

    public function testUneEntreeInvalideEstRefuseeAvecUnMessageParChamp(): void
    {
        $this->connecterConseiller();

        $this->envoyer('POST', '/api/logements', ['nom' => '', 'capacite' => 0, 'dureeMinutes' => 60, 'photos' => ['']]);
        self::assertResponseStatusCodeSame(422);
        $violations = $this->violations();
        foreach (['nom', 'destination', 'prestataire', 'capacite', 'dureeMinutes', 'photos[0]'] as $champ) {
            self::assertArrayHasKey($champ, $violations, $champ);
            self::assertNotSame('', $violations[$champ]);
        }
        self::assertSame('Indiquez le nom.', $violations['nom']);

        $this->envoyer('POST', '/api/activites', ['nom' => 'Plongée', 'capacite' => 'beaucoup']);
        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('capacite', $this->violations());

        $this->envoyer('POST', '/api/destinations', ['pays' => 'Italie', 'ville' => 'Naples', 'slug' => 'Naples !', 'latitude' => 40.8, 'longitude' => 14.3]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['slug'], array_keys($this->violations()));

        // Sans position, pas d'épingle sur la carte de l'accueil.
        $this->envoyer('POST', '/api/destinations', ['pays' => 'Italie', 'ville' => 'Naples', 'slug' => 'naples']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['latitude', 'longitude'], array_keys($this->violations()));

        $activite = $this->em->getRepository(Prestation::class)->findOneBy(['type' => TypePrestation::Activite]);
        self::assertNotNull($activite);
        $this->envoyer('POST', '/api/tarifs', [
            'prestation' => '/api/activites/'.$activite->getId(),
            'debut' => '2028-01-01', 'fin' => '2027-12-31', 'prixCentimes' => -1, 'unite' => 'par_nuit',
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['fin', 'prixCentimes', 'unite'], array_keys($this->violations()));
    }

    public function testUnConseillerCreeUnLogementPuisSonTarif(): void
    {
        $lea = $this->connecterConseiller();
        $destination = $this->destination('lisbonne');
        $prestataire = $this->em->getRepository(Prestataire::class)->findOneBy([]);
        self::assertNotNull($prestataire);

        $this->envoyer('POST', '/api/logements', [
            'nom' => 'Casa do Tejo', 'type' => 'activite', 'capacite' => 4, 'accessible' => true,
            'destination' => '/api/destinations/'.$destination->getId(),
            'prestataire' => '/api/prestataires/'.$prestataire->getId(),
            'envies' => ['culture'],
        ]);
        self::assertResponseStatusCodeSame(201);
        $logement = $this->json();
        // Le type vient de l'adresse, pas du contenu envoyé.
        self::assertSame('logement', $logement['type']);
        self::assertSame('/api/logements/'.$logement['id'], $logement['@id']);

        $this->envoyer('POST', '/api/tarifs', [
            'prestation' => $logement['@id'], 'debut' => '2027-05-01', 'fin' => '2027-05-31', 'prixCentimes' => 9500, 'sejourMinimumNuits' => 2,
        ]);
        self::assertResponseStatusCodeSame(201);
        self::assertSame('par_nuit', $this->json()['unite']);

        $tarif = $this->em->getRepository(TarifParPeriode::class)->find($this->json()['id']);
        self::assertSame($lea->getId(), $tarif?->getSaisiPar()?->getId());

        $this->client->request('PATCH', $logement['@id'], server: ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json'], content: '{"capacite": 6}');
        self::assertResponseIsSuccessful();
        self::assertSame(6, $this->json()['capacite']);
    }

    public function testLeJeuDeDemoRespecteLesContraintes(): void
    {
        /** @var ValidatorInterface $validator */
        $validator = static::getContainer()->get(ValidatorInterface::class);
        foreach ([Destination::class, Prestataire::class, Prestation::class, TarifParPeriode::class] as $classe) {
            foreach ($this->em->getRepository($classe)->findAll() as $fiche) {
                $violations = $validator->validate($fiche);
                self::assertCount(0, $violations, $classe.' : '.$violations);
            }
        }
    }

    public function testLaDocumentationOpenApiDecritLesRessourcesEtLesFiltres(): void
    {
        $this->client->request('GET', '/api/docs.jsonopenapi');
        self::assertResponseIsSuccessful();
        $paths = $this->json()['paths'];

        foreach (['/api/destinations', '/api/prestataires', '/api/logements', '/api/activites', '/api/tarifs'] as $uri) {
            self::assertArrayHasKey($uri, $paths);
        }
        $filtres = static fn (string $uri): array => array_column($paths[$uri]['get']['parameters'], 'name');
        self::assertContains('temperatureMin', $filtres('/api/destinations'));
        self::assertContains('capacite[gte]', $filtres('/api/logements'));
        self::assertContains('mois', $filtres('/api/activites'));
    }

    private function connecterConseiller(): Utilisateur
    {
        $lea = $this->compte('lea@shortcut.test');
        $this->client->loginUser($lea);

        return $lea;
    }

    /**
     * Membres d'une collection, en tant que conseiller.
     *
     * @return list<array<string, mixed>>
     */
    private function liste(string $uri): array
    {
        $this->connecterConseiller();
        $this->client->request('GET', $uri, server: ['HTTP_ACCEPT' => 'application/ld+json']);
        self::assertResponseIsSuccessful($uri);

        return $this->json()['member'];
    }

    /**
     * @param array<string, mixed> $donnees
     */
    private function envoyer(string $methode, string $uri, array $donnees): void
    {
        $this->client->request($methode, $uri, server: [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], content: json_encode($donnees, \JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    private function json(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * Message de la première erreur de chaque champ.
     *
     * @return array<string, string>
     */
    private function violations(): array
    {
        $parChamp = [];
        foreach ($this->json()['violations'] as $violation) {
            $parChamp[$violation['propertyPath']] ??= $violation['message'];
        }
        ksort($parChamp);

        return $parChamp;
    }

    private function compte(string $email): Utilisateur
    {
        $compte = $this->em->getRepository(Utilisateur::class)->findOneBy(['email' => $email]);
        self::assertNotNull($compte);

        return $compte;
    }

    private function destination(string $slug): Destination
    {
        $destination = $this->em->getRepository(Destination::class)->findOneBy(['slug' => $slug]);
        self::assertNotNull($destination);

        return $destination;
    }
}
