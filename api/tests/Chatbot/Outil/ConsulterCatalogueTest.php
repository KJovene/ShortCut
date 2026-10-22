<?php

namespace App\Tests\Chatbot\Outil;

use App\Chatbot\ContexteAgent;
use App\Chatbot\Outil\ConsulterCatalogue;
use App\Chatbot\Outil\OutilException;
use App\Entity\Conversation;
use App\Entity\Demande;
use App\Entity\Destination;
use App\Entity\Prestation;
use App\Enum\AgentIA;
use Doctrine\Bundle\FixturesBundle\Loader\SymfonyFixturesLoader;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * consulter_catalogue sur le jeu de démo : les recommandations de l'agent Conversation, classées par envies, sans
 * prix, et sans les logements qui ne peuvent pas accueillir le groupe. Nécessite `make db-test`.
 */
final class ConsulterCatalogueTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ContexteAgent $contexte;
    private ConsulterCatalogue $outil;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();

        /** @var SymfonyFixturesLoader $loader */
        $loader = self::getContainer()->get('doctrine.fixtures.loader');
        (new ORMExecutor($this->em, new ORMPurger($this->em)))->execute($loader->getFixtures());
        $this->em->clear();

        $this->outil = new ConsulterCatalogue($this->em->getRepository(Destination::class), $this->em->getRepository(Prestation::class));
        $this->contexte = new ContexteAgent(new Demande(), new Conversation(), AgentIA::Conversation);
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testLeSchemaSuitLaSpecification(): void
    {
        $definition = $this->outil->definition();

        self::assertSame('consulter_catalogue', $definition['name']);
        self::assertFalse($definition['strict']);
        self::assertSame(['destination', 'type'], $definition['input_schema']['required']);
        self::assertSame(['logement', 'activite'], $definition['input_schema']['properties']['type']['enum']);
    }

    public function testLesLogementsQuiRepondentAuxEnviesViennentEnPremierSansPrix(): void
    {
        $resultat = $this->outil->executer($this->contexte, ['destination' => 'Santorin', 'type' => 'logement', 'envies' => ['famille'], 'voyageurs' => 4]);

        self::assertSame(['slug' => 'santorin', 'ville' => 'Santorin', 'pays' => 'Grèce'], $resultat['destination']);
        self::assertSame('Villa Imerovigli', $resultat['prestations'][0]['nom']);
        self::assertSame(['famille'], $resultat['prestations'][0]['enviesCorrespondantes']);
        self::assertSame(3, $resultat['prestations'][0]['chambres']);
        foreach ($resultat['prestations'] as $fiche) {
            self::assertSame([], array_filter(array_keys($fiche), static fn (string $cle): bool => str_contains(strtolower($cle), 'prix')), 'Aucun prix avant la proposition.');
        }
    }

    public function testUnGroupeTropGrandEcarteLesLogementsTropPetits(): void
    {
        $resultat = $this->outil->executer($this->contexte, ['destination' => 'santorin', 'type' => 'logement', 'voyageurs' => 8, 'limite' => 6]);

        // La villa de 6 places et 3 chambres se loue entière, une seule fois.
        self::assertNotContains('Villa Imerovigli', array_column($resultat['prestations'], 'nom'));
        self::assertGreaterThanOrEqual(1, $resultat['ecartees']);
    }

    public function testLesActivitesDonnentLeurDureeEtLeurAgeMinimum(): void
    {
        $resultat = $this->outil->executer($this->contexte, ['destination' => 'santorin', 'type' => 'activite', 'envies' => ['gastronomie'], 'limite' => 1]);

        self::assertCount(1, $resultat['prestations']);
        self::assertSame('Dégustation de vins volcaniques', $resultat['prestations'][0]['nom']);
        self::assertSame(150, $resultat['prestations'][0]['dureeMinutes']);
        self::assertSame(18, $resultat['prestations'][0]['ageMinimum']);
        self::assertArrayNotHasKey('chambres', $resultat['prestations'][0]);
    }

    public function testUneDestinationHorsCatalogueEstRefusee(): void
    {
        $this->expectException(OutilException::class);
        $this->expectExceptionMessage('n\'existe pas dans le catalogue');

        $this->outil->executer($this->contexte, ['destination' => 'Bora Bora', 'type' => 'logement']);
    }
}
