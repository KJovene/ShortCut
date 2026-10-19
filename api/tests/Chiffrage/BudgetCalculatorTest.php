<?php

namespace App\Tests\Chiffrage;

use App\Chiffrage\BudgetCalculator;
use App\Chiffrage\DemandeDeChiffrage;
use App\Chiffrage\Etape;
use App\Chiffrage\Montant;
use App\Chiffrage\PositionTransport;
use App\Chiffrage\Poste;
use App\Entity\Destination;
use App\Entity\Prestataire;
use App\Entity\Prestation;
use App\Entity\TarifParPeriode;
use App\Enum\NiveauBudget;
use App\Enum\TypeDeVoyage;
use App\Enum\TypePrestataire;
use App\Enum\TypePrestation;
use App\Enum\UniteTarif;
use PHPUnit\Framework\TestCase;

/**
 * Cas limites du service de chiffrage, sans base de données : saisons, unités, tarifs manquants, âges, transport.
 * Les dix demandes test et les exemples de docs/chiffrage.md sont dans ChiffrageDesDemandesTestTest.
 */
final class BudgetCalculatorTest extends TestCase
{
    private Destination $destination;
    private Prestataire $transporteur;

    protected function setUp(): void
    {
        $this->destination = (new Destination())->setPays('Portugal')->setVille('Lisbonne')->setSlug('lisbonne');
        $this->transporteur = (new Prestataire())->setNom('Air Test')->setType(TypePrestataire::Transporteur)->setFourchettesTransport([
            ['depart' => 'Paris', 'arrivee' => 'Lisbonne', 'minCentimes' => 10000, 'maxCentimes' => 20001],
            ['depart' => 'Aéroport de Lisbonne', 'arrivee' => 'Lisbonne', 'minCentimes' => 2000, 'maxCentimes' => 4000],
        ]);
    }

    public function testUneNuitACachevalSurDeuxSaisonsEtPlusieursUnites(): void
    {
        // 3 voyageurs dans un logement pour 2 : 2 unités ; nuits du 30/06 (basse) puis du 01/07 et 02/07 (haute).
        $logement = $this->logement('Casa', 2, [['2027-06-01', '2027-06-30', 10000], ['2027-07-01', '2027-07-31', 13000]]);
        $resultat = (new BudgetCalculator())->calculer($this->demande([new Etape(new \DateTimeImmutable('2027-06-30'), 3, [$logement])], adultes: 3));

        $poste = $resultat->niveau(NiveauBudget::Essentiel)?->postes(Poste::HEBERGEMENT)[0];
        self::assertSame('(1 nuit × 100 € + 2 nuits × 130 €) × 2 unités', $poste?->calcul);
        self::assertSame((10000 + 2 * 13000) * 2, $poste->centimes);
        self::assertContains(
            'Casa : Appartement, 2 unités, 3 nuits à partir du 30/06/2027, tarifs du 01/06/2027 au 30/06/2027 et du 01/07/2027 au 31/07/2027',
            $resultat->niveau(NiveauBudget::Essentiel)->hypotheses,
        );
    }

    public function testUnLogementSansTarifOuSejourTropCourtEstEcarte(): void
    {
        $sansTarif = $this->logement('Sans tarif', 2, [['2027-01-01', '2027-01-31', 5000]]);
        $sejourMinimum = $this->logement('Minimum 5 nuits', 2, [['2027-05-01', '2027-05-31', 6000]], sejourMinimum: 5);
        $retenu = $this->logement('Retenu', 2, [['2027-05-01', '2027-05-31', 9000]]);

        $resultat = (new BudgetCalculator())->calculer($this->demande([new Etape(new \DateTimeImmutable('2027-05-10'), 3, [$sansTarif, $sejourMinimum, $retenu])]));

        self::assertSame(['Retenu'], $resultat->niveau(NiveauBudget::Essentiel)?->logements);
        self::assertSame([
            ['prestation' => 'Sans tarif', 'raison' => 'Aucun tarif saisi à cette date.'],
            ['prestation' => 'Minimum 5 nuits', 'raison' => 'séjour minimum de 5 nuits.'],
        ], $resultat->exclusions);
    }

    public function testUneEtapeSansLogementChiffrableRendLaDemandeNonChiffrable(): void
    {
        $logement = $this->logement('Guesthouse', 2, [['2027-05-01', '2027-05-31', 9000]]);
        $resultat = (new BudgetCalculator())->calculer($this->demande([new Etape(new \DateTimeImmutable('2027-05-30'), 3, [$logement])]));

        self::assertNull($resultat->niveaux);
        self::assertSame('Guesthouse', $resultat->nonChiffrable?->prestation);
        self::assertSame('2027-06-01', $resultat->nonChiffrable->date?->format('Y-m-d'));
        self::assertSame('Aucun tarif saisi à cette date.', $resultat->nonChiffrable->raison);
    }

