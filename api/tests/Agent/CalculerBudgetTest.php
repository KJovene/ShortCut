<?php

namespace App\Tests\Agent;

use App\Agent\ContexteAgent;
use App\Agent\EntreeInvalide;
use App\Agent\Outil\CalculerBudget;
use App\Chiffrage\ArbitrageCalculator;
use App\Chiffrage\BudgetCalculator;
use App\Chiffrage\ChiffrageCache;
use App\Entity\Conversation;
use App\Entity\Demande;
use App\Entity\Destination;
use App\Entity\Prestataire;
use App\Entity\Prestation;
use App\Entity\RegleDePrix;
use App\Enum\AgentIA;
use App\Enum\TypeDeVoyage;
use Doctrine\Bundle\FixturesBundle\Loader\SymfonyFixturesLoader;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;

/**
 * Outil calculer_budget (#205) : le modèle donne des identifiants de candidats, le serveur chiffre la demande
 * courante et renvoie des euros. Nécessite `make db-test`.
 */
final class CalculerBudgetTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private CalculerBudget $outil;
    private ChiffrageCache $cache;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();

        /** @var SymfonyFixturesLoader $loader */
        $loader = self::getContainer()->get('doctrine.fixtures.loader');
        (new ORMExecutor($this->em, new ORMPurger($this->em)))->execute($loader->getFixtures());

        // Personne n'appelle encore l'outil (la boucle d'agent arrive avec #192) : le conteneur de test l'a retiré.
        $this->cache = new ChiffrageCache(new ArrayAdapter());
        $calculateur = new BudgetCalculator();
        $this->outil = new CalculerBudget(
            $calculateur,
            new ArbitrageCalculator($calculateur),
            new MockClock('2026-10-19 15:30'),
            $this->cache,
            $this->em->getRepository(Destination::class),
            $this->em->getRepository(Prestation::class),
            $this->em->getRepository(Prestataire::class),
            $this->em->getRepository(RegleDePrix::class),
        );
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testLeSchemaSuitLaSpecification(): void
    {
        self::assertSame('calculer_budget', $this->outil->nom());
        self::assertTrue($this->outil->strict());
        self::assertStringContainsString('Ne calcule jamais de prix toi-même', $this->outil->description());

        $schema = $this->outil->schemaEntree();
        self::assertSame(['destination', 'etapes', 'activitesDemandees', 'activitesSuggerees'], $schema['required']);
        self::assertFalse($schema['additionalProperties']);
        self::assertFalse($schema['properties']['etapes']['items']['additionalProperties']);
    }

    public function testLaNocesASantorinEstChiffreeEnEurosEtGardeeEnCache(): void
    {
        $contexte = $this->contexte($this->demandeDeNoces());
        $sortie = $this->outil->executer($contexte, [
            'destination' => 'Santorin',
            'etapes' => [['dateDebut' => '2027-06-12', 'nombreDeNuits' => 7, 'logementIds' => $this->ids('Hôtel Fira Blue, chambre triple', 'Suite Caldera Oia, piscine privée')]],
            'activitesDemandees' => $this->ids('Croisière en catamaran dans la caldeira'),
            'activitesSuggerees' => $this->ids('Dégustation de vins volcaniques'),
        ]);

        self::assertSame(['montantEuros' => 5000, 'parPersonne' => false], $sortie['budgetClient']);
        self::assertFalse($sortie['budgetInsuffisant']);
        self::assertSame(CalculerBudget::AVERTISSEMENT, $sortie['avertissement']);
        self::assertSame([2286, 4956, 5502], array_column($sortie['niveaux'], 'totalEuros'));

        $equilibre = $sortie['niveaux']['equilibre'];
        self::assertSame(['Suite Caldera Oia, piscine privée'], $equilibre['logements']);
        self::assertSame('milieu de la fourchette', $equilibre['transport']);
        self::assertSame(['type' => 'transport', 'libelle' => 'Transferts aéroport – hébergement à Santorin', 'calcul' => '2 voyageurs × 42,50 €', 'montantEuros' => 85, 'estime' => true], $equilibre['postes'][2]);
        self::assertContains('Frais de dossier voyage de noces : 150 €', $equilibre['hypotheses']);
        self::assertNull($sortie['nonChiffrable']);
        self::assertArrayNotHasKey('arbitrages', $sortie, 'Le budget suffit : pas d\'arbitrage.');

        // Retrouvé par son identifiant sous la même demande, jamais sous une autre.
        $cache = $this->cache;
        self::assertMatchesRegularExpression('/^chf_[0-9a-f]{8}$/', $sortie['chiffrageId']);
        self::assertSame($sortie['niveaux'], $cache->retrouver($contexte->demande, $sortie['chiffrageId'])['niveaux'] ?? null);
        self::assertNull($cache->retrouver($this->demandeDeNoces(), $sortie['chiffrageId']));
        self::assertNull($cache->retrouver($contexte->demande, '../autre'));
    }

    public function testUnBudgetInsuffisantRenvoieDesArbitragesChiffres(): void
    {
        $demande = $this->demandeDeNoces()->setTypeDeVoyage(TypeDeVoyage::Groupe)->setAdultes(12)->setBudgetCentimes(30000)->setBudgetParPersonne(true);
        $sortie = $this->outil->executer($this->contexte($demande), [
            'destination' => 'marrakech',
            'etapes' => [['dateDebut' => '2027-10-21', 'nombreDeNuits' => 4, 'logementIds' => $this->ids('Riad Dar Yasmine, riad entier')]],
            'activitesDemandees' => [],
            'activitesSuggerees' => [],
        ]);

        self::assertTrue($sortie['budgetInsuffisant']);
        self::assertSame(526.67, $sortie['niveaux']['essentiel']['parPersonneEuros']);
        self::assertGreaterThanOrEqual(2, \count($sortie['arbitrages']));
        self::assertSame([
            'type' => 'moins_de_nuits_et_autre_periode',
            'libelle' => 'Réduire à 3 nuits, du 28/09/2027 au 01/10/2027, à une période moins chère',
            'etapes' => [['dateDebut' => '2027-09-28', 'nombreDeNuits' => 3]],
            'totalEuros' => 4448,
            'parPersonneEuros' => 370.67,
            'tientDansLeBudget' => false,
            'ecartAuBudgetEuros' => 848,
        ], array_diff_key($sortie['arbitrages'][2], ['hypotheses' => true]));
        self::assertNotEmpty($sortie['arbitrages'][2]['hypotheses']);
    }

    public function testUnePeriodeSansTarifRenvoieLaRaisonSansChiffrage(): void
    {
        $demande = $this->demandeDeNoces()->setTypeDeVoyage(TypeDeVoyage::Circuit);
        $sortie = $this->outil->executer($this->contexte($demande), [
            'destination' => 'islande',
            'etapes' => [['dateDebut' => '2027-01-16', 'nombreDeNuits' => 2, 'logementIds' => $this->ids('Guesthouse Reykjavik Centre')]],
            'activitesDemandees' => [],
            'activitesSuggerees' => [],
        ]);

        self::assertNull($sortie['niveaux']);
        self::assertArrayNotHasKey('chiffrageId', $sortie);
        self::assertSame(['prestation' => 'Guesthouse Reykjavik Centre', 'date' => '2027-01-16', 'raison' => 'Aucun tarif saisi à cette date.'], $sortie['nonChiffrable']);
    }

    public function testUneEntreeInvalideRenvoieUnMessageQueLeModelePeutCorriger(): void
    {
        $contexte = $this->contexte($this->demandeDeNoces());
        $fira = $this->ids('Hôtel Fira Blue, chambre triple');
        $etape = ['dateDebut' => '2027-06-12', 'nombreDeNuits' => 7, 'logementIds' => $fira];
        $valide = ['destination' => 'santorin', 'etapes' => [$etape], 'activitesDemandees' => [], 'activitesSuggerees' => []];

        $cas = [
            'La destination « paris » n\'existe pas dans le catalogue.' => ['destination' => 'paris'] + $valide,
            'Indique la destination' => ['destination' => ''] + $valide,
            'Donne au moins une étape' => ['etapes' => []] + $valide,
            'Étape 1 : la date de début doit être au format AAAA-MM-JJ.' => ['etapes' => [['dateDebut' => '12/06/2027'] + $etape]] + $valide,
            'Étape 1 : le nombre de nuits doit être compris entre 1 et 60.' => ['etapes' => [['nombreDeNuits' => 0] + $etape]] + $valide,
            'Étape 1 : donne au moins un logement compatible' => ['etapes' => [['logementIds' => []] + $etape]] + $valide,
            'n\'existe pas à Santorin' => ['activitesDemandees' => $this->ids('Atelier pastéis de nata')] + $valide,
            'doivent être une liste de nombres' => ['activitesSuggerees' => 'croisière'] + $valide,
        ];
        foreach ($cas as $message => $entree) {
            try {
                $this->outil->executer($contexte, $entree);
                self::fail('Entrée acceptée : '.$message);
            } catch (EntreeInvalide $e) {
                self::assertStringContainsString($message, $e->getMessage());
            }
        }
    }

    public function testLaDemandeDoitConnaitreLeTypeEtLaVilleDeDepart(): void
    {
        $entree = [
            'destination' => 'santorin',
            'etapes' => [['dateDebut' => '2027-06-12', 'nombreDeNuits' => 7, 'logementIds' => $this->ids('Hôtel Fira Blue, chambre triple')]],
            'activitesDemandees' => [],
            'activitesSuggerees' => [],
        ];

        $sansType = new Demande();
        $sansType->setVilleDepart('Paris');
        try {
            $this->outil->executer($this->contexte($sansType), $entree);
            self::fail('Chiffrage sans type de voyage.');
        } catch (EntreeInvalide $e) {
            self::assertStringContainsString('type de voyage', $e->getMessage());
        }

        $sansVille = $this->demandeDeNoces()->setVilleDepart(null);
        $this->expectException(EntreeInvalide::class);
        $this->expectExceptionMessage('La ville de départ du client n\'est pas encore connue');
        $this->outil->executer($this->contexte($sansVille), $entree);
    }

    public function testTropDeCandidatsEstRenvoyeAuModele(): void
    {
        $logements = $this->em->getRepository(Prestation::class)->findBy(['type' => 'logement']);
        $activites = $this->em->getRepository(Prestation::class)->findBy(['type' => 'activite']);
        $santorin = static fn (Prestation $p): bool => 'santorin' === $p->getDestination()?->getSlug();
        $ids = static fn (array $p): array => array_values(array_map(static fn (Prestation $x): ?int => $x->getId(), array_filter($p, $santorin)));

        // Un enfant sans âge connu compte comme le plus jeune.
        $demande = $this->demandeDeNoces()->setEnfants(1)->setAgesEnfants([]);
        $etape = ['dateDebut' => '2027-06-12', 'nombreDeNuits' => 7, 'logementIds' => $ids($logements)];
        $sortie = $this->outil->executer($this->contexte($demande), [
            'destination' => 'santorin', 'etapes' => [$etape], 'activitesDemandees' => [], 'activitesSuggerees' => $ids($activites),
        ]);
        $vol = $sortie['niveaux']['confort']['postes'][1];
        self::assertSame('transport', $vol['type']);
        self::assertStringStartsWith('3 voyageurs × ', $vol['calcul']);

        // 3 logements à chacune des 8 étapes et toutes les suggestions : plus de 20 000 combinaisons à comparer
        // pour l'équilibré, avec un budget assez large pour qu'il faille les explorer.
        $demande->setBudgetCentimes(100_000_000);

        $this->expectException(EntreeInvalide::class);
        $this->expectExceptionMessageMatches('/Trop de combinaisons/');
        $this->outil->executer($this->contexte($demande), [
            'destination' => 'santorin',
            'etapes' => array_fill(0, 8, $etape),
            'activitesDemandees' => [],
            'activitesSuggerees' => $ids($activites),
        ]);
    }

    private function demandeDeNoces(): Demande
    {
        $demande = (new Demande())
            ->setTypeDeVoyage(TypeDeVoyage::Noces)
            ->setVilleDepart('Paris')
            ->setAdultes(2)
            ->setBudgetCentimes(500000);
        $conversation = new Conversation();
        $demande->setConversation($conversation);
        $this->em->persist($demande);
        $this->em->persist($conversation);
        $this->em->flush();

        return $demande;
    }

    private function contexte(Demande $demande): ContexteAgent
    {
        if (null === $demande->getConversation()) {
            $demande->setConversation(new Conversation());
        }

        return new ContexteAgent($demande, $demande->getConversation() ?? throw new \LogicException(), AgentIA::Chiffrage);
    }

    /**
     * @return list<int>
     */
    private function ids(string ...$noms): array
    {
        return array_map(function (string $nom): int {
            $id = $this->em->getRepository(Prestation::class)->findOneBy(['nom' => $nom])?->getId();
            self::assertNotNull($id, $nom);

            return $id;
        }, $noms);
    }
}
