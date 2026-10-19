<?php

namespace App\Chiffrage;

use App\Entity\Prestation;
use App\Entity\TarifParPeriode;
use App\Enum\NiveauBudget;
use App\Texte\Correspondance;

/**
 * Service de chiffrage (#205) : applique docs/chiffrage.md au centime près. Le calcul se fait ici, jamais par l'IA.
 * Sans état : tout vient de la DemandeDeChiffrage, aucun accès à la base.
 *
 * - Poste par poste : hébergement nuit par nuit × unités, transport (vol + transfert) × voyageurs selon la position
 *   dans la fourchette, activités × participants qui ont l'âge minimum.
 * - Total = coût des prestations + marge + frais de dossier ; par personne = total ÷ voyageurs, arrondi au centime.
 * - Trois niveaux : essentiel (le moins cher), confort (le plus cher, toutes les suggestions), équilibré (selon le
 *   budget du client, voir equilibre()).
 */
final class BudgetCalculator
{
    /** Au-delà, l'agent doit réduire les candidats : le nombre de combinaisons explose (logements × suggestions). */
    public const COMBINAISONS_MAX = 20000;

    public function calculer(DemandeDeChiffrage $demande): ResultatChiffrage
    {
        $exclusions = [];
        try {
            $etapes = $this->chiffrerLesLogements($demande, $exclusions);
            $demandees = $this->chiffrerLesActivites($demande, $demande->activitesDemandees, true, $exclusions);
            $suggerees = $this->chiffrerLesActivites($demande, $demande->activitesSuggerees, false, $exclusions);
            $transport = $demande->transportInclus ? $this->trouverLeTransport($demande) : null;
        } catch (NonChiffrable $raison) {
            return new ResultatChiffrage($demande->budgetCentimes, $demande->budgetParPersonne, false, null, $raison, $exclusions);
        }

        $moinsChers = array_map(static fn (array $logements): LogementChiffre => $logements[0], $etapes);
        $plusChers = array_map(static fn (array $logements): LogementChiffre => $logements[\count($logements) - 1], $etapes);

        $essentiel = $this->niveau(NiveauBudget::Essentiel, $demande, $moinsChers, $demandees, PositionTransport::Minimum, $transport);
        $confort = $this->niveau(NiveauBudget::Confort, $demande, $plusChers, [...$demandees, ...$suggerees], PositionTransport::Maximum, $transport);

        $budget = $demande->budgetTotalCentimes();
        $insuffisant = null !== $budget && $essentiel->totalCentimes > $budget;
        $equilibre = $this->equilibre($demande, $etapes, $demandees, $suggerees, $transport, $budget, $insuffisant);

        return new ResultatChiffrage(
            $demande->budgetCentimes,
            $demande->budgetParPersonne,
            $insuffisant,
            [
                NiveauBudget::Essentiel->value => $essentiel,
                NiveauBudget::Equilibre->value => $equilibre,
                NiveauBudget::Confort->value => $confort,
            ],
            null,
            $exclusions,
        );
    }

