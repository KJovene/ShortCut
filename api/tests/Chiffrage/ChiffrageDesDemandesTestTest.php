<?php

namespace App\Tests\Chiffrage;

use App\Chiffrage\BudgetCalculator;
use App\Chiffrage\DemandeDeChiffrage;
use App\Chiffrage\Etape;
use App\Chiffrage\Niveau;
use App\Chiffrage\Poste;
use App\Entity\Destination;
use App\Entity\Prestataire;
use App\Entity\Prestation;
use App\Entity\RegleDePrix;
use App\Enum\NiveauBudget;
use App\Enum\TypeDeVoyage;
use App\Enum\TypePrestataire;
use Doctrine\Bundle\FixturesBundle\Loader\SymfonyFixturesLoader;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Critères d'acceptation du service de chiffrage (#205) sur le jeu de démo :
 * - les dix demandes test (tests/demandes) sont chiffrées au centime près, poste par poste ;
 * - les trois exemples de docs/chiffrage.md sont reproduits.
 * Nécessite `make db-test`.
 */
final class ChiffrageDesDemandesTestTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private BudgetCalculator $calculateur;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();

        /** @var SymfonyFixturesLoader $loader */
        $loader = self::getContainer()->get('doctrine.fixtures.loader');
        (new ORMExecutor($this->em, new ORMPurger($this->em)))->execute($loader->getFixtures());
        $this->em->clear();

        $this->calculateur = new BudgetCalculator();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $demande
     */
    #[DataProvider('demandesTest')]
    public function testLesDemandesTestSontChiffreesAuCentimePres(string $fichier, array $demande): void
    {
        $attendu = $demande['attendu'];
        $resultat = $this->calculateur->calculer($this->demandeDeChiffrage($demande));

        if ($attendu['passageAuConseiller']) {
            self::assertNull($resultat->niveaux, $fichier);
            self::assertNotNull($resultat->nonChiffrable, $fichier);

            return;
        }

        self::assertNull($resultat->nonChiffrable, $fichier.' : '.$resultat->nonChiffrable?->getMessage());
        self::assertSame($attendu['budgetInsuffisant'], $resultat->budgetInsuffisant, $fichier);
        $this->comparer($fichier, $attendu['budgetsParNiveau'], $resultat->niveaux ?? []);

        if (isset($attendu['versionInitiale'])) {
            $initiale = $demande;
            $initiale['exigencesAttendues'] = [...$demande['exigencesAttendues'], ...$attendu['versionInitiale']['exigences']];
            $resultat = $this->calculateur->calculer($this->demandeDeChiffrage($initiale));
            $this->comparer($fichier.' (version initiale)', $attendu['versionInitiale']['budgetsParNiveau'], $resultat->niveaux ?? []);
        }
    }

    public function testExemple1BudgetDonneEtSuffisant(): void
    {
        $niveaux = $this->calculateur->calculer($this->demandeDeChiffrage(self::demandesTest()['02-sejour-lisbonne-mobilite-reduite.json'][1]))->niveaux ?? [];

        self::assertSame([88000, 117400, 224800], self::totaux($niveaux));
        self::assertSame([44000, 58700, 112400], array_values(array_map(static fn (Niveau $n): int => $n->parPersonneCentimes, $niveaux)));
    }

    public function testExemple2BudgetInsuffisantEtSonArbitrage(): void
    {
        $demande = self::demandesTest()['06-groupe-marrakech-budget-insuffisant.json'][1];
        $resultat = $this->calculateur->calculer($this->demandeDeChiffrage($demande));

        self::assertTrue($resultat->budgetInsuffisant);
        $essentiel = $resultat->niveau(NiveauBudget::Essentiel);
        self::assertSame(632000, $essentiel?->totalCentimes);
        self::assertSame(52667, $essentiel->parPersonneCentimes);

        // Arbitrage chiffré avec la même formule : 3 nuits du 16 au 19 septembre (basse saison).
        $demande['exigencesAttendues']['dateDebut'] = '2027-09-16';
        $demande['attendu']['candidats']['etapes'][0]['nuits'] = 3;
        $arbitrage = $this->calculateur->calculer($this->demandeDeChiffrage($demande))->niveau(NiveauBudget::Essentiel);
        self::assertSame(444800, $arbitrage?->totalCentimes);
        self::assertSame(37067, $arbitrage->parPersonneCentimes);
        self::assertSame('3 nuits × 520 €', $arbitrage->postes(Poste::HEBERGEMENT)[0]->calcul);
    }

    public function testExemple3SansBudget(): void
    {
        $demande = self::demandesTest()['07-noces-santorin.json'][1];
        $demande['exigencesAttendues']['budgetEuros'] = null;
        $resultat = $this->calculateur->calculer($this->demandeDeChiffrage($demande));

        self::assertFalse($resultat->budgetInsuffisant);
        self::assertSame([228600, 268800, 550200], self::totaux($resultat->niveaux ?? []));
        // Équilibré sans budget : le logement médian (le moins cher des deux) et la seule activité demandée.
        $equilibre = $resultat->niveau(NiveauBudget::Equilibre);
        self::assertSame(['Hôtel Fira Blue, chambre triple'], $equilibre?->logements);
        self::assertSame(['Croisière en catamaran dans la caldeira'], $equilibre->activites);
    }

    /**
     * @param array<string, array<string, mixed>> $attendus
     * @param array<string, Niveau>               $niveaux
     */
    private function comparer(string $fichier, array $attendus, array $niveaux): void
    {
        self::assertSame(array_keys($attendus), array_keys($niveaux), $fichier);
        foreach ($attendus as $cle => $attendu) {
            $niveau = $niveaux[$cle];
            $contexte = $fichier.' '.$cle;
            self::assertSame($attendu['logements'], $niveau->logements, $contexte);
            self::assertSame($attendu['activites'], $niveau->activites, $contexte);
            self::assertSame($attendu['transportAu'], $niveau->transport->label(), $contexte);

            $detail = $attendu['detail'];
            foreach (['hebergements' => Poste::HEBERGEMENT, 'transports' => Poste::TRANSPORT, 'activites' => Poste::ACTIVITE] as $groupe => $type) {
                self::assertSame(
                    array_map(static fn (array $p): array => [$p['prestation'], $p['calcul'], $p['centimes']], $detail[$groupe]),
                    array_map(static fn (Poste $p): array => [$p->libelle, $p->calcul, $p->centimes], $niveau->postes($type)),
                    $contexte.' '.$groupe,
                );
            }
            self::assertSame($detail['coutPrestationsCentimes'], $niveau->coutPrestationsCentimes, $contexte);
            self::assertSame($detail['margeCentimes'], $niveau->margeCentimes, $contexte);
            self::assertSame($detail['fraisDeDossierCentimes'], $niveau->fraisDeDossierCentimes, $contexte);
            self::assertSame($attendu['totalCentimes'], $niveau->totalCentimes, $contexte);
            self::assertSame($attendu['parPersonneCentimes'], $niveau->parPersonneCentimes, $contexte);
        }
    }

    /**
     * Construit la demande de chiffrage d'une demande test : exigences du client et candidats attendus.
     *
     * @param array<string, mixed> $demande
     */
    private function demandeDeChiffrage(array $demande): DemandeDeChiffrage
    {
        $exigences = $demande['exigencesAttendues'];
        $candidats = $demande['attendu']['candidats'];
        $type = TypeDeVoyage::from($demande['typeDeVoyage']);
        $regle = $this->em->getRepository(RegleDePrix::class)->findOneBy([]);
        self::assertNotNull($regle);

        $destination = $this->em->getRepository(Destination::class)->findOneBy(['slug' => $demande['attendu']['destinations'][0]]);
        self::assertNotNull($destination);

        $etapes = [];
        $debut = new \DateTimeImmutable($exigences['dateDebut']);
        foreach ($candidats['etapes'] as $etape) {
            $etapes[] = new Etape($debut, $etape['nuits'], $this->prestations($etape['logements']));
            $debut = $debut->modify(\sprintf('+%d days', $etape['nuits']));
        }

        return new DemandeDeChiffrage(
            typeDeVoyage: $type,
            destination: $destination,
            adultes: $exigences['adultes'],
            agesEnfants: $exigences['agesEnfants'],
            villeDepart: $exigences['villeDepart'],
            transportInclus: $exigences['transportInclus'],
            budgetCentimes: null === $exigences['budgetEuros'] ? null : $exigences['budgetEuros'] * 100,
            budgetParPersonne: $exigences['budgetParPersonne'],
            etapes: $etapes,
            activitesDemandees: $this->prestations($candidats['activitesDemandees']),
            activitesSuggerees: $this->prestations($candidats['activitesOptionnelles']),
            transporteurs: $this->em->getRepository(Prestataire::class)->findBy(['type' => TypePrestataire::Transporteur]),
            margePourcentage: $regle->getMargePourcentage(),
            fraisDeDossierCentimes: $regle->getFraisDeDossierCentimes($type),
        );
    }

    /**
     * @param list<string> $noms
     *
     * @return list<Prestation>
     */
    private function prestations(array $noms): array
    {
        return array_map(function (string $nom): Prestation {
            $prestation = $this->em->getRepository(Prestation::class)->findOneBy(['nom' => $nom]);
            self::assertNotNull($prestation, $nom);

            return $prestation;
        }, $noms);
    }

    /**
     * @param array<string, Niveau> $niveaux
     *
     * @return list<int>
     */
    private static function totaux(array $niveaux): array
    {
        return array_values(array_map(static fn (Niveau $n): int => $n->totalCentimes, $niveaux));
    }

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function demandesTest(): array
    {
        $demandes = [];
        foreach (glob(\dirname(__DIR__).'/demandes/[0-9][0-9]-*.json') ?: [] as $chemin) {
            $demandes[basename($chemin)] = [basename($chemin), json_decode((string) file_get_contents($chemin), true, flags: \JSON_THROW_ON_ERROR)];
        }

        return $demandes;
    }
}
