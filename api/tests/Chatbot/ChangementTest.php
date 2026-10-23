<?php

namespace App\Tests\Chatbot;

use App\Chatbot\Changement;
use App\Chatbot\NouvelleVersion;
use App\Chatbot\Outil\OutilException;
use App\Demande\FicheDemande;
use App\Entity\Demande;
use App\Enum\TypeDeVoyage;
use PHPUnit\Framework\TestCase;

/** Ce qui a changé depuis une version, et la combinaison recalée sur les nouvelles dates (#211). */
final class ChangementTest extends TestCase
{
    public function testDeNouvellesDatesSeRecalculentSansNouvelleRecherche(): void
    {
        $avant = $this->lisbonne();
        $apres = $this->lisbonne()->setDateDebut(new \DateTimeImmutable('2027-05-21'))->setDateFin(new \DateTimeImmutable('2027-05-24'));

        $changement = Changement::entre(FicheDemande::depuis($avant), FicheDemande::depuis($apres));

        self::assertSame(['dateDebut', 'dateFin'], array_keys($changement->champs));
        self::assertSame('2027-05-14', $changement->champs['dateDebut']['avant']);
        self::assertFalse($changement->rechercheNecessaire());
        self::assertSame(['dates : du 21/05/2027 au 24/05/2027, 3 nuits'], $changement->resume());
    }

    public function testDeNouvellesEnviesDemandentUneRecherche(): void
    {
        $changement = Changement::entre(
            FicheDemande::depuis($this->lisbonne()),
            FicheDemande::depuis($this->lisbonne()->setEnvies(['gastronomie', 'culture'])->setAdultes(3)->setBudgetCentimes(150000)),
        );

        self::assertTrue($changement->rechercheNecessaire());
        self::assertSame([
            'voyageurs : 3 adultes',
            'budget : 1 500 € au total, transport inclus',
            'envies : gastronomie, culture',
        ], $changement->resume());
    }

    public function testLOrdreDUneListeNEstPasUnChangement(): void
    {
        $changement = Changement::entre(
            FicheDemande::depuis($this->lisbonne()->setEnvies(['gastronomie', 'culture'])),
            FicheDemande::depuis($this->lisbonne()->setEnvies(['culture', 'gastronomie'])),
        );

        self::assertTrue($changement->vide());
    }

    public function testLesEtapesSuiventLeNouveauDepartEtLaNouvelleDuree(): void
    {
        $choix = [
            'destination' => 'islande',
            'etapes' => [
                ['dateDebut' => '2027-07-01', 'nombreDeNuits' => 3, 'logementIds' => [1]],
                ['dateDebut' => '2027-07-04', 'nombreDeNuits' => 4, 'logementIds' => [2, 3]],
            ],
            'activitesDemandees' => [7],
            'activitesSuggerees' => [],
            'pourquoi' => 'Deux étapes.',
        ];

        // Une nuit de plus, partie une semaine plus tard : sur l'étape la plus longue.
        $plus = NouvelleVersion::choixAdapte($choix, (new Demande())->setDateDebut(new \DateTimeImmutable('2027-07-08'))->setNombreDeJours(8));
        self::assertSame([
            ['dateDebut' => '2027-07-08', 'nombreDeNuits' => 3, 'logementIds' => [1]],
            ['dateDebut' => '2027-07-11', 'nombreDeNuits' => 5, 'logementIds' => [2, 3]],
        ], $plus['etapes']);
        self::assertSame([7], $plus['activitesDemandees']);

        // Trois nuits de moins : prises une à une sur la plus longue du moment.
        $moins = NouvelleVersion::choixAdapte($choix, (new Demande())->setDateDebut(new \DateTimeImmutable('2027-07-01'))->setNombreDeJours(4));
        self::assertSame([2, 2], array_column($moins['etapes'], 'nombreDeNuits'));
        self::assertSame('2027-07-03', $moins['etapes'][1]['dateDebut']);

        $this->expectException(OutilException::class);
        NouvelleVersion::choixAdapte($choix, (new Demande())->setDateDebut(new \DateTimeImmutable('2027-07-01'))->setNombreDeJours(1));
    }

    private function lisbonne(): Demande
    {
        return (new Demande())->setTypeDeVoyage(TypeDeVoyage::Sejour)->setLieuOuZone('Lisbonne')->setVilleDepart('Lyon')
            ->setDateDebut(new \DateTimeImmutable('2027-05-14'))->setDateFin(new \DateTimeImmutable('2027-05-17'))->setNombreDeJours(3)
            ->setAdultes(2)->setBudgetCentimes(130000)->setEnvies(['gastronomie'])->setContraintes(['fauteuil roulant']);
    }
}