    /**
     * Niveau équilibré :
     * - sans budget : le logement de prix médian (le moins cher des deux du milieu si leur nombre est pair) et les
     *   seules activités demandées, transport au milieu ;
     * - budget insuffisant : la combinaison la moins chère, transport au milieu (pour information) ;
     * - sinon : parmi toutes les combinaisons (un logement par étape, les activités demandées et une partie des
     *   suggestions), celle au total le plus élevé qui tient dans le budget, transport au milieu ; si aucune ne tient,
     *   même recherche avec le transport au minimum (l'essentiel tient, il y en a donc toujours une).
     *   « La plus complète » se mesure au total : le cas 07 (Santorin) préfère la suite avec la croisière à l'hôtel
     *   avec deux activités. À total égal, celle qui a le plus d'activités.
     *
     * @param list<list<LogementChiffre>>                                                                                                                                                        $etapes
     * @param list<ActiviteChiffree>                                                                                                                                                             $demandees
     * @param list<ActiviteChiffree>                                                                                                                                                             $suggerees
     * @param array{vol: array{depart: string, arrivee: string, minCentimes: int, maxCentimes: int}, transfert: array{depart: string, arrivee: string, minCentimes: int, maxCentimes: int}}|null $transport
     */
    private function equilibre(DemandeDeChiffrage $demande, array $etapes, array $demandees, array $suggerees, ?array $transport, ?int $budget, bool $insuffisant): Niveau
    {
        if (null === $budget) {
            $medians = array_map(static fn (array $logements): LogementChiffre => $logements[intdiv(\count($logements) - 1, 2)], $etapes);

            return $this->niveau(NiveauBudget::Equilibre, $demande, $medians, $demandees, PositionTransport::Milieu, $transport);
        }
        if ($insuffisant) {
            $moinsChers = array_map(static fn (array $logements): LogementChiffre => $logements[0], $etapes);

            return $this->niveau(NiveauBudget::Equilibre, $demande, $moinsChers, $demandees, PositionTransport::Milieu, $transport);
        }

        $nombre = array_product(array_map('count', $etapes)) * (2 ** \count($suggerees));
        if ($nombre > self::COMBINAISONS_MAX) {
            throw new \InvalidArgumentException(\sprintf('Trop de combinaisons à comparer (%d) : donne moins de logements par étape ou moins de suggestions.', $nombre));
        }

        foreach ([PositionTransport::Milieu, PositionTransport::Minimum] as $position) {
            $meilleur = null;
            $meilleurTotal = -1;
            foreach ($this->combinaisonsDeLogements($etapes) as $logements) {
                foreach ($this->sousEnsembles($suggerees) as $ajouts) {
                    $activites = [...$demandees, ...$ajouts];
                    $total = $this->total($demande, $logements, $activites, $position, $transport);
                    if ($total > $budget) {
                        continue;
                    }
                    if ($total > $meilleurTotal || ($total === $meilleurTotal && null !== $meilleur && \count($activites) > \count($meilleur[1]))) {
                        $meilleur = [$logements, $activites];
                        $meilleurTotal = $total;
                    }
                }
            }
            if (null !== $meilleur) {
                return $this->niveau(NiveauBudget::Equilibre, $demande, $meilleur[0], $meilleur[1], $position, $transport);
            }
        }

        // Inatteignable : sans budget insuffisant, l'essentiel tient au minimum. Gardé pour l'analyse statique.
        throw new \LogicException('Aucune combinaison ne tient dans le budget alors que l\'essentiel y tient.');
    }

    /**
     * Chaque logement candidat, chiffré pour son étape et trié du moins cher au plus cher. Les candidats sans tarif
     * à une des nuits, ou dont le séjour minimum dépasse l'étape, sont écartés ; une étape sans candidat restant
     * rend la demande non chiffrable.
     *
     * @param list<array{prestation: string, raison: string}> $exclusions
     *
     * @return list<list<LogementChiffre>>
     */
    private function chiffrerLesLogements(DemandeDeChiffrage $demande, array &$exclusions): array
    {
        $etapes = [];
        foreach ($demande->etapes as $etape) {
            $chiffres = [];
            $premiereRaison = null;
            foreach ($etape->logements as $logement) {
                try {
                    $chiffres[] = $this->chiffrerUnLogement($demande, $etape, $logement);
                } catch (NonChiffrable $raison) {
                    $exclusions[] = ['prestation' => $raison->prestation, 'raison' => $raison->raison];
                    $premiereRaison ??= $raison;
                }
            }
            if ([] === $chiffres) {
                throw $premiereRaison ?? new \LogicException('Étape sans logement.');
            }
            usort($chiffres, static fn (LogementChiffre $a, LogementChiffre $b): int => [$a->centimes(), $a->logement->getNom()] <=> [$b->centimes(), $b->logement->getNom()]);
            $etapes[] = $chiffres;
        }

        return $etapes;
    }

    private function chiffrerUnLogement(DemandeDeChiffrage $demande, Etape $etape, Prestation $logement): LogementChiffre
    {
        $nom = (string) $logement->getNom();
        $tarifs = [];
        $prixDesNuits = [];
        for ($i = 0; $i < $etape->nombreDeNuits; ++$i) {
            $date = $etape->nuit($i);
            $tarif = self::tarifA($logement, $date) ?? throw new NonChiffrable($nom, $date, 'Aucun tarif saisi à cette date.');
            $prixDesNuits[] = (int) $tarif->getPrixCentimes();
            if (!\in_array($tarif, $tarifs, true)) {
                $tarifs[] = $tarif;
            }
        }

        $minimum = $tarifs[0]->getSejourMinimumNuits();
        if (null !== $minimum && $minimum > $etape->nombreDeNuits) {
            throw new NonChiffrable($nom, $etape->dateDebut, \sprintf('séjour minimum de %s.', Montant::quantite($minimum, 'nuit')));
        }

        $unites = (int) ceil($demande->voyageurs() / max(1, $logement->getCapacite()));

        return new LogementChiffre(
            $logement,
            $etape,
            $unites,
            new Poste(Poste::HEBERGEMENT, $nom, self::calculDesNuits($prixDesNuits, $unites), array_sum($prixDesNuits) * $unites),
            $tarifs,
        );
    }

