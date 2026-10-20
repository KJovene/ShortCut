<?php

namespace App\Tests\Api;

use App\Chiffrage\BudgetCalculator;
use App\Chiffrage\PrixAPartirDe;
use App\DataFixtures\Donnees\Catalogue;
use App\Entity\Destination;
use App\Entity\RegleDePrix;
use App\Enum\TypeDeVoyage;
use App\Enum\TypePrestation;
use Doctrine\Bundle\FixturesBundle\Loader\SymfonyFixturesLoader;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Lecture publique du catalogue par le site : destinations publiées seulement, sans connexion et sans
 * aucune donnée interne. Nécessite `make db-test`.
 */
final class CataloguePublicTest extends WebTestCase
{
    /** Champs réservés au backoffice : aucun ne doit apparaître, à aucun niveau, dans une réponse publique. */
    private const CHAMPS_INTERNES = ['id', 'publiee', 'prestataire', 'tarifs', 'prixCentimes', 'sejourMinimumNuits', 'destination', 'saisiPar', 'modifieLe', 'ordreMenu'];

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

    public function testUnVisiteurLitLesDestinationsPubliees(): void
    {
        $this->destination('lisbonne')->setPubliee(false);
        $this->em->flush();

        $slugs = array_column($this->lire('/api/public/destinations')['member'], 'slug');

        self::assertNotEmpty($slugs);
        self::assertNotContains('lisbonne', $slugs);
        self::assertSame(\count($this->em->getRepository(Destination::class)->findBy(['publiee' => true])), \count($slugs));
    }