    public function testLesActivitesSelonLeurTarifEtLAgeDesParticipants(): void
    {
        $logement = $this->logement('Hôtel', 4, [['2027-05-01', '2027-05-31', 10000]]);
        $plongee = $this->activite('Plongée', 12, 2, [['2027-05-01', '2027-05-31', 5000]]);
        $bebes = $this->activite('Réservé aux adultes', 30, 10, [['2027-05-01', '2027-05-31', 1000]]);
        $horsSaison = $this->activite('Hors saison', null, 10, [['2027-08-01', '2027-08-31', 1000]]);

        $demande = $this->demande(
            [new Etape(new \DateTimeImmutable('2027-05-10'), 2, [$logement])],
            adultes: 2,
            agesEnfants: [8, 14],
            suggerees: [$plongee, $bebes, $horsSaison],
        );
        // Les adultes ont toujours l'âge minimum : seuls les enfants sont écartés (8 ans pour la plongée dès 12 ans).
        $resultat = (new BudgetCalculator())->calculer($demande);
        $confort = $resultat->niveau(NiveauBudget::Confort);

        self::assertSame(['Plongée', 'Réservé aux adultes'], $confort?->activites);
        self::assertSame('3 participants × 50 €', $confort->postes(Poste::ACTIVITE)[0]->calcul);
        self::assertContains('Plongée : 3 participants sur 4 (âge minimum 12 ans)', $confort->hypotheses);
        self::assertContains('Plongée : organisée en 2 sessions de 2 personnes au plus', $confort->hypotheses);
        self::assertSame([['prestation' => 'Hors saison', 'raison' => 'Aucun tarif saisi au 10/05/2027.']], $resultat->exclusions);
    }

    public function testUneActiviteDemandeeSansTarifRendLaDemandeNonChiffrable(): void
    {
        $logement = $this->logement('Hôtel', 2, [['2027-05-01', '2027-05-31', 10000]]);
        $activite = $this->activite('Croisière', null, 10, [['2027-07-01', '2027-07-31', 9000]]);

        $resultat = (new BudgetCalculator())->calculer($this->demande([new Etape(new \DateTimeImmutable('2027-05-10'), 2, [$logement])], demandees: [$activite]));

        self::assertSame('Croisière', $resultat->nonChiffrable?->prestation);
        self::assertNull($resultat->niveaux);
    }

    public function testSansTransportInclusNiVilleDeDepart(): void
    {
        $logement = $this->logement('Hôtel', 2, [['2027-05-01', '2027-05-31', 10000]]);
        $demande = new DemandeDeChiffrage(
            TypeDeVoyage::Sejour, $this->destination, 2, [], null, false, null, false,
            [new Etape(new \DateTimeImmutable('2027-05-10'), 2, [$logement])], [], [], [], 20, 10000,
        );
        $essentiel = (new BudgetCalculator())->calculer($demande)->niveau(NiveauBudget::Essentiel);

        self::assertSame([], $essentiel?->postes(Poste::TRANSPORT));
        self::assertSame(20000 + 4000 + 10000, $essentiel->totalCentimes);
        self::assertContains('Transport non inclus', $essentiel->hypotheses);
    }

    public function testUneFourchetteManquanteRendLaDemandeNonChiffrable(): void
    {
        $logement = $this->logement('Hôtel', 2, [['2027-05-01', '2027-05-31', 10000]]);
        $etapes = [new Etape(new \DateTimeImmutable('2027-05-10'), 2, [$logement])];

        $sansVol = $this->demande($etapes, villeDepart: 'Nantes');
        self::assertSame('Vol aller-retour Nantes – Lisbonne', (new BudgetCalculator())->calculer($sansVol)->nonChiffrable?->prestation);

        $this->transporteur->setFourchettesTransport([['depart' => 'Paris', 'arrivee' => 'Lisbonne', 'minCentimes' => 10000, 'maxCentimes' => 20000]]);
        self::assertSame('Transferts aéroport – hébergement à Lisbonne', (new BudgetCalculator())->calculer($this->demande($etapes))->nonChiffrable?->prestation);
    }