    /**
     * « 3 nuits × 110 € », « 4 nuits × 144 € × 2 unités », « (2 nuits × 110 € + 1 nuit × 130 €) × 2 unités ».
     *
     * @param list<int> $prixDesNuits
     */
    private static function calculDesNuits(array $prixDesNuits, int $unites): string
    {
        $tranches = [];
        foreach ($prixDesNuits as $prix) {
            $derniere = array_key_last($tranches);
            if (null !== $derniere && $tranches[$derniere][1] === $prix) {
                ++$tranches[$derniere][0];
            } else {
                $tranches[] = [1, $prix];
            }
        }

        $calcul = implode(' + ', array_map(
            static fn (array $t): string => Montant::quantite($t[0], 'nuit').' × '.Montant::euros($t[1]),
            $tranches,
        ));
        if ($unites > 1) {
            $calcul = (\count($tranches) > 1 ? '('.$calcul.')' : $calcul).' × '.Montant::quantite($unites, 'unité');
        }

        return $calcul;
    }

    /**
     * Activités au tarif du premier jour du voyage, payées par les voyageurs qui ont l'âge minimum. Une activité
     * demandée sans tarif rend la demande non chiffrable ; une suggestion sans tarif est écartée.
     *
     * @param list<Prestation>                                $activites
     * @param list<array{prestation: string, raison: string}> $exclusions
     *
     * @return list<ActiviteChiffree>
     */
    private function chiffrerLesActivites(DemandeDeChiffrage $demande, array $activites, bool $demandees, array &$exclusions): array
    {
        $jour = $demande->premierJour();
        $chiffrees = [];
        foreach ($activites as $activite) {
            $nom = (string) $activite->getNom();
            $tarif = self::tarifA($activite, $jour);
            if (null === $tarif) {
                if ($demandees) {
                    throw new NonChiffrable($nom, $jour, 'Aucun tarif saisi à cette date.');
                }
                $exclusions[] = ['prestation' => $nom, 'raison' => 'Aucun tarif saisi au '.$jour->format('d/m/Y').'.'];
                continue;
            }

            // Les adultes ont toujours l'âge minimum : seuls les enfants peuvent être écartés d'une activité.
            $ageMinimum = $activite->getAgeMinimum() ?? 0;
            $participants = $demande->adultes + \count(array_filter($demande->agesEnfants, static fn (int $age): bool => $age >= $ageMinimum));

            $prix = (int) $tarif->getPrixCentimes();
            $chiffrees[] = new ActiviteChiffree(
                $activite,
                $participants,
                (int) ceil($participants / max(1, $activite->getCapacite())),
                new Poste(Poste::ACTIVITE, $nom, Montant::quantite($participants, 'participant').' × '.Montant::euros($prix), $participants * $prix),
            );
        }

        return $chiffrees;
    }

    /**
     * Fourchettes du vol aller-retour (ville de départ → ville de la destination) et du transfert aéroport → hébergement.
     * Les villes saisies à la main sont rapprochées avec Correspondance. Si plusieurs transporteurs desservent le
     * trajet, la fourchette la moins chère (minimum le plus bas) est retenue.
     *
     * @return array{vol: array{depart: string, arrivee: string, minCentimes: int, maxCentimes: int}, transfert: array{depart: string, arrivee: string, minCentimes: int, maxCentimes: int}}
     */
    private function trouverLeTransport(DemandeDeChiffrage $demande): array
    {
        $ville = (string) $demande->destination->getVille();
        $vols = [];
        $transferts = [];
        foreach ($demande->transporteurs as $transporteur) {
            foreach ($transporteur->getFourchettesTransport() as $fourchette) {
                if (!Correspondance::correspondent($fourchette['arrivee'], $ville)) {
                    continue;
                }
                if (str_starts_with(Correspondance::normaliser($fourchette['depart']), 'aeroport')) {
                    $transferts[] = $fourchette;
                } elseif (Correspondance::correspondent($fourchette['depart'], (string) $demande->villeDepart)) {
                    $vols[] = $fourchette;
                }
            }
        }

        $moinsChere = static function (array $fourchettes): array {
            usort($fourchettes, static fn (array $a, array $b): int => $a['minCentimes'] <=> $b['minCentimes']);

            return $fourchettes[0];
        };
        $trajet = \sprintf('%s – %s', $demande->villeDepart, $ville);

        return [
            'vol' => [] === $vols ? throw new NonChiffrable('Vol aller-retour '.$trajet, null, 'Aucune fourchette de prix saisie pour ce trajet.') : $moinsChere($vols),
            'transfert' => [] === $transferts ? throw new NonChiffrable('Transferts aéroport – hébergement à '.$ville, null, 'Aucune fourchette de prix saisie pour ce transfert.') : $moinsChere($transferts),
        ];
    }

