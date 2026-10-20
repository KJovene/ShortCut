<?php

namespace App\Tests\Chatbot\Outil;

use App\Chatbot\ContexteAgent;
use App\Chatbot\Outil\ChercherActivites;
use App\Chatbot\Outil\ChercherDestinations;
use App\Chatbot\Outil\ChercherLogements;
use App\Chatbot\Outil\OutilException;
use App\Entity\Conversation;
use App\Entity\Demande;
use App\Entity\Destination;
use App\Entity\Prestation;
use App\Enum\AgentIA;
use App\Tests\Chiffrage\ChiffrageDesDemandesTestTest;
use Doctrine\Bundle\FixturesBundle\Loader\SymfonyFixturesLoader;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Outils de recherche (#202) sur le jeu de démo : les dix demandes test retrouvent leur destination, leurs
 * logements et activités compatibles, et aucun résultat n'est hors capacité, hors température ou sans tarif
 * aux dates. Nécessite `make db-test`.
 */
final class OutilsDeRechercheTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ContexteAgent $contexte;
    private ChercherDestinations $destinations;
    private ChercherLogements $logements;
    private ChercherActivites $activites;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();

        /** @var SymfonyFixturesLoader $loader */
        $loader = self::getContainer()->get('doctrine.fixtures.loader');
        (new ORMExecutor($this->em, new ORMPurger($this->em)))->execute($loader->getFixtures());
        $this->em->clear();

        // Aucun agent n'utilise encore chercher_logements ni chercher_activites (agent Recherche) : le conteneur de
        // test les a retirés, ils sont construits ici avec les mêmes dépôts.
        $destinations = $this->em->getRepository(Destination::class);
        $prestations = $this->em->getRepository(Prestation::class);
        $this->destinations = new ChercherDestinations($destinations, $this->em, $prestations, new MockClock('2026-10-19'));
        $this->logements = new ChercherLogements($destinations, $prestations);
        $this->activites = new ChercherActivites($destinations, $prestations);
        $this->contexte = new ContexteAgent(new Demande(), new Conversation(), AgentIA::Recherche);
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testLesSchemasSuiventLaSpecification(): void
    {
        $attendus = [
            'chercher_destinations' => [$this->destinations, []],
            'chercher_logements' => [$this->logements, ['destination', 'dateDebut', 'nombreDeNuits', 'voyageurs', 'accessibleObligatoire']],
            'chercher_activites' => [$this->activites, ['destination', 'date', 'adultes', 'agesEnfants', 'accessibleObligatoire']],
        ];
        foreach ($attendus as $nom => [$outil, $requis]) {
            $definition = $outil->definition();
            self::assertSame($nom, $outil->nom());
            self::assertSame($nom, $definition['name']);
            self::assertFalse($definition['strict'], $nom);
            self::assertSame($requis, $definition['input_schema']['required'], $nom);
            self::assertFalse($definition['input_schema']['additionalProperties'], $nom);
        }
    }

    /**
     * @param array<string, mixed> $demande
     */
    #[DataProvider('demandesTest')]
    public function testChaqueDemandeTestRetrouveSaDestination(string $fichier, array $demande): void
    {
        $exigences = $demande['exigencesAttendues'];
        $slug = $demande['attendu']['destinations'][0];
        $resultat = $this->destinations->executer($this->contexte, [
            'zone' => $exigences['lieuOuZone'],
            'dateDebut' => $exigences['dateDebut'],
            'dateFin' => $exigences['dateFin'],
            'temperatureMin' => $exigences['temperatureMin'],
            'temperatureMax' => $exigences['temperatureMax'],
            'envies' => $exigences['envies'],
            'villeDepart' => $exigences['villeDepart'],
            'limite' => 10,
        ]);

        if ($demande['attendu']['passageAuConseiller']) {
            // Islande en janvier : aucun tarif, la destination est écartée et l'agent passe la main.
            self::assertSame([], $resultat['destinations'], $fichier);
            self::assertSame($slug, $resultat['ecartees'][0]['slug'], $fichier);
            self::assertStringStartsWith('Aucun logement n\'a de tarif pour chaque nuit du 16/01/2027 au 22/01/2027', $resultat['ecartees'][0]['raison']);

            return;
        }

        self::assertContains($slug, array_column($resultat['destinations'], 'slug'), $fichier);
        foreach ($resultat['destinations'] as $destination) {
            self::assertNotNull($destination['aPartirDeParNuitEuros'], $fichier.' '.$destination['slug']);
            foreach ($destination['climatAuxDates'] as $periode) {
                if (null !== $exigences['temperatureMin']) {
                    self::assertGreaterThanOrEqual($exigences['temperatureMin'], $periode['temperatureMoyenne'], $fichier.' '.$destination['slug']);
                }
            }
        }
    }

    public function testLeSoleilDeFevrierEcarteLesDestinationsTropFraichesEtClasseParEnvies(): void
    {
        $resultat = $this->destinations->executer($this->contexte, [
            'zone' => 'soleil, plage', 'dateDebut' => '2027-02-13', 'dateFin' => '2027-02-20', 'temperatureMin' => 18,
            'envies' => ['plage', 'famille', 'animaux'], 'villeDepart' => 'Bordeaux',
        ]);

        self::assertStringContainsString('voici tout le catalogue publié', $resultat['remarque']);
        // Plage et famille partout : à égalité d'envies, l'ordre est celui des villes (La Fortuna, Tenerife, Ubud).
        self::assertSame(['costa-rica', 'tenerife', 'bali'], array_column($resultat['destinations'], 'slug'));
        $tenerife = $resultat['destinations'][1];
        self::assertSame(['plage', 'famille'], $tenerife['enviesCorrespondantes']);
        self::assertSame(270, $tenerife['tempsDeTrajetMinutes']);
        self::assertSame(114, $tenerife['aPartirDeParNuitEuros'], 'Hôtel Teide Nature, 95 € en haute saison d\'hiver (× 1,2).');
        // Lisbonne est écartée avec son climat, et les premières dates où elle conviendrait : 19 °C en mai.
        $lisbonne = current(array_filter($resultat['ecartees'], static fn (array $e): bool => 'lisbonne' === $e['slug']));
        self::assertSame([
            'slug' => 'lisbonne',
            'raison' => '13 °C en moyenne aux dates, moins que les 18 °C souhaités.',
            'climatAuxDates' => [['debut' => '2027-02-01', 'fin' => '2027-02-28', 'temperatureMoyenne' => 13.0, 'pluieMm' => 90]],
            'datesPossibles' => [['dateDebut' => '2027-05-01', 'dateFin' => '2027-05-08']],
        ], $lisbonne);
    }

    /**
     * Islande en janvier (#204) : aucun tarif. L'outil dit pourquoi, donne le climat saisi, les premières dates
     * possibles (mai, aucune avant aujourd'hui) et d'autres destinations aux mêmes dates : rien n'est à inventer.
     */
    public function testUnePeriodeNonCouverteDonneDesAjustements(): void
    {
        $resultat = $this->destinations->executer($this->contexte, [
            'zone' => 'Islande', 'dateDebut' => '2027-01-16', 'dateFin' => '2027-01-22', 'envies' => ['nature', 'aurores boréales'],
        ]);

        self::assertSame([], $resultat['destinations']);
        $islande = $resultat['ecartees'][0];
        self::assertSame('islande', $islande['slug']);
        self::assertSame(0.0, $islande['climatAuxDates'][0]['temperatureMoyenne']);
        self::assertSame([['dateDebut' => '2027-05-01', 'dateFin' => '2027-05-07']], $islande['datesPossibles']);
        self::assertArrayNotHasKey('nuitsPossibles', $islande);

        self::assertCount(3, $resultat['autresDestinations']);
        foreach ($resultat['autresDestinations'] as $autre) {
            self::assertNotSame('islande', $autre['slug']);
            self::assertContains('nature', $autre['enviesCorrespondantes']);
            self::assertNotNull($autre['aPartirDeParNuitEuros']);
        }
    }

    public function testUnGroupeSansLogementAssezGrandEstOrienteVersDAutresDestinations(): void
    {
        // 60 choristes : aucun logement de Santorin ne les accueille, quelles que soient les dates.
        $resultat = $this->destinations->executer($this->contexte, [
            'zone' => 'Santorin', 'dateDebut' => '2027-06-10', 'dateFin' => '2027-06-14', 'voyageurs' => 60,
        ]);

        self::assertSame([], $resultat['destinations']);
        self::assertSame([['slug' => 'santorin', 'raison' => 'Aucun logement pour 60 voyageurs : proposer d\'autres destinations ou passer la main au conseiller.']], $resultat['ecartees']);
        self::assertSame(['rome'], array_column($resultat['autresDestinations'], 'slug'), 'Seul le Roma Congressi Hotel accueille 60 personnes.');
    }

    public function testUneDureePlusCourtePeutSuffire(): void
    {
        // Les tarifs islandais s'arrêtent au 30 septembre : sur 7 nuits à partir du 26, seules 5 sont couvertes.
        $islande = $this->destinations->executer($this->contexte, ['zone' => 'Islande', 'dateDebut' => '2027-09-26', 'dateFin' => '2027-10-03']);
        self::assertSame(5, $islande['ecartees'][0]['nuitsPossibles']);
        self::assertSame([['dateDebut' => '2027-09-24', 'dateFin' => '2027-10-01']], array_slice($islande['ecartees'][0]['datesPossibles'], 0, 1));
    }

    public function testUneTemperatureDemandeLesDates(): void
    {
        $this->expectException(OutilException::class);
        $this->expectExceptionMessage('Donne dateDebut et dateFin pour filtrer par température.');
        $this->destinations->executer($this->contexte, ['temperatureMin' => 20]);
    }

    public function testUneSaisonEntiereNEstPasUneDateDeVoyage(): void
    {
        $this->expectException(OutilException::class);
        $this->expectExceptionMessage('donne les dates du voyage, pas une saison');
        $this->destinations->executer($this->contexte, ['dateDebut' => '2027-03-01', 'dateFin' => '2027-06-30']);
    }

    /**
     * Les logements attendus sont proposés, chaque étape d'un circuit comprise ; ceux que la demande test exclut
     * sont écartés. Aucun résultat n'est hors capacité ni sans tarif pour une des nuits.
     *
     * @param array<string, mixed> $demande
     */
    #[DataProvider('demandesTest')]
    public function testChaqueDemandeTestRetrouveSesLogements(string $fichier, array $demande): void
    {
        $exigences = $demande['exigencesAttendues'];
        $voyageurs = $exigences['adultes'] + \count($exigences['agesEnfants']);
        $debut = new \DateTimeImmutable($exigences['dateDebut']);
        $proposes = [];
        $ecartes = [];
        foreach ($demande['attendu']['candidats']['etapes'] as $etape) {
            $resultat = $this->logements->executer($this->contexte, [
                'destination' => $demande['attendu']['destinations'][0],
                'dateDebut' => $debut->format('Y-m-d'),
                'nombreDeNuits' => $etape['nuits'],
                'voyageurs' => $voyageurs,
                'accessibleObligatoire' => self::accessibleObligatoire($exigences),
                'envies' => $exigences['envies'],
            ]);
            foreach ($resultat['logements'] as $logement) {
                self::assertGreaterThanOrEqual($voyageurs, $logement['capacite'] * $logement['unitesNecessaires'], $fichier.' '.$logement['nom']);
                self::assertSame($etape['nuits'], array_sum(array_column($logement['tarifs'], 'nuits')), $fichier.' '.$logement['nom']);
            }
            array_push($proposes, ...array_column($resultat['logements'], 'nom'));
            array_push($ecartes, ...array_column($resultat['ecartes'], 'nom'));
            $debut = $debut->modify(\sprintf('+%d days', $etape['nuits']));
        }

        if ($demande['attendu']['passageAuConseiller']) {
            self::assertSame([], $proposes, $fichier);

            return;
        }
        foreach ($demande['attendu']['logementsCompatibles'] as $nom) {
            self::assertContains($nom, $proposes, $fichier);
        }
        foreach ($demande['attendu']['exclusions'] as $exclusion) {
            self::assertNotContains($exclusion['prestation'], $proposes, $fichier);
        }
    }

    public function testLesRaisonsDesLogementsEcartes(): void
    {
        // Association de 35 personnes à Rome : 18 chambres doubles, il faut un tarif de groupe.
        $rome = $this->logements->executer($this->contexte, ['destination' => 'Rome', 'dateDebut' => '2027-04-15', 'nombreDeNuits' => 4, 'voyageurs' => 35, 'accessibleObligatoire' => false]);
        self::assertContains(['nom' => 'Hôtel Colosseo, chambre double', 'raison' => 'Capacité de 2 personnes : il faudrait 18 chambres, au-delà de 4 il faut un tarif de groupe.'], $rome['ecartes']);
        self::assertContains(['nom' => 'Appartement Trastevere', 'raison' => 'Capacité de 5 personnes pour 35 voyageurs : ce logement de 2 chambres se loue entier, une seule fois.'], $rome['ecartes']);

        // Séminaire de 40 personnes à Marrakech : le riad privatisé n'en loge que 12.
        $marrakech = $this->logements->executer($this->contexte, ['destination' => 'marrakech', 'dateDebut' => '2027-03-10', 'nombreDeNuits' => 2, 'voyageurs' => 40, 'accessibleObligatoire' => false]);
        self::assertSame(['Palmeraie Resort & Séminaires'], array_column($marrakech['logements'], 'nom'));
        self::assertContains(['nom' => 'Riad Dar Yasmine, riad entier', 'raison' => 'Capacité de 12 personnes pour 40 voyageurs : ce logement de 6 chambres se loue entier, une seule fois.'], $marrakech['ecartes']);

        // Fauteuil roulant à Lisbonne, et un séjour trop court pour l'appartement.
        $lisbonne = $this->logements->executer($this->contexte, ['destination' => 'lisbonne', 'dateDebut' => '2027-05-14', 'nombreDeNuits' => 2, 'voyageurs' => 2, 'accessibleObligatoire' => true]);
        self::assertContains(['nom' => 'Appartement Bairro Alto', 'raison' => 'Non accessible : un voyageur a besoin d\'un logement accessible.'], $lisbonne['ecartes']);
        $court = $this->logements->executer($this->contexte, ['destination' => 'lisbonne', 'dateDebut' => '2027-05-14', 'nombreDeNuits' => 2, 'voyageurs' => 4, 'accessibleObligatoire' => false]);
        self::assertContains(['nom' => 'Appartement Bairro Alto', 'raison' => 'Séjour minimum de 3 nuits pour un séjour de 2 nuits.'], $court['ecartes']);

        // Islande en janvier : aucun tarif.
        $islande = $this->logements->executer($this->contexte, ['destination' => 'islande', 'dateDebut' => '2027-01-16', 'nombreDeNuits' => 2, 'voyageurs' => 2, 'accessibleObligatoire' => false]);
        self::assertSame([], $islande['logements']);
        self::assertContains(['nom' => 'Guesthouse Reykjavik Centre', 'raison' => 'Aucun tarif saisi pour la nuit du 16/01/2027.'], $islande['ecartes']);
    }

    public function testUnLogementDonneSesTarifsNuitParNuitEtSesUnites(): void
    {
        // Trois voyageurs à Monteverde : deux chambres pour deux ; nuits d'avril (haute saison, × 1,2) puis de mai (× 0,9).
        $resultat = $this->logements->executer($this->contexte, ['destination' => 'Costa Rica', 'dateDebut' => '2027-04-29', 'nombreDeNuits' => 4, 'voyageurs' => 3, 'accessibleObligatoire' => false, 'categorie' => 'ecolodge']);
        self::assertCount(1, $resultat['logements']);
        $monteverde = $resultat['logements'][0];

        self::assertSame('Ecolodge Monteverde', $monteverde['nom']);
        self::assertIsInt($monteverde['logementId']);
        self::assertSame(2, $monteverde['unitesNecessaires']);
        self::assertSame([
            ['du' => '2027-04-29', 'au' => '2027-05-01', 'nuits' => 2, 'prixParNuitEuros' => 144],
            ['du' => '2027-05-01', 'au' => '2027-05-03', 'nuits' => 2, 'prixParNuitEuros' => 108],
        ], $monteverde['tarifs']);
        self::assertSame([], $resultat['ecartes'], 'Une autre catégorie n\'est pas « écartée » : elle n\'a pas été demandée.');
    }

    public function testLesLogementsQuiRepondentAuxEnviesViennentEnPremier(): void
    {
        $sansEnvie = $this->logements->executer($this->contexte, ['destination' => 'santorin', 'dateDebut' => '2027-06-12', 'nombreDeNuits' => 7, 'voyageurs' => 2, 'accessibleObligatoire' => false]);
        $romantique = $this->logements->executer($this->contexte, ['destination' => 'santorin', 'dateDebut' => '2027-06-12', 'nombreDeNuits' => 7, 'voyageurs' => 2, 'accessibleObligatoire' => false, 'envies' => ['romantique']]);

        // Du moins cher au plus cher, sauf si une envie départage.
        self::assertSame(['Hôtel Fira Blue, chambre triple', 'Suite Caldera Oia, piscine privée', 'Villa Imerovigli'], array_column($sansEnvie['logements'], 'nom'));
        self::assertSame('Suite Caldera Oia, piscine privée', $romantique['logements'][0]['nom']);
        self::assertSame(['romantique'], $romantique['logements'][0]['enviesCorrespondantes']);
    }

    /**
     * @param array<string, mixed> $demande
     */
    #[DataProvider('demandesTest')]
    public function testChaqueDemandeTestRetrouveSesActivites(string $fichier, array $demande): void
    {
        $exigences = $demande['exigencesAttendues'];
        $resultat = $this->activites->executer($this->contexte, [
            'destination' => $demande['attendu']['destinations'][0],
            'date' => $exigences['dateDebut'],
            'adultes' => $exigences['adultes'],
            'agesEnfants' => $exigences['agesEnfants'],
            'accessibleObligatoire' => self::accessibleObligatoire($exigences),
            'envies' => $exigences['envies'],
        ]);
        $proposees = array_column($resultat['activites'], 'nom');

        if ($demande['attendu']['passageAuConseiller']) {
            self::assertSame([], $proposees, $fichier);

            return;
        }
        $candidats = $demande['attendu']['candidats'];
        foreach ([...$candidats['activitesDemandees'], ...($candidats['activitesOptionnelles'] ?? [])] as $nom) {
            self::assertContains($nom, $proposees, $fichier);
        }
        // Une activité exclue pour la famille n'est proposée qu'aux voyageurs qui ont l'âge (Teide : les adultes).
        $parNom = array_column($resultat['activites'], null, 'nom');
        foreach ($demande['attendu']['exclusions'] as $exclusion) {
            if (isset($parNom[$exclusion['prestation']])) {
                self::assertNotSame([], $parNom[$exclusion['prestation']]['exclus'], $fichier.' '.$exclusion['prestation']);
            }
        }
        foreach ($resultat['activites'] as $activite) {
            self::assertGreaterThan(0, $activite['participants'], $fichier.' '.$activite['nom']);
            self::assertGreaterThanOrEqual($activite['participants'], $activite['capacite'] * $activite['sessions'], $fichier.' '.$activite['nom']);
            self::assertGreaterThan(0, $activite['prixParPersonneEuros'], $fichier.' '.$activite['nom']);
        }
    }

    public function testLesRaisonsDesActivitesEcartees(): void
    {
        // Famille à Tenerife, enfants de 6 et 9 ans : ce que toute la famille peut faire d'abord, puis ce que seuls
        // les parents peuvent faire, avec les enfants exclus. Rien n'est écarté pour l'âge.
        $tenerife = $this->activites->executer($this->contexte, ['destination' => 'tenerife', 'date' => '2027-02-13', 'adultes' => 2, 'agesEnfants' => [9, 6], 'accessibleObligatoire' => false]);
        self::assertSame([
            ['Observation des baleines et dauphins', 4, []],
            ['Baptême de plongée', 2, ['enfant de 6 ans', 'enfant de 9 ans']],
            ['Randonnée guidée au Teide', 2, ['enfant de 6 ans', 'enfant de 9 ans']],
        ], array_map(static fn (array $a): array => [$a['nom'], $a['participants'], $a['exclus']], $tenerife['activites']));
        self::assertSame([], $tenerife['ecartees']);
        $aine = $this->activites->executer($this->contexte, ['destination' => 'tenerife', 'date' => '2027-02-13', 'adultes' => 2, 'agesEnfants' => [11], 'accessibleObligatoire' => false]);
        self::assertSame([3, []], [$aine['activites'][1]['participants'], $aine['activites'][1]['exclus']], 'L\'aîné de 11 ans peut plonger.');

        $lisbonne = $this->activites->executer($this->contexte, ['destination' => 'lisbonne', 'date' => '2027-05-14', 'adultes' => 2, 'agesEnfants' => [], 'accessibleObligatoire' => true]);
        self::assertSame([['nom' => 'Visite guidée de l\'Alfama et du tram 28', 'raison' => 'Non accessible : un voyageur a besoin d\'une activité accessible.']], $lisbonne['ecartees']);

        $courtes = $this->activites->executer($this->contexte, ['destination' => 'tenerife', 'date' => '2027-02-13', 'adultes' => 2, 'agesEnfants' => [], 'accessibleObligatoire' => false, 'dureeMaxMinutes' => 150]);
        self::assertContains(['nom' => 'Randonnée guidée au Teide', 'raison' => 'Dure 6 h, plus que les 2 h 30 souhaitées.'], $courtes['ecartees']);
        self::assertSame(['Baptême de plongée'], array_column($courtes['activites'], 'nom'));

        $islande = $this->activites->executer($this->contexte, ['destination' => 'islande', 'date' => '2027-01-16', 'adultes' => 2, 'agesEnfants' => [], 'accessibleObligatoire' => false]);
        self::assertSame([], $islande['activites']);
        self::assertContains(['nom' => 'Baignade dans une source chaude', 'raison' => 'Aucun tarif saisi au 16/01/2027.'], $islande['ecartees']);
    }

    public function testUneCapaciteTropPetiteDonnePlusieursSessions(): void
    {
        $resultat = $this->activites->executer($this->contexte, ['destination' => 'marrakech', 'date' => '2027-03-10', 'adultes' => 40, 'agesEnfants' => [], 'accessibleObligatoire' => false, 'envies' => ['cuisine']]);
        $cuisine = $resultat['activites'][0];

        self::assertSame('Cours de cuisine marocaine et tajine', $cuisine['nom']);
        self::assertSame([40, 16, 3], [$cuisine['participants'], $cuisine['capacite'], $cuisine['sessions']]);
        self::assertSame(54, $cuisine['prixParPersonneEuros']);
    }

    /**
     * @param array<string, mixed> $entree
     */
    #[DataProvider('entreesRefusees')]
    public function testUneEntreeRefuseeEstExpliqueeAuModele(string $outil, array $entree, string $message): void
    {
        $this->expectException(OutilException::class);
        $this->expectExceptionMessage($message);
        ('logements' === $outil ? $this->logements : $this->activites)->executer($this->contexte, $entree);
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function entreesRefusees(): iterable
    {
        $sejour = ['destination' => 'santorin', 'dateDebut' => '2027-06-12', 'nombreDeNuits' => 7, 'voyageurs' => 2, 'accessibleObligatoire' => false];
        $sortie = ['destination' => 'santorin', 'date' => '2027-06-12', 'adultes' => 2, 'agesEnfants' => [], 'accessibleObligatoire' => false];

        yield 'destination inconnue' => ['logements', ['destination' => 'paris'] + $sejour, 'La destination « paris » n\'existe pas dans le catalogue.'];
        yield 'destination absente' => ['activites', ['destination' => ''] + $sortie, 'Indique la destination'];
        yield 'date mal écrite' => ['logements', ['dateDebut' => '12/06/2027'] + $sejour, 'dateDebut doit être une date au format AAAA-MM-JJ, reçu « 12/06/2027 ».'];
        yield 'zéro nuit' => ['logements', ['nombreDeNuits' => 0] + $sejour, 'nombreDeNuits doit être compris entre 1 et 60.'];
        yield 'accessibilité absente' => ['logements', array_diff_key($sejour, ['accessibleObligatoire' => true]), 'Indique accessibleObligatoire'];
        yield 'âge en texte' => ['activites', ['agesEnfants' => ['6 ans']] + $sortie, 'agesEnfants doit être une liste de nombres entiers entre 0 et 17.'];
        yield 'envies en texte' => ['activites', ['envies' => 'plage'] + $sortie, 'envies doit être une liste.'];
    }

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function demandesTest(): array
    {
        return ChiffrageDesDemandesTestTest::demandesTest();
    }

    /**
     * @param array<string, mixed> $exigences
     */
    private static function accessibleObligatoire(array $exigences): bool
    {
        return [] !== array_filter($exigences['contraintes'], static fn (string $c): bool => str_contains($c, 'fauteuil'));
    }
}
