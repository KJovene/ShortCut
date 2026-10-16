<?php

namespace App\Tests\Entity;

use App\Entity\Conversation;
use App\Entity\Demande;
use App\Entity\Destination;
use App\Entity\LigneDePrestation;
use App\Entity\PeriodeClimatique;
use App\Entity\Prestataire;
use App\Entity\Prestation;
use App\Entity\Proposition;
use App\Entity\TarifParPeriode;
use App\Entity\Utilisateur;
use App\Enum\NiveauBudget;
use App\Enum\Role;
use App\Enum\StatutDemande;
use App\Enum\TypeDeVoyage;
use App\Enum\TypeLigne;
use App\Enum\TypePrestataire;
use App\Enum\TypePrestation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Vérifie que le schéma accepte une demande complète, du catalogue à la proposition.
 * Nécessite la base de test : `make db-test`.
 */
final class ModeleDeDonneesTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        // Chaque test s'exécute dans une transaction annulée : la base de test reste vide.
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testUneDemandeCompleteEstEnregistreeAvecSesRelations(): void
    {
        $destination = (new Destination())->setPays('Portugal')->setVille('Lisbonne')->setSlug('lisbonne');
        $destination->addPeriodeClimatique(
            (new PeriodeClimatique())
                ->setDebut(new \DateTimeImmutable('2027-06-01'))
                ->setFin(new \DateTimeImmutable('2027-06-30'))
                ->setTemperatureMoyenne(26.5),
        );

        $prestataire = (new Prestataire())->setNom('Hôtel Tejo')->setType(TypePrestataire::Hebergeur);
        $logement = (new Prestation())
            ->setType(TypePrestation::Logement)
            ->setNom('Chambre double vue fleuve')
            ->setCapacite(2)
            ->setDestination($destination)
            ->setPrestataire($prestataire);
        $logement->addTarif(
            (new TarifParPeriode())
                ->setDebut(new \DateTimeImmutable('2027-06-01'))
                ->setFin(new \DateTimeImmutable('2027-06-30'))
                ->setPrixCentimes(12000),
        );

        $client = (new Utilisateur())->setRole(Role::Client)->setPrenom('Camille')->setNom('Durand')->setEmail('camille@example.com');
        $demande = (new Demande())
            ->setTypeDeVoyage(TypeDeVoyage::Sejour)
            ->setLieuOuZone('Lisbonne')
            ->setNombreDeJours(7)
            ->setAdultes(2)
            ->setBudgetCentimes(250000)
            ->setEnvies(['gastronomie', 'balades']);
        $demande->setClient($client);
        $demande->setConversation(new Conversation());

        $proposition = (new Proposition())
            ->setNiveauBudget(NiveauBudget::Equilibre)
            ->setTotalCentimes(240000)
            ->setFraisDeDossierCentimes(15000);
        $demande->addProposition($proposition);
        $proposition->addLigne(
            (new LigneDePrestation())
                ->setType(TypeLigne::Logement)
                ->setLibelle('6 nuits, chambre double vue fleuve')
                ->setPrestation($logement)
                ->setQuantite(6)
                ->setPrixEstimeCentimes(72000),
        );

        foreach ([$destination, $prestataire, $logement, $client, $demande, $demande->getConversation(), $proposition] as $entite) {
            $this->em->persist($entite);
        }
        foreach ([...$destination->getPeriodesClimatiques(), ...$logement->getTarifs(), ...$proposition->getLignes()] as $entite) {
            $this->em->persist($entite);
        }
        $this->em->flush();
        $this->em->clear();

        $relue = $this->em->getRepository(Demande::class)->find($demande->getId());

        self::assertNotNull($relue);
        self::assertSame(StatutDemande::Nouvelle, $relue->getStatut());
        self::assertSame(['gastronomie', 'balades'], $relue->getEnvies());
        self::assertSame('camille@example.com', $relue->getClient()?->getEmail());
        self::assertSame(64, \strlen((string) $relue->getConversation()?->getJeton()));
        self::assertCount(1, $relue->getPropositions());

        $ligne = $relue->getPropositions()->first()->getLignes()->first();
        self::assertNotFalse($ligne);
        self::assertSame(12000, $ligne->getPrestation()?->getTarifs()->first()->getPrixCentimes());
        self::assertSame(26.5, $ligne->getPrestation()->getDestination()?->getPeriodesClimatiques()->first()->getTemperatureMoyenne());
    }

    public function testUnePeriodeQuiFinitAvantDeCommencerEstRefusee(): void
    {
        $periode = (new PeriodeClimatique())
            ->setDebut(new \DateTimeImmutable('2027-06-30'))
            ->setFin(new \DateTimeImmutable('2027-06-01'))
            ->setTemperatureMoyenne(20);

        $erreurs = self::getContainer()->get(ValidatorInterface::class)->validate($periode);

        self::assertCount(1, $erreurs);
        self::assertSame('fin', $erreurs[0]->getPropertyPath());
    }
}