    /**
     * @param list<LogementChiffre>                                                                                                                                                              $logements
     * @param list<ActiviteChiffree>                                                                                                                                                             $activites
     * @param array{vol: array{depart: string, arrivee: string, minCentimes: int, maxCentimes: int}, transfert: array{depart: string, arrivee: string, minCentimes: int, maxCentimes: int}}|null $transport
     */
    private function niveau(NiveauBudget $niveau, DemandeDeChiffrage $demande, array $logements, array $activites, PositionTransport $position, ?array $transport): Niveau
    {
        $postes = [
            ...array_map(static fn (LogementChiffre $l): Poste => $l->poste, $logements),
            ...$this->postesDeTransport($demande, $position, $transport),
            ...array_map(static fn (ActiviteChiffree $a): Poste => $a->poste, $activites),
        ];
        $cout = array_sum(array_map(static fn (Poste $p): int => $p->centimes, $postes));
        $marge = self::marge($cout, $demande->margePourcentage);
        $total = $cout + $marge + $demande->fraisDeDossierCentimes;

        return new Niveau(
            $niveau,
            array_map(static fn (LogementChiffre $l): string => (string) $l->logement->getNom(), $logements),
            array_map(static fn (ActiviteChiffree $a): string => (string) $a->activite->getNom(), $activites),
            $position,
            $postes,
            $cout,
            $marge,
            $demande->fraisDeDossierCentimes,
            $total,
            (int) round($total / $demande->voyageurs()),
            $this->hypotheses($demande, $logements, $activites, $position, null !== $transport),
        );
    }

    /**
     * Total d'une combinaison, sans construire le détail (pour comparer les combinaisons de l'équilibré).
     *
     * @param list<LogementChiffre>                                                                                                                                                              $logements
     * @param list<ActiviteChiffree>                                                                                                                                                             $activites
     * @param array{vol: array{depart: string, arrivee: string, minCentimes: int, maxCentimes: int}, transfert: array{depart: string, arrivee: string, minCentimes: int, maxCentimes: int}}|null $transport
     */
    private function total(DemandeDeChiffrage $demande, array $logements, array $activites, PositionTransport $position, ?array $transport): int
    {
        $cout = array_sum(array_map(static fn (LogementChiffre $l): int => $l->centimes(), $logements))
            + array_sum(array_map(static fn (ActiviteChiffree $a): int => $a->centimes(), $activites))
            + array_sum(array_map(static fn (Poste $p): int => $p->centimes, $this->postesDeTransport($demande, $position, $transport)));

        return $cout + self::marge($cout, $demande->margePourcentage) + $demande->fraisDeDossierCentimes;
    }

    /** Marge arrondie au centime le plus proche. */
    private static function marge(int $coutCentimes, int $pourcentage): int
    {
        return intdiv($coutCentimes * $pourcentage + 50, 100);
    }

    /**
     * @param array{vol: array{depart: string, arrivee: string, minCentimes: int, maxCentimes: int}, transfert: array{depart: string, arrivee: string, minCentimes: int, maxCentimes: int}}|null $transport
     *
     * @return list<Poste>
     */
    private function postesDeTransport(DemandeDeChiffrage $demande, PositionTransport $position, ?array $transport): array
    {
        if (null === $transport) {
            return [];
        }

        $voyageurs = $demande->voyageurs();
        $postes = [];
        foreach ([
            'vol' => \sprintf('Vol aller-retour %s – %s', $transport['vol']['depart'], $transport['vol']['arrivee']),
            'transfert' => \sprintf('Transferts aéroport – hébergement à %s', $transport['transfert']['arrivee']),
        ] as $cle => $libelle) {
            $prix = $position->prix($transport[$cle]['minCentimes'], $transport[$cle]['maxCentimes']);
            $postes[] = new Poste(Poste::TRANSPORT, $libelle, Montant::quantite($voyageurs, 'voyageur').' × '.Montant::euros($prix), $voyageurs * $prix, true);
        }

        return $postes;
    }

