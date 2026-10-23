<?php

namespace App\Chiffrage;

/**
 * Arbitrages chiffrés quand même l'essentiel dépasse le budget (#206, docs/chiffrage.md) :
 * 1. moins de nuits : le séjour le plus long qui tient dans le budget, sinon une nuit de moins ;
 * 2. une autre période : les mêmes nuits aux dates les moins chères, à six mois au plus des dates demandées ;
 * 3. les deux à la fois, quand ni l'un ni l'autre ne suffit seul.
 * Chaque arbitrage est chiffré au niveau essentiel par BudgetCalculator : même formule, mêmes tarifs.
 * Changer de catégorie d'hébergement ou de destination demande d'autres candidats : c'est à l'agent Recherche de
 * chercher, puis de rappeler retenir_combinaison, qui chiffre à nouveau.
 */
final class ArbitrageCalculator
{
    /** Jusqu'où chercher une période moins chère, de part et d'autre des dates demandées. */
    public const FENETRE_JOURS = 182;

    public function __construct(private readonly BudgetCalculator $calculateur)
    {
    }

    /**
     * @param \DateTimeImmutable $aPartirDu aucune date proposée avant ce jour (aujourd'hui en production)
     *
     * @return list<Arbitrage> vide si le budget suffit, s'il n'y en a pas, ou si rien n'est moins cher
     */
    public function proposer(DemandeDeChiffrage $demande, \DateTimeImmutable $aPartirDu): array
    {
        $budget = $demande->budgetTotalCentimes();
        $actuel = $this->calculateur->essentiel($demande);
        if (null === $budget || null === $actuel || $actuel->totalCentimes <= $budget) {
            return [];
        }

        $arbitrages = [];
        $moinsDeNuits = $this->moinsDeNuits($demande, $budget);
        if (null !== $moinsDeNuits) {
            $arbitrages[] = $moinsDeNuits;
        }
        $autrePeriode = $this->autrePeriode($demande, $budget, $aPartirDu, Arbitrage::AUTRE_PERIODE, $actuel->totalCentimes);
        if (null !== $autrePeriode) {
            $arbitrages[] = $autrePeriode;
        }

        // Ensemble seulement si aucun ne suffit seul : sinon, l'arbitrage le plus simple suffit.
        if (null !== $moinsDeNuits && null !== $autrePeriode && !$moinsDeNuits->tientDansLeBudget() && !$autrePeriode->tientDansLeBudget()) {
            $lesDeux = $this->autrePeriode(
                $demande->avecEtapes($moinsDeNuits->etapes),
                $budget,
                $aPartirDu,
                Arbitrage::LES_DEUX,
                min($moinsDeNuits->essentiel->totalCentimes, $autrePeriode->essentiel->totalCentimes),
            );
            if (null !== $lesDeux) {
                $arbitrages[] = $lesDeux;
            }
        }

        return $arbitrages;
    }

    /**
     * Retire des nuits, une par une, à l'étape la plus longue (chaque étape garde au moins une nuit), jusqu'à tenir
     * dans le budget. Si même une seule nuit par étape ne suffit pas, propose une nuit de moins, chiffrée.
     */
    private function moinsDeNuits(DemandeDeChiffrage $demande, int $budget): ?Arbitrage
    {
        $etapes = $demande->etapes;
        $premiere = null;
        while (null !== ($etapes = self::retirerUneNuit($etapes))) {
            $essentiel = $this->calculateur->essentiel($demande->avecEtapes($etapes));
            if (null === $essentiel) {
                continue;
            }
            $arbitrage = new Arbitrage(Arbitrage::MOINS_DE_NUITS, self::libelleNuits($etapes), $etapes, $essentiel, $budget);
            if ($arbitrage->tientDansLeBudget()) {
                return $arbitrage;
            }
            $premiere ??= $arbitrage;
        }

        return $premiere;
    }

    /**
     * Décale tout le voyage, jour par jour, dans la fenêtre de six mois (sans passer avant $aPartirDu), et garde les
     * dates les moins chères ; à prix égal, les plus proches des dates demandées. Rien si ce n'est pas moins cher
     * que $aBattre.
     *
     * @param Arbitrage::AUTRE_PERIODE|Arbitrage::LES_DEUX $type
     */
    private function autrePeriode(DemandeDeChiffrage $demande, int $budget, \DateTimeImmutable $aPartirDu, string $type, int $aBattre): ?Arbitrage
    {
        $meilleur = null;
        for ($decalage = -self::FENETRE_JOURS; $decalage <= self::FENETRE_JOURS; ++$decalage) {
            if (0 === $decalage && Arbitrage::AUTRE_PERIODE === $type) {
                continue;
            }
            $etapes = array_map(
                static fn (Etape $e): Etape => new Etape($e->dateDebut->modify(\sprintf('%+d days', $decalage)), $e->nombreDeNuits, $e->logements),
                $demande->etapes,
            );
            if ($etapes[0]->dateDebut < $aPartirDu) {
                continue;
            }
            $essentiel = $this->calculateur->essentiel($demande->avecEtapes($etapes));
            if (null === $essentiel || $essentiel->totalCentimes >= $aBattre) {
                continue;
            }
            $plusProche = null !== $meilleur && $essentiel->totalCentimes === $meilleur[1]->totalCentimes && abs($decalage) < abs($meilleur[2]);
            if (null === $meilleur || $essentiel->totalCentimes < $meilleur[1]->totalCentimes || $plusProche) {
                $meilleur = [$etapes, $essentiel, $decalage];
            }
        }
        if (null === $meilleur) {
            return null;
        }

        [$etapes, $essentiel] = $meilleur;
        $libelle = Arbitrage::LES_DEUX === $type
            ? self::libelleNuits($etapes).', à une période moins chère'
            : \sprintf('Partir à une période moins chère, %s', Periode::sejour($etapes[0]->dateDebut, $demande->nombreDeNuits()));

        return new Arbitrage($type, $libelle, $etapes, $essentiel, $budget);
    }

    /**
     * Les étapes avec une nuit de moins à la plus longue (la dernière à égalité), ou null si chaque étape n'a plus
     * qu'une nuit. Les étapes suivantes commencent un jour plus tôt.
     *
     * @param list<Etape> $etapes
     *
     * @return list<Etape>|null
     */
    private static function retirerUneNuit(array $etapes): ?array
    {
        $plusLongue = null;
        foreach ($etapes as $index => $etape) {
            if ($etape->nombreDeNuits > 1 && (null === $plusLongue || $etape->nombreDeNuits >= $etapes[$plusLongue]->nombreDeNuits)) {
                $plusLongue = $index;
            }
        }
        if (null === $plusLongue) {
            return null;
        }

        $resultat = [];
        $debut = $etapes[0]->dateDebut;
        foreach ($etapes as $index => $etape) {
            $nuits = $etape->nombreDeNuits - ($index === $plusLongue ? 1 : 0);
            $resultat[] = new Etape($debut, $nuits, $etape->logements);
            $debut = $debut->modify(\sprintf('+%d days', $nuits));
        }

        return $resultat;
    }

    /**
     * « Réduire à 3 nuits, du 21/10/2027 au 24/10/2027 ».
     *
     * @param list<Etape> $etapes
     */
    private static function libelleNuits(array $etapes): string
    {
        $nuits = array_sum(array_map(static fn (Etape $e): int => $e->nombreDeNuits, $etapes));

        return \sprintf('Réduire à %s, %s', Montant::quantite($nuits, 'nuit'), Periode::sejour($etapes[0]->dateDebut, $nuits));
    }
}
