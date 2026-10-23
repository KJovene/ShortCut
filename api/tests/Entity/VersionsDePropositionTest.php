<?php

namespace App\Tests\Entity;

use App\Entity\Demande;
use App\Entity\Proposition;
use App\Enum\StatutProposition;
use PHPUnit\Framework\TestCase;

/** Les versions de proposition d'une demande : la dernière selon un critère, le numéro suivant, ce que voit le client. */
final class VersionsDePropositionTest extends TestCase
{
    public function testLaDerniereVersionEtLeNumeroSuivant(): void
    {
        $demande = new Demande();
        self::assertNull($demande->derniereProposition());
        self::assertSame(1, $demande->prochainNumeroDeVersion());

        $v1 = (new Proposition())->setVersion(1)->setStatut(StatutProposition::Envoyee)->setEnvoyeeLe(new \DateTimeImmutable());
        $v3 = (new Proposition())->setVersion(3)->setStatut(StatutProposition::Brouillon);
        $v2 = (new Proposition())->setVersion(2)->setStatut(StatutProposition::Remplacee);
        $demande->addProposition($v1)->addProposition($v3)->addProposition($v2);

        self::assertSame($v3, $demande->derniereProposition());
        self::assertSame(4, $demande->prochainNumeroDeVersion());
        self::assertSame($v1, $demande->derniereProposition(static fn (Proposition $p): bool => $p->estVisibleDuClient()));
        self::assertSame($v1, $demande->derniereProposition(static fn (Proposition $p): bool => $p->estRecueParLeClient()));
    }

    public function testUneVersionConfirmeeEstRecueParLeClient(): void
    {
        self::assertFalse((new Proposition())->estRecueParLeClient());
        self::assertTrue((new Proposition())->setConfirmeeLe(new \DateTimeImmutable())->estRecueParLeClient());
        self::assertFalse((new Proposition())->setStatut(StatutProposition::Brouillon)->estVisibleDuClient());
        self::assertTrue((new Proposition())->setStatut(StatutProposition::Refusee)->estVisibleDuClient());
    }
}
