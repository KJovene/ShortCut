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
    private const CHAMPS_EXIGENCES = ['typeDeVoyage', 'lieuOuZone', 'villeDepart', 'dateDebut', 'dateFin', 'datesFlexibles', 'nombreDeJours', 'temperatureMin', 'temperatureMax', 'adultes', 'enfants', 'agesEnfants', 'budgetEuros', 'budgetParPersonne', 'transportInclus', 'envies', 'contraintes'];

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
        $cites = [...$attendu['logementsCompatibles'], ...array_column($attendu['exclusions'], 'prestation')];
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
        self::assertLessThanOrEqual($niveaux['equilibre']['totalEuros'], $niveaux['essentiel']['totalEuros'], $fichier);
        self::assertLessThanOrEqual($niveaux['confort']['totalEuros'], $niveaux['equilibre']['totalEuros'], $fichier);

        $exigences = $demande['exigencesAttendues'];
        $cle = $exigences['budgetParPersonne'] ? 'parPersonneEuros' : 'totalEuros';
        if ($attendu['budgetInsuffisant']) {
            self::assertGreaterThan($exigences['budgetEuros'], $niveaux['essentiel'][$cle], $fichier.' : même l\'essentiel dépasse le budget');
            self::assertNotEmpty($attendu['arbitragesAttendus'] ?? [], $fichier);
        } elseif (null !== $exigences['budgetEuros']) {
            self::assertLessThanOrEqual($exigences['budgetEuros'], $niveaux['equilibre'][$cle], $fichier.' : l\'équilibré tient dans le budget');
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
