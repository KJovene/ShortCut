<?php

namespace App\Chiffrage;

use App\Entity\Destination;
use App\Entity\Prestation;
use App\Entity\RegleDePrix;
use App\Enum\TypeDeVoyage;
use App\Enum\TypePrestataire;
use App\Enum\TypePrestation;
use App\Enum\UniteTarif;
use App\Repository\PrestataireRepository;
use App\Repository\PrestationRepository;
use App\Repository\RegleDePrixRepository;
use App\Texte\Correspondance;
use Psr\Clock\ClockInterface;

/**
 * Prix « à partir de » affichés sur le site public. Ce sont des prix de vente (marge de l'agence comprise), calculés
 * sur les tarifs des périodes pas encore passées : les tarifs d'achat ne sortent jamais d'ici.
 *
 * Par personne, pour une destination : 7 nuits à deux dans le logement le moins cher pouvant les accueillir, vol
 * aller-retour depuis Paris et transferts au bas de leur fourchette, marge, frais de dossier du type de voyage
 * principal partagés entre les deux voyageurs ; arrondi à la dizaine d'euros supérieure.
 */
final class PrixAPartirDe
{
    public const NUITS = 7;
    public const VOYAGEURS = 2;
    public const VILLE_DE_DEPART = 'Paris';

    /** Exemples par type de prestation, les moins chers d'abord. */
    public const EXEMPLES_PAR_TYPE = 3;

    public function __construct(
        private readonly PrestationRepository $prestations,
        private readonly PrestataireRepository $prestataires,
        private readonly RegleDePrixRepository $reglesDePrix,
        private readonly ClockInterface $horloge,
    ) {
    }

    /**
     * @return array{parPersonneEuros: int|null, exemples: list<array{prestation: Prestation, euros: int, unite: UniteTarif}>}
     */
    public function calculer(Destination $destination): array
    {
        $regle = $this->reglesDePrix->courante();
        $aujourdhui = $this->horloge->now()->format('Y-m-d');

        $parType = [];
        $nuitPourDeux = null;
        foreach ($this->prestations->duCatalogue([$destination]) as $prestation) {
            $achat = self::plusPetitTarifAVenir($prestation, $aujourdhui);
            if (null === $achat || null === $prestation->getType()) {
                continue;
            }
            $parType[$prestation->getType()->value][] = [
                'prestation' => $prestation,
                'euros' => self::euros($achat['centimes'] + BudgetCalculator::marge($achat['centimes'], $regle->getMargePourcentage()), 1),
                'unite' => $achat['unite'],
            ];
            if (TypePrestation::Logement === $prestation->getType() && UniteTarif::ParNuit === $achat['unite'] && $prestation->getCapacite() >= self::VOYAGEURS) {
                $nuitPourDeux = min($nuitPourDeux ?? $achat['centimes'], $achat['centimes']);
            }
        }

        $exemples = [];
        foreach (TypePrestation::cases() as $type) {
            $candidats = $parType[$type->value] ?? [];
            usort($candidats, static fn (array $a, array $b): int => $a['euros'] <=> $b['euros']);
            array_push($exemples, ...\array_slice($candidats, 0, self::EXEMPLES_PAR_TYPE));
        }

        return ['parPersonneEuros' => $this->parPersonne($destination, $regle, $nuitPourDeux), 'exemples' => $exemples];
    }

    /** Null s'il manque un logement pour deux, un vol depuis Paris, un transfert ou le type de voyage. */
    private function parPersonne(Destination $destination, RegleDePrix $regle, ?int $nuitPourDeux): ?int
    {
        $type = TypeDeVoyage::tryFrom($destination->getTypesDeVoyage()[0] ?? '');
        $transport = $this->transport((string) $destination->getVille());
        if (null === $nuitPourDeux || null === $type || null === $transport) {
            return null;
        }

        $cout = intdiv($nuitPourDeux * self::NUITS, self::VOYAGEURS) + $transport['vol'] + $transport['transfert'];
        $frais = intdiv($regle->getFraisDeDossierCentimes($type), self::VOYAGEURS);

        return self::euros($cout + BudgetCalculator::marge($cout, $regle->getMargePourcentage()) + $frais, 10);
    }

    /**
     * Bas des fourchettes du vol aller-retour depuis Paris et du transfert aéroport → hébergement, les moins chers
     * si plusieurs transporteurs desservent le trajet (mêmes règles que BudgetCalculator).
     *
     * @return array{vol: int, transfert: int}|null
     */
    private function transport(string $ville): ?array
    {
        $vol = null;
        $transfert = null;
        foreach ($this->prestataires->findBy(['type' => TypePrestataire::Transporteur]) as $transporteur) {
            foreach ($transporteur->getFourchettesTransport() as $fourchette) {
                if (!Correspondance::correspondent($fourchette['arrivee'], $ville)) {
                    continue;
                }
                if (str_starts_with(Correspondance::normaliser($fourchette['depart']), 'aeroport')) {
                    $transfert = min($transfert ?? $fourchette['minCentimes'], $fourchette['minCentimes']);
                } elseif (Correspondance::correspondent($fourchette['depart'], self::VILLE_DE_DEPART)) {
                    $vol = min($vol ?? $fourchette['minCentimes'], $fourchette['minCentimes']);
                }
            }
        }

        return null === $vol || null === $transfert ? null : ['vol' => $vol, 'transfert' => $transfert];
    }

    /**
     * Tarif d'achat le plus bas parmi les périodes qui ne sont pas encore passées.
     *
     * @return array{centimes: int, unite: UniteTarif}|null
     */
    private static function plusPetitTarifAVenir(Prestation $prestation, string $aujourdhui): ?array
    {
        $plusPetit = null;
        foreach ($prestation->getTarifs() as $tarif) {
            $centimes = $tarif->getPrixCentimes();
            if (null === $centimes || $tarif->getFin()?->format('Y-m-d') < $aujourdhui) {
                continue;
            }
            if (null === $plusPetit || $centimes < $plusPetit['centimes']) {
                $plusPetit = ['centimes' => $centimes, 'unite' => $tarif->getUnite()];
            }
        }

        return $plusPetit;
    }

    /** Arrondi au multiple d'euros supérieur : un prix « à partir de » ne doit jamais être sous-estimé. */
    private static function euros(int $centimes, int $pas): int
    {
        return intdiv($centimes + $pas * 100 - 1, $pas * 100) * $pas;
    }
}
