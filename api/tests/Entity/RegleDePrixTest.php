<?php

namespace App\Tests\Entity;

use App\Entity\RegleDePrix;
use App\Enum\TypeDeVoyage;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** Barème des frais de dossier par type de voyage (décision #165, docs/chiffrage.md). */
final class RegleDePrixTest extends KernelTestCase
{
    public function testLeBaremeParDefautSuitLaDecisionDeLAgence(): void
    {
        $regle = new RegleDePrix();

        self::assertSame(20, $regle->getMargePourcentage());
        self::assertSame(10000, $regle->getFraisDeDossierCentimes(TypeDeVoyage::Sejour));
        self::assertSame(15000, $regle->getFraisDeDossierCentimes(TypeDeVoyage::Noces));
        self::assertSame(15000, $regle->getFraisDeDossierCentimes(TypeDeVoyage::Circuit));
        self::assertSame(20000, $regle->getFraisDeDossierCentimes(TypeDeVoyage::Groupe));
        self::assertSame(20000, $regle->getFraisDeDossierCentimes(TypeDeVoyage::Entreprise));
    }

    public function testDesFraisHorsDesBornesOuManquantsSontRefuses(): void
    {
        $validateur = self::getContainer()->get(ValidatorInterface::class);

        self::assertCount(0, $validateur->validate(new RegleDePrix()));

        $regle = (new RegleDePrix())->setFraisDossierParTypeCentimes(['sejour' => 5000, 'noces' => 15000, 'circuit' => 15000, 'groupe' => 20000]);
        $erreurs = $validateur->validate($regle);

        // Séjour sous le minimum de 100 € et entreprise absente.
        self::assertCount(2, $erreurs);
        self::assertSame('fraisDossierParTypeCentimes', $erreurs[0]->getPropertyPath());
    }
}