    public function testLesVillesSontRapprocheesEtLaFourchetteLaMoinsChereRetenue(): void
    {
        $concurrent = (new Prestataire())->setNom('Air Cher')->setType(TypePrestataire::Transporteur)->setFourchettesTransport([
            ['depart' => 'PARIS', 'arrivee' => 'lisbonne', 'minCentimes' => 15000, 'maxCentimes' => 30000],
        ]);
        $logement = $this->logement('Hôtel', 2, [['2027-05-01', '2027-05-31', 10000]]);
        $demande = $this->demande([new Etape(new \DateTimeImmutable('2027-05-10'), 2, [$logement])], villeDepart: 'paris', transporteurs: [$concurrent, $this->transporteur]);

        $resultat = (new BudgetCalculator())->calculer($demande);
        $vol = $resultat->niveau(NiveauBudget::Equilibre)?->postes(Poste::TRANSPORT)[0];

        self::assertSame('Vol aller-retour Paris – Lisbonne', $vol?->libelle);
        // Milieu de 100 € et 200,01 € : 150,005 € arrondi au centime supérieur.
        self::assertSame('2 voyageurs × 150,01 €', $vol->calcul);
        self::assertTrue($vol->estime);
    }

    public function testSansBudgetLEquilibrePrendLeLogementMedian(): void
    {
        $logements = [
            $this->logement('A', 2, [['2027-05-01', '2027-05-31', 10000]]),
            $this->logement('B', 2, [['2027-05-01', '2027-05-31', 20000]]),
            $this->logement('C', 2, [['2027-05-01', '2027-05-31', 30000]]),
            $this->logement('D', 2, [['2027-05-01', '2027-05-31', 40000]]),
        ];
        $etape = static fn (array $l): array => [new Etape(new \DateTimeImmutable('2027-05-10'), 1, $l)];

        $pair = (new BudgetCalculator())->calculer($this->demande($etape(array_reverse($logements))));
        self::assertSame(['B'], $pair->niveau(NiveauBudget::Equilibre)?->logements);
        self::assertSame(['A'], $pair->niveau(NiveauBudget::Essentiel)?->logements);
        self::assertSame(['D'], $pair->niveau(NiveauBudget::Confort)?->logements);

        $impair = (new BudgetCalculator())->calculer($this->demande($etape(\array_slice($logements, 0, 3))));
        self::assertSame(['B'], $impair->niveau(NiveauBudget::Equilibre)?->logements);
    }

    public function testLeBudgetParPersonneEtLeRepliSurLeTransportMinimum(): void
    {
        $logements = [$this->logement('A', 2, [['2027-05-01', '2027-05-31', 10000]])];
        $etapes = [new Etape(new \DateTimeImmutable('2027-05-10'), 1, $logements)];
        $calculateur = new BudgetCalculator();

        // Essentiel : 100 + 2 × 100 + 2 × 20 = 340 € ; + 68 € de marge + 100 € de frais = 508 €, soit 254 € par personne.
        $essentiel = $calculateur->calculer($this->demande($etapes, budget: 25400, parPersonne: true));
        self::assertFalse($essentiel->budgetInsuffisant);
        self::assertSame(PositionTransport::Minimum, $essentiel->niveau(NiveauBudget::Equilibre)?->transport);

        $insuffisant = $calculateur->calculer($this->demande($etapes, budget: 25399, parPersonne: true));
        self::assertTrue($insuffisant->budgetInsuffisant);
        self::assertSame(PositionTransport::Milieu, $insuffisant->niveau(NiveauBudget::Equilibre)?->transport);

        $large = $calculateur->calculer($this->demande($etapes, budget: 100000));
        self::assertSame(PositionTransport::Milieu, $large->niveau(NiveauBudget::Equilibre)?->transport);
    }

    public function testATotalEgalLEquilibrePrendLePlusDActivites(): void
    {
        $logement = $this->logement('A', 2, [['2027-05-01', '2027-05-31', 10000]]);
        $gratuite = $this->activite('Balade libre', null, 10, [['2027-05-01', '2027-05-31', 0]]);
        $resultat = (new BudgetCalculator())->calculer($this->demande([new Etape(new \DateTimeImmutable('2027-05-10'), 1, [$logement])], budget: 100000, suggerees: [$gratuite]));

        self::assertSame(['Balade libre'], $resultat->niveau(NiveauBudget::Equilibre)?->activites);
    }

