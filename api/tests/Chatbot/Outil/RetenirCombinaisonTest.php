<?php

namespace App\Tests\Chatbot\Outil;

use App\Chatbot\ContexteAgent;
use App\Chatbot\Outil\OutilException;
use App\Chatbot\Outil\RetenirCombinaison;
use App\Chiffrage\ChiffrageCache;
use App\Entity\Conversation;
use App\Entity\Demande;
use App\Entity\Prestation;
use App\Enum\AgentIA;
use App\Enum\TypeDeVoyage;
use App\Tests\Chiffrage\ChiffrageDesDemandesTestTest;
use Doctrine\Bundle\FixturesBundle\Loader\SymfonyFixturesLoader;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Outil retenir_combinaison (#203) sur le jeu de démo : sur les dix demandes test, la combinaison attendue est
 * retenue, chaque niveau garde ses logements et activités, chaque prestation renvoie vers sa fiche ; tout choix
 * hors contrainte est refusé avec sa raison. Nécessite `make db-test`.
 */
final class RetenirCombinaisonTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private RetenirCombinaison $outil;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();

        /** @var SymfonyFixturesLoader $loader */
        $loader = self::getContainer()->get('doctrine.fixtures.loader');
        (new ORMExecutor($this->em, new ORMPurger($this->em)))->execute($loader->getFixtures());
        $this->em->clear();

        $this->outil = self::getContainer()->get(RetenirCombinaison::class);
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
    public function testChaqueDemandeTestRetientSaCombinaisonNiveauParNiveau(string $fichier, array $demande): void
    {
        $fiche = $this->demande($demande['exigencesAttendues']);
        $entree = $this->entree($demande);

        if ($demande['attendu']['passageAuConseiller']) {
            // Islande en janvier : aucun tarif, rien n'est retenu ; l'agent passe la main au conseiller.
            $this->expectException(OutilException::class);
            $this->expectExceptionMessage('Reykjavik ne convient pas : Aucun logement n\'a de tarif pour chaque nuit du 16/01/2027 au 22/01/2027');
            $this->outil->executer($this->contexte($fiche), $entree);
        }

        $sortie = $this->outil->executer($this->contexte($fiche), $entree);

        self::assertSame($demande['attendu']['destinations'][0], $sortie['destination']['slug'], $fichier);
        self::assertMatchesRegularExpression('#^/api/destinations/[\w-]+$#', $sortie['destination']['fiche'], $fichier);
        self::assertSame($demande['attendu']['budgetInsuffisant'], $sortie['budgetInsuffisant'], $fichier);
        foreach ($demande['attendu']['budgetsParNiveau'] as $cle => $attendu) {
            $niveau = $sortie['niveaux'][$cle];
            $contexte = $fichier.' '.$cle;
            self::assertSame($attendu['logements'], array_column($niveau['logements'], 'nom'), $contexte);
            self::assertSame($attendu['activites'], array_column($niveau['activites'], 'nom'), $contexte);
            self::assertEqualsWithDelta($attendu['totalCentimes'] / 100, $niveau['totalEuros'], 0.001, $contexte);
            foreach ($niveau['logements'] as $logement) {
                self::assertSame('/api/logements/'.$logement['id'], $logement['fiche'], $contexte);
            }
            foreach ($niveau['activites'] as $activite) {
                self::assertSame('/api/activites/'.$activite['id'], $activite['fiche'], $contexte);
            }
        }

        // Le niveau conseillé tient dans le budget ; les options sont des activités compatibles qu'il ne contient pas,
        // à leur prix de vente pour le groupe : le total du niveau augmente d'autant.
        $conseille = $sortie['niveaux'][$sortie['niveauConseille']];
        self::assertContains($sortie['niveauConseille'], ['essentiel', 'equilibre'], $fichier);
        self::assertLessThanOrEqual(RetenirCombinaison::OPTIONS_MAX, \count($sortie['options']), $fichier);
        if ($sortie['budgetInsuffisant']) {
            self::assertSame([], $sortie['options'], $fichier);
        }
        foreach ($sortie['options'] as $option) {
            self::assertNotContains($option['id'], array_column($conseille['activites'], 'id'), $fichier);
            self::assertGreaterThan(0, $option['prixEuros'], $fichier);
            self::assertEqualsWithDelta($conseille['totalEuros'] + $option['prixEuros'], $option['totalAvecEuros'], 0.011, $fichier);
        }

        // La justification vient des données : climat aux dates, trajet depuis la ville de départ, envies.
        $justification = $sortie['justification'];
        self::assertNotSame([], $justification['climatAuxDates'], $fichier);
        self::assertIsInt($justification['tempsDeTrajetMinutes'], $fichier);
        $envies = [...$justification['enviesCorrespondantes'], ...$justification['enviesSansReponse']];
        self::assertEqualsCanonicalizing($demande['exigencesAttendues']['envies'], $envies, $fichier);
        self::assertSame('Elle répond à toutes les exigences.', $justification['pourquoi']);

        // La combinaison est gardée avec le chiffrage, sous la demande : la rédaction la retrouvera.
        $enCache = self::getContainer()->get(ChiffrageCache::class)->retrouver($fiche, $sortie['chiffrageId']);
        self::assertSame($sortie['niveaux'], $enCache['combinaison']['niveaux'] ?? null, $fichier);
    }

    public function testLaJustificationDeLaFamilleATenerife(): void
    {
        $demande = self::demandesTest()['01-sejour-famille-tenerife.json'][1];
        $justification = $this->outil->executer($this->contexte($this->demande($demande['exigencesAttendues'])), $this->entree($demande))['justification'];

        self::assertSame([['debut' => '2027-02-01', 'fin' => '2027-02-28', 'temperatureMoyenne' => 19.0, 'pluieMm' => 30]], $justification['climatAuxDates']);
        self::assertSame(['au moins 18 °C'], $justification['enviesDeClimat'], 'Le climat aux dates répond à l\'envie de chaleur.');
        self::assertSame(['Bordeaux', 270], [$justification['villeDepart'], $justification['tempsDeTrajetMinutes']]);
        self::assertSame(['plage', 'famille'], $justification['enviesCorrespondantes']);
        self::assertSame(['animaux'], $justification['enviesSansReponse']);
    }

    /**
     * @param \Closure(array<string, mixed>, \Closure(list<string>): list<int>): array<string, mixed> $modifier
     */
    #[DataProvider('choixHorsContrainte')]
    public function testUnChoixHorsContrainteEstRefuseAvecSaRaison(string $fichier, \Closure $modifier, string $message): void
    {
        $demande = self::demandesTest()[$fichier][1];
        $entree = $modifier($this->entree($demande), $this->ids(...));

        $this->expectException(OutilException::class);
        $this->expectExceptionMessage($message);
        $this->outil->executer($this->contexte($this->demande($demande['exigencesAttendues'])), $entree);
    }

    /**
     * @return iterable<string, array{string, \Closure, string}>
     */
    public static function choixHorsContrainte(): iterable
    {
        $lisbonne = '02-sejour-lisbonne-mobilite-reduite.json';
        $tenerife = '01-sejour-famille-tenerife.json';

        yield 'logement non accessible' => [$lisbonne, static function (array $e, \Closure $ids): array {
            $e['etapes'][0]['logementIds'][] = $ids(['Appartement Bairro Alto'])[0];

            return $e;
        }, 'Étape 1 : Appartement Bairro Alto, non accessible : un voyageur a besoin d\'un logement accessible.'];
        yield 'activité non accessible' => [$lisbonne, static function (array $e, \Closure $ids): array {
            $e['activitesSuggerees'] = $ids(['Visite guidée de l\'Alfama et du tram 28']);

            return $e;
        }, 'Activités : Visite guidée de l\'Alfama et du tram 28, non accessible'];
        yield 'capacité dépassée' => ['05-groupe-rome-association.json', static function (array $e, \Closure $ids): array {
            $e['etapes'][0]['logementIds'] = $ids(['Hôtel Colosseo, chambre double']);

            return $e;
        }, 'Hôtel Colosseo, chambre double, capacité de 2 personnes : il faudrait 18 chambres'];
        yield 'hors de la zone demandée' => [$lisbonne, static function (array $e): array {
            $e['destination'] = 'rome';

            return $e;
        }, 'Rome ne convient pas : elle n\'est pas dans la zone demandée (« Lisbonne »).'];
        yield 'trop frais aux dates' => [$tenerife, static function (array $e): array {
            $e['destination'] = 'lisbonne';

            return $e;
        }, 'Lisbonne ne convient pas : 13 °C en moyenne aux dates, moins que les 18 °C souhaités.'];
        yield 'une nuit de moins' => [$tenerife, static function (array $e): array {
            $e['etapes'][0]['nombreDeNuits'] = 6;

            return $e;
        }, 'Les étapes comptent 6 nuits, le client en a demandé 7.'];
        yield 'dates fixes décalées' => [$tenerife, static function (array $e): array {
            $e['etapes'][0]['dateDebut'] = '2027-02-14';

            return $e;
        }, 'Le client part le 13/02/2027 (dates fixes), pas le 14/02/2027.'];
        yield 'étapes avec un trou' => ['03-circuit-costa-rica.json', static function (array $e): array {
            $e['etapes'][1]['dateDebut'] = '2027-03-11';

            return $e;
        }, 'Étape 2 : elle doit commencer le 10/03/2027'];
        yield 'identifiant inventé' => [$tenerife, static function (array $e): array {
            $e['activitesDemandees'][] = 999999;

            return $e;
        }, 'Activités : 999999 n\'existe pas à Tenerife'];
        yield 'logement d\'une autre destination' => [$tenerife, static function (array $e, \Closure $ids): array {
            $e['etapes'][0]['logementIds'] = $ids(['Tejo Palace, suite vue fleuve']);

            return $e;
        }, 'n\'existe pas à Tenerife'];
    }

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function demandesTest(): array
    {
        return ChiffrageDesDemandesTestTest::demandesTest();
    }

    /**
     * L'entrée qu'enverrait l'agent : les candidats attendus de la demande test, étapes à la suite.
     *
     * @param array<string, mixed> $demande
     *
     * @return array<string, mixed>
     */
    private function entree(array $demande): array
    {
        $candidats = $demande['attendu']['candidats'];
        $debut = new \DateTimeImmutable($demande['exigencesAttendues']['dateDebut']);
        $etapes = [];
        foreach ($candidats['etapes'] as $etape) {
            $etapes[] = ['dateDebut' => $debut->format('Y-m-d'), 'nombreDeNuits' => $etape['nuits'], 'logementIds' => $this->ids($etape['logements'])];
            $debut = $debut->modify(\sprintf('+%d days', $etape['nuits']));
        }

        return [
            'destination' => $demande['attendu']['destinations'][0],
            'etapes' => $etapes,
            'activitesDemandees' => $this->ids($candidats['activitesDemandees']),
            'activitesSuggerees' => $this->ids($candidats['activitesOptionnelles'] ?? []),
            'pourquoi' => 'Elle répond à toutes les exigences.',
        ];
    }

    /**
     * @param list<string> $noms
     *
     * @return list<int>
     */
    private function ids(array $noms): array
    {
        return array_map(
            fn (string $nom): int => (int) ($this->em->getRepository(Prestation::class)->findOneBy(['nom' => $nom])?->getId() ?? throw new \LogicException($nom)),
            $noms,
        );
    }

    /**
     * La fiche demande telle que l'agent Conversation l'a enregistrée.
     *
     * @param array<string, mixed> $exigences
     */
    private function demande(array $exigences): Demande
    {
        $demande = (new Demande())
            ->setTypeDeVoyage(TypeDeVoyage::from($exigences['typeDeVoyage']))
            ->setLieuOuZone($exigences['lieuOuZone'])
            ->setVilleDepart($exigences['villeDepart'])
            ->setDateDebut(new \DateTimeImmutable($exigences['dateDebut']))
            ->setDateFin(new \DateTimeImmutable($exigences['dateFin']))
            ->setDatesFlexibles($exigences['datesFlexibles'])
            ->setNombreDeJours($exigences['nombreDeJours'])
            ->setAdultes($exigences['adultes'])
            ->setEnfants($exigences['enfants'])
            ->setAgesEnfants($exigences['agesEnfants'])
            ->setBudgetCentimes(null === $exigences['budgetEuros'] ? null : $exigences['budgetEuros'] * 100)
            ->setBudgetParPersonne($exigences['budgetParPersonne'])
            ->setTransportInclus($exigences['transportInclus'])
            ->setEnvies($exigences['envies'])
            ->setContraintes($exigences['contraintes']);
        $demande->setConversation(new Conversation());
        $this->em->persist($demande);
        $this->em->persist($demande->getConversation() ?? throw new \LogicException());
        $this->em->flush();

        return $demande;
    }

    private function contexte(Demande $demande): ContexteAgent
    {
        return new ContexteAgent($demande, $demande->getConversation() ?? throw new \LogicException(), AgentIA::Recherche);
    }
}
