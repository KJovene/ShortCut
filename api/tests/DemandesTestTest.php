<?php

namespace App\Tests;

use App\DataFixtures\Donnees\Catalogue;
use App\Enum\TypeDeVoyage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Cohérence des 10 demandes test (tests/demandes) avec le catalogue de démo.
 */
final class DemandesTestTest extends TestCase
{
    private const CHAMPS_EXIGENCES = ['typeDeVoyage', 'lieuOuZone', 'villeDepart', 'dateDebut', 'dateFin', 'datesFlexibles', 'nombreDeJours', 'adultes', 'enfants', 'agesEnfants', 'budgetEuros', 'budgetParPersonne', 'transportInclus', 'envies', 'contraintes'];

    public function testIlYADixDemandesEtChaqueTypeApparaitAuMoinsDeuxFois(): void
    {
        $demandes = self::demandes();
        self::assertCount(10, $demandes);

        $types = array_count_values(array_map(fn (array $d): string => $d['typeDeVoyage'], $demandes));
        foreach (TypeDeVoyage::cases() as $type) {
            self::assertGreaterThanOrEqual(2, $types[$type->value] ?? 0, $type->label());
        }
    }

    public function testLesCasDifficilesSontCouverts(): void
    {
        $cas = array_merge(...array_values(array_map(fn (array $d): array => $d['casParticuliers'], self::demandes())));

        foreach (['budget_insuffisant', 'envie_floue', 'changement_en_cours', 'periode_sans_donnees'] as $attendu) {
            self::assertContains($attendu, $cas);
        }
    }

    /**
     * @param array<string, mixed> $demande
     */
    #[DataProvider('fournirDemandes')]
    public function testLesFraisDeDossierSuiventLeBaremeParTypeDeVoyage(string $fichier, array $demande): void
    {
        $bareme = ['sejour' => 100, 'noces' => 150, 'circuit' => 150, 'groupe' => 200, 'entreprise' => 200];

        self::assertSame($bareme[$demande['typeDeVoyage']], $demande['formule']['fraisDeDossierEuros'], $fichier);
    }

    /**
     * @param array<string, mixed> $demande
     */
    #[DataProvider('fournirDemandes')]
    public function testLaDemandeEstCoherenteAvecLeCatalogue(string $fichier, array $demande): void
    {
        self::assertSame(self::CHAMPS_EXIGENCES, array_keys($demande['exigencesAttendues']), $fichier);
        self::assertSame($demande['typeDeVoyage'], $demande['exigencesAttendues']['typeDeVoyage']);
        self::assertNotEmpty($demande['conversation']);

        $attendu = $demande['attendu'];
        foreach ($attendu['destinations'] as $slug) {
            self::assertArrayHasKey($slug, Catalogue::destinations(), $fichier);
        }

        $prestations = self::nomsDesPrestations();
        $candidats = $attendu['candidats'];
        $cites = [
            ...$attendu['logementsCompatibles'],
            ...array_column($attendu['exclusions'], 'prestation'),
            ...array_merge(...array_column($candidats['etapes'], 'logements')),
            ...$candidats['activitesDemandees'],
            ...$candidats['activitesOptionnelles'],
        ];
        foreach ($attendu['budgetsParNiveau'] ?? [] as $niveau) {
            $cites = [...$cites, ...array_column($niveau['detail']['hebergements'], 'prestation'), ...array_column($niveau['detail']['activites'], 'prestation')];
        }
        foreach ($cites as $nom) {
            self::assertContains($nom, $prestations, $fichier);
        }
    }

    /**
     * @param array<string, mixed> $demande
     */
    #[DataProvider('fournirDemandes')]
    public function testLesNiveauxDeBudgetSontOrdonnesEtRespectentLeBudget(string $fichier, array $demande): void
    {
        $attendu = $demande['attendu'];
        $niveaux = $attendu['budgetsParNiveau'];

        if ($attendu['passageAuConseiller']) {
            self::assertNull($niveaux, $fichier.' : pas de chiffrage sans données');

            return;
        }

        self::assertNotNull($niveaux, $fichier);
        self::assertLessThanOrEqual($niveaux['equilibre']['totalCentimes'], $niveaux['essentiel']['totalCentimes'], $fichier);
        self::assertLessThanOrEqual($niveaux['confort']['totalCentimes'], $niveaux['equilibre']['totalCentimes'], $fichier);

        foreach ($niveaux as $nom => $niveau) {
            $detail = $niveau['detail'];
            $cout = array_sum(array_column([...$detail['hebergements'], ...$detail['transports'], ...$detail['activites']], 'centimes'));
            self::assertSame($cout, $detail['coutPrestationsCentimes'], $fichier.' '.$nom);
            self::assertSame((int) round($cout * 0.2), $detail['margeCentimes'], $fichier.' '.$nom.' : marge de 20 %');
            self::assertSame($demande['formule']['fraisDeDossierEuros'] * 100, $detail['fraisDeDossierCentimes'], $fichier.' '.$nom);
            self::assertSame($cout + $detail['margeCentimes'] + $detail['fraisDeDossierCentimes'], $niveau['totalCentimes'], $fichier.' '.$nom);
        }

        $exigences = $demande['exigencesAttendues'];
        $cle = $exigences['budgetParPersonne'] ? 'parPersonneCentimes' : 'totalCentimes';
        if ($attendu['budgetInsuffisant']) {
            self::assertGreaterThan($exigences['budgetEuros'] * 100, $niveaux['essentiel'][$cle], $fichier.' : même l\'essentiel dépasse le budget');
            self::assertNotEmpty($attendu['arbitragesAttendus'] ?? [], $fichier);
        } elseif (null !== $exigences['budgetEuros']) {
            self::assertLessThanOrEqual($exigences['budgetEuros'] * 100, $niveaux['equilibre'][$cle], $fichier.' : l\'équilibré tient dans le budget');
        }
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>}>
     */
    public static function fournirDemandes(): iterable
    {
        foreach (self::demandes() as $fichier => $demande) {
            yield $fichier => [$fichier, $demande];
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function demandes(): array
    {
        $demandes = [];
        foreach (glob(__DIR__.'/demandes/[0-9][0-9]-*.json') ?: [] as $chemin) {
            $demande = json_decode((string) file_get_contents($chemin), true, flags: \JSON_THROW_ON_ERROR);
            self::assertIsArray($demande);
            $demandes[basename($chemin)] = $demande;
        }

        return $demandes;
    }

    /**
     * @return list<string>
     */
    private static function nomsDesPrestations(): array
    {
        return array_column([...Catalogue::logements(), ...Catalogue::activites()], 'nom');
    }
}