    public function testTropDeCombinaisonsEstRefuse(): void
    {
        $logements = array_map(fn (int $i): Prestation => $this->logement('L'.$i, 2, [['2027-05-01', '2027-05-31', 10000 + $i]]), range(1, 20));
        $activites = array_map(fn (int $i): Prestation => $this->activite('A'.$i, null, 10, [['2027-05-01', '2027-05-31', 1000]]), range(1, 10));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Trop de combinaisons/');
        (new BudgetCalculator())->calculer($this->demande([new Etape(new \DateTimeImmutable('2027-05-10'), 1, $logements)], budget: 10000000, suggerees: $activites));
    }

    public function testLesEntreesIncompletesSontRefusees(): void
    {
        $logement = $this->logement('A', 2, []);
        $refus = [
            fn () => new Etape(new \DateTimeImmutable(), 0, [$logement]),
            fn () => new Etape(new \DateTimeImmutable(), 1, []),
            fn () => new DemandeDeChiffrage(TypeDeVoyage::Sejour, $this->destination, 0, [], 'Paris', true, null, false, [new Etape(new \DateTimeImmutable(), 1, [$logement])], [], [], [], 20, 10000),
            fn () => new DemandeDeChiffrage(TypeDeVoyage::Sejour, $this->destination, 2, [], 'Paris', true, null, false, [], [], [], [], 20, 10000),
            fn () => new DemandeDeChiffrage(TypeDeVoyage::Sejour, $this->destination, 2, [], ' ', true, null, false, [new Etape(new \DateTimeImmutable(), 1, [$logement])], [], [], [], 20, 10000),
        ];
        foreach ($refus as $index => $creer) {
            try {
                $creer();
                self::fail('Entrée acceptée : cas '.$index);
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testLEcritureDesMontants(): void
    {
        self::assertSame('1 300 €', Montant::euros(130000));
        self::assertSame('32,50 €', Montant::euros(3250));
        self::assertSame(1300, Montant::nombre(130000));
        self::assertSame(32.5, Montant::nombre(3250));
        self::assertSame('1 nuit', Montant::quantite(1, 'nuit'));
        self::assertSame('1 250 voyageurs', Montant::quantite(1250, 'voyageur'));
        self::assertSame('maximum de la fourchette', PositionTransport::Maximum->label());
        self::assertSame(200, PositionTransport::Maximum->prix(100, 200));
    }

    /**
     * @param list<Etape>       $etapes
     * @param list<int>         $agesEnfants
     * @param list<Prestation>  $demandees
     * @param list<Prestation>  $suggerees
     * @param list<Prestataire> $transporteurs
     */
    private function demande(
        array $etapes,
        int $adultes = 2,
        array $agesEnfants = [],
        string $villeDepart = 'Paris',
        ?int $budget = null,
        bool $parPersonne = false,
        array $demandees = [],
        array $suggerees = [],
        ?array $transporteurs = null,
    ): DemandeDeChiffrage {
        return new DemandeDeChiffrage(
            TypeDeVoyage::Sejour, $this->destination, $adultes, $agesEnfants, $villeDepart, true, $budget, $parPersonne,
            $etapes, $demandees, $suggerees, $transporteurs ?? [$this->transporteur], 20, 10000,
        );
    }

    /**
     * @param list<array{string, string, int}> $tarifs [début, fin, prix en centimes]
     */
    private function logement(string $nom, int $capacite, array $tarifs, ?int $sejourMinimum = null): Prestation
    {
        $logement = (new Prestation(TypePrestation::Logement))->setNom($nom)->setCategorie('Appartement')->setCapacite($capacite)->setDestination($this->destination);
        foreach ($tarifs as [$debut, $fin, $prix]) {
            $logement->addTarif((new TarifParPeriode())->setDebut(new \DateTimeImmutable($debut))->setFin(new \DateTimeImmutable($fin))
                ->setPrixCentimes($prix)->setUnite(UniteTarif::ParNuit)->setSejourMinimumNuits($sejourMinimum));
        }

        return $logement;
    }

    /**
     * @param list<array{string, string, int}> $tarifs
     */
    private function activite(string $nom, ?int $ageMinimum, int $capacite, array $tarifs): Prestation
    {
        $activite = (new Prestation(TypePrestation::Activite))->setNom($nom)->setAgeMinimum($ageMinimum)->setCapacite($capacite)->setDureeMinutes(60)->setDestination($this->destination);
        foreach ($tarifs as [$debut, $fin, $prix]) {
            $activite->addTarif((new TarifParPeriode())->setDebut(new \DateTimeImmutable($debut))->setFin(new \DateTimeImmutable($fin))
                ->setPrixCentimes($prix)->setUnite(UniteTarif::ParPersonne));
        }

        return $activite;
    }
}
