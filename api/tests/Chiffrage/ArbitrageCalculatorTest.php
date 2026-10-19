<?php

namespace App\Tests\Chiffrage;

use App\Chiffrage\Arbitrage;
use App\Chiffrage\ArbitrageCalculator;
use App\Chiffrage\BudgetCalculator;
use App\Chiffrage\DemandeDeChiffrage;
use App\Chiffrage\Etape;
use App\Entity\Destination;
use App\Entity\Prestataire;
use App\Entity\Prestation;
use App\Entity\TarifParPeriode;
use App\Enum\TypeDeVoyage;
use App\Enum\TypePrestataire;
use App\Enum\TypePrestation;
use App\Enum\UniteTarif;
use PHPUnit\Framework\TestCase;

/**
 * Cas limites des arbitrages, sans base de données. Le cas « budget insuffisant » du jeu de démo (Marrakech)
 * est dans ChiffrageDesDemandesTestTest.
 *
 * Ici : vols 100 € et transferts 20 € par personne (2 voyageurs, soit 240 €), marge 20 %, frais 100 €.
 * Un logement à 100 € la nuit coûte donc 748 € pour 3 nuits, 628 € pour 2, 508 € pour 1.
 */
final class ArbitrageCalculatorTest extends TestCase
{
    private Destination $destination;
    private Prestataire $transporteur;
    private ArbitrageCalculator $arbitrages;
    private \DateTimeImmutable $aujourdhui;

    protected function setUp(): void
    {
        $this->destination = (new Destination())->setPays('Portugal')->setVille('Lisbonne')->setSlug('lisbonne');
        $this->transporteur = (new Prestataire())->setNom('Air Test')->setType(TypePrestataire::Transporteur)->setFourchettesTransport([
            ['depart' => 'Paris', 'arrivee' => 'Lisbonne', 'minCentimes' => 10000, 'maxCentimes' => 20000],
            ['depart' => 'Aéroport de Lisbonne', 'arrivee' => 'Lisbonne', 'minCentimes' => 2000, 'maxCentimes' => 4000],
        ]);
        $this->arbitrages = new ArbitrageCalculator(new BudgetCalculator());
        $this->aujourdhui = new \DateTimeImmutable('2026-10-19');
    }

    public function testRienAProposerQuandLeBudgetSuffitOuNExistePas(): void
    {
        $etapes = [new Etape(new \DateTimeImmutable('2027-05-10'), 3, [$this->logement([['2027-01-01', '2027-12-31', 10000]])])];

        self::assertSame([], $this->arbitrages->proposer($this->demande($etapes, 74800), $this->aujourdhui));
        self::assertSame([], $this->arbitrages->proposer($this->demande($etapes, null), $this->aujourdhui));
    }

    public function testRienAProposerSiLaDemandeNEstPasChiffrable(): void
    {
        $etapes = [new Etape(new \DateTimeImmutable('2027-05-10'), 3, [$this->logement([['2027-08-01', '2027-08-31', 10000]])])];

        self::assertSame([], $this->arbitrages->proposer($this->demande($etapes, 10000), $this->aujourdhui));
    }

    public function testMoinsDeNuitsQuiTientDansLeBudget(): void
    {
        // Même prix toute l'année : changer de dates ne sert à rien, seul « moins de nuits » est proposé.
        $etapes = [new Etape(new \DateTimeImmutable('2027-05-10'), 3, [$this->logement([['2027-01-01', '2027-12-31', 10000]])])];
        $arbitrages = $this->arbitrages->proposer($this->demande($etapes, 70000), $this->aujourdhui);

        self::assertCount(1, $arbitrages);
        self::assertSame(Arbitrage::MOINS_DE_NUITS, $arbitrages[0]->type);
        self::assertSame('Réduire à 2 nuits, du 10/05/2027 au 12/05/2027', $arbitrages[0]->libelle);
        self::assertSame(62800, $arbitrages[0]->essentiel->totalCentimes);
        self::assertTrue($arbitrages[0]->tientDansLeBudget());
        self::assertSame(-7200, $arbitrages[0]->ecartCentimes());
    }

    public function testUnCircuitPerdSaNuitALEtapeLaPlusLongue(): void
    {
        $logement = $this->logement([['2027-01-01', '2027-12-31', 10000]]);
        $etapes = [
            new Etape(new \DateTimeImmutable('2027-05-10'), 2, [$logement]),
            new Etape(new \DateTimeImmutable('2027-05-12'), 3, [$logement]),
        ];
        // 5 nuits : 988 € ; une nuit de moins à la 2e étape, la plus longue : 868 €, dans les 870 € du budget.
        $arbitrage = $this->arbitrages->proposer($this->demande($etapes, 87000), $this->aujourdhui)[0];

        self::assertSame([['2027-05-10', 2], ['2027-05-12', 2]], array_map(
            static fn (Etape $e): array => [$e->dateDebut->format('Y-m-d'), $e->nombreDeNuits],
            $arbitrage->etapes,
        ));
    }

    public function testUneNuitParEtapeNeLaissePasDeNuitARetirer(): void
    {
        $etapes = [new Etape(new \DateTimeImmutable('2027-05-10'), 1, [$this->logement([['2027-01-01', '2027-12-31', 10000]])])];

        self::assertSame([], $this->arbitrages->proposer($this->demande($etapes, 10000), $this->aujourdhui));
    }

    public function testUnePeriodeMoinsChereNeRemontePasAvantAujourdhui(): void
    {
        // 50 € la nuit en avril, 100 € ensuite ; rien avant avril.
        $logement = $this->logement([['2027-04-01', '2027-04-30', 5000], ['2027-05-01', '2027-12-31', 10000]]);
        $etapes = [new Etape(new \DateTimeImmutable('2027-05-10'), 3, [$logement])];
        $demande = $this->demande($etapes, 60000);

        $periodes = array_values(array_filter(
            $this->arbitrages->proposer($demande, $this->aujourdhui),
            static fn (Arbitrage $a): bool => Arbitrage::AUTRE_PERIODE === $a->type,
        ));
        // Les trois dernières nuits d'avril (28, 29 et 30), au plus près des dates demandées : 150 € au lieu de 300 €.
        self::assertSame('Partir à une période moins chère, du 28/04/2027 au 01/05/2027', $periodes[0]->libelle);
        self::assertSame(56800, $periodes[0]->essentiel->totalCentimes);

        $sansAvril = $this->arbitrages->proposer($demande, new \DateTimeImmutable('2027-05-01'));
        self::assertSame([Arbitrage::MOINS_DE_NUITS], array_map(static fn (Arbitrage $a): string => $a->type, $sansAvril));
    }

    /**
     * @param list<Etape> $etapes
     */
    private function demande(array $etapes, ?int $budget): DemandeDeChiffrage
    {
        return new DemandeDeChiffrage(
            TypeDeVoyage::Sejour, $this->destination, 2, [], 'Paris', true, $budget, false,
            $etapes, [], [], [$this->transporteur], 20, 10000,
        );
    }

    /**
     * @param list<array{string, string, int}> $tarifs
     */
    private function logement(array $tarifs): Prestation
    {
        $logement = (new Prestation(TypePrestation::Logement))->setNom('Casa')->setCapacite(2)->setChambres(1)->setDestination($this->destination);
        foreach ($tarifs as [$debut, $fin, $prix]) {
            $logement->addTarif((new TarifParPeriode())->setDebut(new \DateTimeImmutable($debut))->setFin(new \DateTimeImmutable($fin))
                ->setPrixCentimes($prix)->setUnite(UniteTarif::ParNuit));
        }

        return $logement;
    }
}