    /**
     * Hypothèses affichées sous le niveau : tarifs et dates par logement, catégorie et unités, participants des
     * activités, transport, frais de dossier.
     *
     * @param list<LogementChiffre>  $logements
     * @param list<ActiviteChiffree> $activites
     *
     * @return list<string>
     */
    private function hypotheses(DemandeDeChiffrage $demande, array $logements, array $activites, PositionTransport $position, bool $transport): array
    {
        $hypotheses = [];
        foreach ($logements as $l) {
            $periodes = implode(' et ', array_map(
                static fn (TarifParPeriode $t): string => \sprintf('du %s au %s', $t->getDebut()?->format('d/m/Y'), $t->getFin()?->format('d/m/Y')),
                $l->tarifs,
            ));
            $hypotheses[] = \sprintf(
                '%s : %s%s, %s à partir du %s, %s %s',
                $l->logement->getNom(),
                null !== $l->logement->getCategorie() ? $l->logement->getCategorie().', ' : '',
                Montant::quantite($l->unites, 'unité'),
                Montant::quantite($l->etape->nombreDeNuits, 'nuit'),
                $l->etape->dateDebut->format('d/m/Y'),
                1 === \count($l->tarifs) ? 'tarif' : 'tarifs',
                $periodes,
            );
        }
        foreach ($activites as $a) {
            if ($a->participants < $demande->voyageurs()) {
                $hypotheses[] = \sprintf('%s : %s sur %d (âge minimum %d ans)', $a->activite->getNom(), Montant::quantite($a->participants, 'participant'), $demande->voyageurs(), $a->activite->getAgeMinimum());
            }
            if ($a->sessions > 1) {
                $hypotheses[] = \sprintf('%s : organisée en %d sessions de %d personnes au plus', $a->activite->getNom(), $a->sessions, $a->activite->getCapacite());
            }
        }
        $hypotheses[] = $transport
            ? \sprintf('Vols et transferts estimés au %s, %s', $position->label(), Montant::quantite($demande->voyageurs(), 'voyageur'))
            : 'Transport non inclus';
        $hypotheses[] = \sprintf('Frais de dossier %s : %s', mb_strtolower($demande->typeDeVoyage->label()), Montant::euros($demande->fraisDeDossierCentimes));

        return $hypotheses;
    }

    private static function tarifA(Prestation $prestation, \DateTimeImmutable $date): ?TarifParPeriode
    {
        $jour = $date->format('Y-m-d');
        foreach ($prestation->getTarifs() as $tarif) {
            if ($tarif->getDebut()?->format('Y-m-d') <= $jour && $jour <= $tarif->getFin()?->format('Y-m-d')) {
                return $tarif;
            }
        }

        return null;
    }

    /**
     * Toutes les façons de choisir un logement par étape.
     *
     * @param list<list<LogementChiffre>> $etapes
     *
     * @return iterable<list<LogementChiffre>>
     */
    private function combinaisonsDeLogements(array $etapes): iterable
    {
        if ([] === $etapes) {
            yield [];

            return;
        }

        $premiere = array_shift($etapes);
        foreach ($this->combinaisonsDeLogements($etapes) as $suite) {
            foreach ($premiere as $logement) {
                yield [$logement, ...$suite];
            }
        }
    }

    /**
     * Tous les sous-ensembles des suggestions, dans leur ordre d'origine.
     *
     * @param list<ActiviteChiffree> $suggerees
     *
     * @return iterable<list<ActiviteChiffree>>
     */
    private function sousEnsembles(array $suggerees): iterable
    {
        $n = \count($suggerees);
        for ($masque = 0; $masque < (1 << $n); ++$masque) {
            $choix = [];
            for ($i = 0; $i < $n; ++$i) {
                if ($masque & (1 << $i)) {
                    $choix[] = $suggerees[$i];
                }
            }
            yield $choix;
        }
    }
}