    public function testUneDestinationNonPublieeOuInconnueRepond404(): void
    {
        $this->lire('/api/public/destinations/lisbonne');

        $this->destination('lisbonne')->setPubliee(false);
        $this->em->flush();

        $this->client->request('GET', '/api/public/destinations/lisbonne');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/api/public/destinations/inconnue');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAucunChampInterneNApparait(): void
    {
        foreach (['/api/public/destinations', '/api/public/destinations/lisbonne', '/api/pages/agence'] as $uri) {
            $cles = self::cles($this->lire($uri));
            foreach (self::CHAMPS_INTERNES as $champ) {
                self::assertNotContains($champ, $cles, \sprintf('%s expose « %s »', $uri, $champ));
            }
        }
    }

    public function testLaFicheDonneLeClimatEtDesExemplesAvecUnPrixDeVente(): void
    {
        $fiche = $this->lire('/api/public/destinations/lisbonne');

        self::assertSame('Lisbonne', $fiche['ville']);
        self::assertCount(12, $fiche['periodesClimatiques']);
        self::assertSame(['debut', 'fin', 'temperatureMoyenne', 'pluieMm'], array_values(array_filter(array_keys($fiche['periodesClimatiques'][0]), static fn (string $cle): bool => !str_starts_with($cle, '@'))));

        $marge = ($this->em->getRepository(RegleDePrix::class)->findOneBy([]) ?? new RegleDePrix())->getMargePourcentage();
        $lisbonne = $this->destination('lisbonne');
        foreach (TypePrestation::cases() as $type) {
            $exemples = array_values(array_filter($fiche['exemples'], static fn (array $e): bool => $type->value === $e['type']));
            self::assertNotEmpty($exemples, $type->value);
            self::assertLessThanOrEqual(PrixAPartirDe::EXEMPLES_PAR_TYPE, \count($exemples));

            $prix = array_column($exemples, 'prixAPartirDeEuros');
            $tries = $prix;
            sort($tries);
            self::assertSame($tries, $prix, 'Les exemples les moins chers d\'abord.');

            // Le premier exemple coûte le tarif d'achat le plus bas du type, marge comprise, arrondi à l'euro supérieur.
            $achats = [];
            foreach ($lisbonne->getPrestations() as $prestation) {
                if ($type === $prestation->getType()) {
                    foreach ($prestation->getTarifs() as $tarif) {
                        $achats[] = (int) $tarif->getPrixCentimes();
                    }
                }
            }
            $achat = min($achats);
            self::assertSame(intdiv($achat + BudgetCalculator::marge($achat, $marge) + 99, 100), $prix[0]);
            self::assertSame(TypePrestation::Logement === $type ? 'par_nuit' : 'par_personne', $exemples[0]['unite']);
        }
    }

    public function testLaFicheDonneCeQueLeSiteAffiche(): void
    {
        $fiche = $this->lire('/api/public/destinations/lisbonne');

        self::assertSame('Lisbonne, Portugal', $fiche['nom']);
        self::assertSame(['sejour', 'groupe', 'entreprise'], $fiche['typesDeVoyage']);
        self::assertSame(6, $fiche['moisIdeal']);
        self::assertSame([38.7, -9.1], [$fiche['latitude'], $fiche['longitude']]);
        self::assertNotEmpty($fiche['accroche']);
        self::assertNotEmpty($fiche['photoAlt']);
        self::assertMatchesRegularExpression('#^/uploads/destinations/.+#', $fiche['photo']);
        foreach ($fiche['exemples'] as $exemple) {
            foreach ($exemple['photos'] as $photo) {
                self::assertStringStartsWith('/uploads/prestations/', $photo);
            }
        }

        // Sans nom saisi, le site affiche la ville et le pays.
        $this->destination('lisbonne')->setNom(null);
        $this->em->flush();
        self::assertSame('Lisbonne, Portugal', $this->lire('/api/public/destinations/lisbonne')['nom']);
    }

    public function testLePrixParPersonneComprendSeptNuitsADeuxLeTransportLaMargeEtLesFrais(): void
    {
        $regle = $this->em->getRepository(RegleDePrix::class)->findOneBy([]) ?? new RegleDePrix();
        $lisbonne = $this->destination('lisbonne');

        $nuit = null;
        foreach ($lisbonne->getPrestations() as $prestation) {
            if (TypePrestation::Logement === $prestation->getType() && $prestation->getCapacite() >= 2) {
                foreach ($prestation->getTarifs() as $tarif) {
                    $nuit = min($nuit ?? (int) $tarif->getPrixCentimes(), (int) $tarif->getPrixCentimes());
                }
            }
        }
        self::assertNotNull($nuit);
        // Fixtures : vol Paris – Lisbonne dès 120 €, transferts dès 20 €, séjour (type principal).
        $cout = intdiv($nuit * 7, 2) + 12000 + 2000;
        $vente = $cout + BudgetCalculator::marge($cout, $regle->getMargePourcentage()) + intdiv($regle->getFraisDeDossierCentimes(TypeDeVoyage::Sejour), 2);

        self::assertSame((int) ceil($vente / 1000) * 10, $this->lire('/api/public/destinations/lisbonne')['prixAPartirDeParPersonneEuros']);

        // Sans type de voyage, pas de frais de dossier connus : pas de prix par personne.
        $lisbonne->setTypesDeVoyage([]);
        $this->em->flush();
        self::assertNull($this->lire('/api/public/destinations/lisbonne')['prixAPartirDeParPersonneEuros']);
    }

    public function testLeFiltreClimatFonctionneSurLaListePublique(): void
    {
        $attendues = [];
        foreach (Catalogue::destinations() as $donnees) {
            if ($donnees['temperatures'][4] >= 25) {
                $attendues[] = $donnees['ville'];
            }
        }
        sort($attendues);

        $villes = array_column($this->lire('/api/public/destinations?mois='.Catalogue::ANNEE.'-05&temperatureMin=25')['member'], 'ville');
        sort($villes);
        self::assertSame($attendues, $villes);
    }

    public function testLeCatalogueCompletResteReserveAuxConseillers(): void
    {
        foreach (['/api/destinations', '/api/logements', '/api/activites', '/api/prestataires', '/api/tarifs'] as $uri) {
            $this->client->request('GET', $uri);
            self::assertResponseStatusCodeSame(401, $uri);
        }
    }

    /**
     * Réponse d'une adresse publique, lue sans connexion.
     *
     * @return array<string, mixed>
     */
    private function lire(string $uri): array
    {
        $this->client->request('GET', $uri, server: ['HTTP_ACCEPT' => 'application/ld+json']);
        self::assertResponseIsSuccessful($uri);

        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * Toutes les clés d'une réponse, à tous les niveaux.
     *
     * @param array<mixed> $donnees
     *
     * @return list<string>
     */
    private static function cles(array $donnees): array
    {
        $cles = [];
        foreach ($donnees as $cle => $valeur) {
            if (\is_string($cle)) {
                $cles[] = $cle;
            }
            if (\is_array($valeur)) {
                $cles = [...$cles, ...self::cles($valeur)];
            }
        }

        return array_values(array_unique($cles));
    }

    private function destination(string $slug): Destination
    {
        $destination = $this->em->getRepository(Destination::class)->findOneBy(['slug' => $slug]);
        self::assertNotNull($destination);

        return $destination;
    }
}
