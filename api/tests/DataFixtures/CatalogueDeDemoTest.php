<?php

namespace App\Tests\DataFixtures;

use App\DataFixtures\Donnees\Catalogue;
use App\Entity\Destination;
use App\Entity\Prestataire;
use App\Entity\Prestation;
use App\Entity\Utilisateur;
use App\Enum\TypeDeVoyage;
use App\Enum\TypePrestataire;
use App\Enum\TypePrestation;
use Doctrine\Bundle\FixturesBundle\Loader\SymfonyFixturesLoader;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Critères d'acceptation du jeu de démo (ticket #174), sur la base de test.
 * Nécessite la base de test : `make db-test`.
 */
final class CatalogueDeDemoTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();

        /** @var SymfonyFixturesLoader $loader */
        $loader = self::getContainer()->get('doctrine.fixtures.loader');
        (new ORMExecutor($this->em, new ORMPurger($this->em)))->execute($loader->getFixtures());
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testLeCatalogueContientLesVolumesPrevus(): void
    {
        self::assertCount(8, $this->em->getRepository(Destination::class)->findAll());
        self::assertCount(15, $this->em->getRepository(Prestataire::class)->findAll());
        self::assertCount(30, $this->logements());
        self::assertCount(20, $this->em->getRepository(Prestation::class)->findBy(['type' => TypePrestation::Activite]));
        self::assertCount(4, $this->em->getRepository(Utilisateur::class)->findAll());

        foreach ($this->em->getRepository(Destination::class)->findAll() as $destination) {
            self::assertCount(12, $destination->getPeriodesClimatiques(), $destination->getVille() ?? '');
            self::assertNotNull($destination->getPhoto());
            self::assertNotNull($destination->getDescription());
        }
    }

    public function testChaqueDestinationADesFourchettesDeVolDepuisLesTroisVilles(): void
    {
        $vols = [];
        foreach ($this->em->getRepository(Prestataire::class)->findBy(['type' => TypePrestataire::Transporteur]) as $transporteur) {
            foreach ($transporteur->getFourchettesTransport() as $f) {
                $vols[$f['arrivee']][] = $f['depart'];
            }
        }

        foreach ($this->em->getRepository(Destination::class)->findAll() as $destination) {
            foreach (Catalogue::VILLES_DE_DEPART as $ville) {
                self::assertContains($ville, $vols[$destination->getVille()] ?? [], (string) $destination->getVille());
            }
        }
    }

    public function testChaqueTypeDeVoyageAUneCombinaisonPossible(): void
    {
        $logements = $this->logements();
        $avecEquipement = fn (string $mot): array => array_filter(
            $logements,
            fn (Prestation $p): bool => [] !== array_filter($p->getEquipements(), fn (string $e): bool => str_contains($e, $mot)),
        );

        $combinaisons = [
            TypeDeVoyage::Sejour->value => array_filter($logements, fn (Prestation $p): bool => \in_array('plage', $p->getEnvies(), true)),
            TypeDeVoyage::Groupe->value => array_filter($logements, fn (Prestation $p): bool => $p->getCapacite() >= 20),
            TypeDeVoyage::Noces->value => array_filter($logements, fn (Prestation $p): bool => 2 === $p->getCapacite() && \in_array('romantique', $p->getEnvies(), true)),
            TypeDeVoyage::Entreprise->value => $avecEquipement('salle de réunion'),
            // Circuit : au moins trois hébergements et un guide dans la même destination.
            TypeDeVoyage::Circuit->value => array_filter(
                $this->em->getRepository(Destination::class)->findAll(),
                fn (Destination $d): bool => \count(array_filter($d->getPrestations()->toArray(), fn (Prestation $p): bool => TypePrestation::Logement === $p->getType())) >= 3
                    && [] !== array_filter($d->getPrestations()->toArray(), fn (Prestation $p): bool => TypePrestataire::Guide === $p->getPrestataire()?->getType()),
            ),
        ];

        foreach (TypeDeVoyage::cases() as $type) {
            self::assertNotEmpty($combinaisons[$type->value], $type->label());
        }
        foreach ($combinaisons as $type => $resultats) {
            if (TypeDeVoyage::Circuit->value === $type) {
                continue;
            }
            foreach ($resultats as $logement) {
                self::assertNotEmpty($logement->getTarifs(), $type.' : '.$logement->getNom());
            }
        }
    }

    public function testDesPeriodesSontVolontairementNonCouvertes(): void
    {
        $islande = $this->em->getRepository(Destination::class)->findOneBy(['slug' => 'islande']);
        self::assertNotNull($islande);

        $janvier = new \DateTimeImmutable(Catalogue::ANNEE.'-01-15');
        foreach ($islande->getPrestations() as $prestation) {
            foreach ($prestation->getTarifs() as $tarif) {
                self::assertFalse($tarif->getDebut() <= $janvier && $janvier <= $tarif->getFin(), (string) $prestation->getNom());
            }
        }
    }

    /**
     * @return list<Prestation>
     */
    private function logements(): array
    {
        return $this->em->getRepository(Prestation::class)->findBy(['type' => TypePrestation::Logement]);
    }
}
