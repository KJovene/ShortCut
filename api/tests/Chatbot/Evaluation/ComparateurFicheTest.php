<?php

namespace App\Tests\Chatbot\Evaluation;

use App\Chatbot\Evaluation\ComparateurFiche;
use PHPUnit\Framework\TestCase;

final class ComparateurFicheTest extends TestCase
{
    public function testUneFicheIdentiqueEstEntierementCaptee(): void
    {
        $fiche = self::ficheAttendue();
        $comparaison = ComparateurFiche::comparer($fiche, $fiche);

        foreach (['typeDeVoyage', 'dateDebut', 'adultes', 'agesEnfants', 'budgetEuros', 'transportInclus', 'temperatureMax'] as $champ) {
            self::assertSame(ComparateurFiche::OK, $comparaison[$champ]['statut'], $champ);
        }
        // Le texte libre est laissé au juge.
        self::assertSame(ComparateurFiche::A_JUGER, $comparaison['lieuOuZone']['statut']);
        self::assertSame(ComparateurFiche::A_JUGER, $comparaison['envies']['statut']);
        // Aucune contrainte attendue, aucune obtenue : rien à juger.
        self::assertSame(ComparateurFiche::OK, $comparaison['contraintes']['statut']);
    }

    public function testRepereLesValeursManquantesFaussesEtInventees(): void
    {
        $obtenue = [
            'budgetEuros' => null,
            'adultes' => 3,
            'temperatureMax' => 30,
            'agesEnfants' => [9, 6],
            'villeDepart' => 'bordeaux (Mérignac)',
            'contraintes' => ['végétarien'],
        ] + self::ficheAttendue();

        $comparaison = ComparateurFiche::comparer(self::ficheAttendue(), $obtenue);

        self::assertSame(ComparateurFiche::MANQUANT, $comparaison['budgetEuros']['statut']);
        self::assertSame(ComparateurFiche::FAUX, $comparaison['adultes']['statut']);
        self::assertSame(ComparateurFiche::EN_TROP, $comparaison['temperatureMax']['statut']);
        self::assertSame(ComparateurFiche::OK, $comparaison['agesEnfants']['statut'], 'L\'ordre des âges ne compte pas.');
        self::assertSame(ComparateurFiche::OK, $comparaison['villeDepart']['statut']);
        self::assertSame(ComparateurFiche::A_JUGER, $comparaison['contraintes']['statut']);
    }

    public function testSeulesLesValeursAttenduesRenseigneesSontDesExigences(): void
    {
        self::assertTrue(ComparateurFiche::estExigence(false));
        self::assertTrue(ComparateurFiche::estExigence(0));
        self::assertFalse(ComparateurFiche::estExigence(null));
        self::assertFalse(ComparateurFiche::estExigence([]));
    }

    /**
     * @return array<string, mixed>
     */
    private static function ficheAttendue(): array
    {
        $json = (string) file_get_contents(\dirname(__DIR__, 2).'/demandes/01-sejour-famille-tenerife.json');

        return json_decode($json, true, flags: \JSON_THROW_ON_ERROR)['exigencesAttendues'];
    }
}
