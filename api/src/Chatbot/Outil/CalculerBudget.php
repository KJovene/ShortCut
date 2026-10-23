<?php

namespace App\Chatbot\Outil;

use App\Chatbot\ContexteAgent;
use App\Chiffrage\Arbitrage;
use App\Chiffrage\ArbitrageCalculator;
use App\Chiffrage\BudgetCalculator;
use App\Chiffrage\ChiffrageCache;
use App\Chiffrage\DemandeDeChiffrage;
use App\Chiffrage\Etape;
use App\Chiffrage\Montant;
use App\Chiffrage\Niveau;
use App\Chiffrage\Periode;
use App\Chiffrage\Poste;
use App\Chiffrage\ResultatChiffrage;
use App\Entity\Demande;
use App\Entity\Destination;
use App\Entity\Prestation;
use App\Enum\Langue;
use App\Enum\TypePrestataire;
use App\Enum\TypePrestation;
use App\Repository\DestinationRepository;
use App\Repository\PrestataireRepository;
use App\Repository\PrestationRepository;
use App\Repository\RegleDePrixRepository;
use Psr\Clock\ClockInterface;

/**
 * Outil `calculer_budget` (docs/outils.md) : le modèle donne les candidats, le serveur chiffre les trois niveaux
 * avec BudgetCalculator. Voyageurs, ville de départ, budget et type de voyage viennent de la demande courante :
 * le modèle ne les fournit pas. Montants en euros dans la sortie, pour qu'il les recopie sans erreur.
 * Si même l'essentiel dépasse le budget, la sortie propose des arbitrages chiffrés (ArbitrageCalculator).
 */
final class CalculerBudget implements Outil
{
    public const NOM = 'calculer_budget';

    /** Mention qui suit chaque présentation des totaux au client : l'estimation est datée (charte Détour). */
    public const AVERTISSEMENT = 'Prix estimés le %s à %s d\'après nos tarifs : votre conseiller vérifie chaque prix et chaque place avant de vous les confirmer.';
    public const AVERTISSEMENT_EN = 'Prices estimated on %s at %s from our rates: your advisor checks every price and every place before confirming them.';

    public function __construct(
        private readonly BudgetCalculator $calculateur,
        private readonly ArbitrageCalculator $arbitrages,
        private readonly ClockInterface $horloge,
        private readonly ChiffrageCache $cache,
        private readonly DestinationRepository $destinations,
        private readonly PrestationRepository $prestations,
        private readonly PrestataireRepository $prestataires,
        private readonly RegleDePrixRepository $reglesDePrix,
    ) {
    }

    public function nom(): string
    {
        return self::NOM;
    }

    /**
     * « Prix estimés le jeudi 22 octobre 2026 à 9 h 40 d'après nos tarifs : … », à l'heure de Paris ; en anglais,
     * « Prices estimated on Thursday 22 October 2026 at 09:40 … ».
     */
    public function avertissement(Langue $langue = Langue::Francais): string
    {
        $maintenant = $this->horloge->now()->setTimezone(new \DateTimeZone('Europe/Paris'));

        return Langue::Anglais === $langue
            ? \sprintf(self::AVERTISSEMENT_EN, $maintenant->format('l j F Y'), $maintenant->format('H:i'))
            : \sprintf(self::AVERTISSEMENT, Periode::jour($maintenant), Periode::heure($maintenant));
    }

    public function definition(): array
    {
        $ids = ['type' => 'array', 'items' => ['type' => 'integer']];

        return [
            'name' => $this->nom(),
            'description' => 'Calcule les budgets essentiel, équilibré et confort de la demande en cours, selon les règles de prix de l\'agence. Donne pour chaque étape les logements compatibles, puis les activités demandées par le client et celles que tu suggères. Ne calcule jamais de prix toi-même : reprends les montants renvoyés.',
            // Outil qui écrit (cache du chiffrage) : mode strict, voir docs/outils.md « Schémas ».
            'strict' => true,
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'destination' => ['type' => 'string', 'description' => 'Slug renvoyé par chercher_destinations, en minuscules (ex. « santorin »).'],
                    'etapes' => [
                        'type' => 'array',
                        'description' => 'Une étape pour un séjour, plusieurs pour un circuit, dans l\'ordre du voyage.',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'dateDebut' => ['type' => 'string', 'description' => 'Première nuit de l\'étape, AAAA-MM-JJ.'],
                                'nombreDeNuits' => ['type' => 'integer'],
                                'logementIds' => $ids + ['description' => 'Logements compatibles pour cette étape.'],
                            ],
                            'required' => ['dateDebut', 'nombreDeNuits', 'logementIds'],
                            'additionalProperties' => false,
                        ],
                    ],
                    'activitesDemandees' => $ids + ['description' => 'Activités que le client a explicitement demandées (incluses à tous les niveaux).'],
                    'activitesSuggerees' => $ids + ['description' => 'Activités en accord avec ses envies (ajoutées selon le niveau et le budget).'],
                ],
                'required' => ['destination', 'etapes', 'activitesDemandees', 'activitesSuggerees'],
                'additionalProperties' => false,
            ],
        ];
    }

    public function executer(ContexteAgent $contexte, array $entree): array
    {
        $demande = $contexte->demande;
        $aChiffrer = $this->demandeDeChiffrage($demande, $entree);
        try {
            $resultat = $this->calculateur->calculer($aChiffrer);
        } catch (\InvalidArgumentException $e) {
            // Trop de combinaisons, étape vide… : un message que le modèle peut corriger.
            throw new OutilException($e->getMessage(), previous: $e);
        }

        $sortie = $this->sortie($resultat);
        if ($resultat->budgetInsuffisant) {
            $sortie['arbitrages'] = array_map(
                self::arbitrage(...),
                $this->arbitrages->proposer($aChiffrer, $this->horloge->now()->setTime(0, 0)),
            );
        }
        if (null !== $resultat->niveaux) {
            $sortie = ['chiffrageId' => $this->cache->enregistrer($demande, $sortie)] + $sortie;
        }

        return $sortie;
    }

    /**
     * Les activités en option d'une combinaison (même entrée que l'outil), chacune à son prix de vente pour le groupe.
     *
     * @param array<string, mixed> $entree    destination et étapes, comme pour calculer_budget
     * @param list<int>            $activites identifiants des activités à proposer
     *
     * @return list<array{activite: Prestation, participants: int, centimes: int, parPersonneCentimes: int}>
     */
    public function options(Demande $demande, array $entree, array $activites): array
    {
        $aChiffrer = $this->demandeDeChiffrage($demande, ['activitesDemandees' => [], 'activitesSuggerees' => []] + $entree);
        $destination = $aChiffrer->destination;

        return $this->calculateur->options($aChiffrer, $this->prestations($activites, TypePrestation::Activite, $destination));
    }

    /**
     * La demande du client et les candidats de l'entrée, prêts à chiffrer.
     *
     * @param array<string, mixed> $entree
     */
    private function demandeDeChiffrage(Demande $demande, array $entree): DemandeDeChiffrage
    {
        $type = $demande->getTypeDeVoyage()
            ?? throw new OutilException('Le type de voyage n\'est pas encore connu : enregistre-le avec enregistrer_exigences avant de chiffrer.');
        if ($demande->isTransportInclus() && null === $demande->getVilleDepart()) {
            throw new OutilException('La ville de départ du client n\'est pas encore connue : demande-la et enregistre-la avec enregistrer_exigences avant de chiffrer.');
        }
        $destination = $this->destination($entree['destination'] ?? null);
        $regle = $this->reglesDePrix->courante();

        try {
            return new DemandeDeChiffrage(
                typeDeVoyage: $type,
                destination: $destination,
                adultes: $demande->getAdultes(),
                agesEnfants: $demande->agesDesEnfants(),
                villeDepart: $demande->getVilleDepart(),
                transportInclus: $demande->isTransportInclus(),
                budgetCentimes: $demande->getBudgetCentimes(),
                budgetParPersonne: $demande->isBudgetParPersonne(),
                etapes: $this->etapes($entree['etapes'] ?? null, $destination),
                activitesDemandees: $this->prestations($entree['activitesDemandees'] ?? [], TypePrestation::Activite, $destination),
                activitesSuggerees: $this->prestations($entree['activitesSuggerees'] ?? [], TypePrestation::Activite, $destination),
                transporteurs: $this->prestataires->findBy(['type' => TypePrestataire::Transporteur]),
                margePourcentage: $regle->getMargePourcentage(),
                fraisDeDossierCentimes: $regle->getFraisDeDossierCentimes($type),
            );
        } catch (\InvalidArgumentException $e) {
            throw new OutilException($e->getMessage(), previous: $e);
        }
    }

    /**
     * Une destination publiée, retrouvée par son slug sans tenir compte des majuscules ni des accents
     * (« Santorin » → santorin), ni d'une petite faute.
     */
    private function destination(mixed $slug): Destination
    {
        if (!\is_string($slug) || '' === trim($slug)) {
            throw new OutilException('Indique la destination (slug renvoyé par chercher_destinations).');
        }

        return $this->destinations->publieeCorrespondant($slug)
            ?? throw new OutilException(\sprintf('La destination « %s » n\'existe pas dans le catalogue.', $slug));
    }

    /**
     * @return list<Etape>
     */
    private function etapes(mixed $etapes, Destination $destination): array
    {
        if (!\is_array($etapes) || [] === $etapes) {
            throw new OutilException('Donne au moins une étape, avec ses logements compatibles.');
        }

        $liste = [];
        foreach (array_values($etapes) as $index => $etape) {
            $numero = $index + 1;
            $date = \is_array($etape) && \is_string($etape['dateDebut'] ?? null)
                ? \DateTimeImmutable::createFromFormat('!Y-m-d', $etape['dateDebut'])
                : false;
            if (false === $date || $date->format('Y-m-d') !== $etape['dateDebut']) {
                throw new OutilException(\sprintf('Étape %d : la date de début doit être au format AAAA-MM-JJ.', $numero));
            }
            $nuits = $etape['nombreDeNuits'] ?? null;
            if (!\is_int($nuits) || $nuits < 1 || $nuits > 60) {
                throw new OutilException(\sprintf('Étape %d : le nombre de nuits doit être compris entre 1 et 60.', $numero));
            }
            $logements = $this->prestations($etape['logementIds'] ?? [], TypePrestation::Logement, $destination);
            if ([] === $logements) {
                throw new OutilException(\sprintf('Étape %d : donne au moins un logement compatible (logementIds).', $numero));
            }
            $liste[] = new Etape($date, $nuits, $logements);
        }

        return $liste;
    }

    /**
     * Prestations du catalogue désignées par leurs identifiants : du bon type et dans la destination.
     *
     * @return list<Prestation>
     */
    private function prestations(mixed $ids, TypePrestation $type, Destination $destination): array
    {
        if (!\is_array($ids)) {
            throw new OutilException('Les identifiants de prestations doivent être une liste de nombres.');
        }

        $quoi = TypePrestation::Logement === $type ? 'Le logement' : 'L\'activité';
        $prestations = [];
        foreach (array_unique($ids) as $id) {
            $prestation = \is_int($id) ? $this->prestations->find($id) : null;
            if (null === $prestation || $type !== $prestation->getType() || $prestation->getDestination()?->getId() !== $destination->getId()) {
                throw new OutilException(\sprintf('%s %s n\'existe pas à %s : utilise les identifiants renvoyés par les outils de recherche.', $quoi, json_encode($id), $destination->getVille()));
            }
            $prestations[] = $prestation;
        }

        return $prestations;
    }

    /**
     * @return array<string, mixed>
     */
    private function sortie(ResultatChiffrage $resultat): array
    {
        return [
            'budgetClient' => null === $resultat->budgetCentimes ? null : [
                'montantEuros' => Montant::nombre($resultat->budgetCentimes),
                'parPersonne' => $resultat->budgetParPersonne,
            ],
            'budgetInsuffisant' => $resultat->budgetInsuffisant,
            'avertissement' => $this->avertissement(),
            'niveaux' => null === $resultat->niveaux ? null : array_map(self::niveau(...), $resultat->niveaux),
            'exclusions' => $resultat->exclusions,
            'nonChiffrable' => null === $resultat->nonChiffrable ? null : [
                'prestation' => $resultat->nonChiffrable->prestation,
                'date' => $resultat->nonChiffrable->date?->format('Y-m-d'),
                'raison' => $resultat->nonChiffrable->raison,
            ],
        ];
    }

    /**
     * Un arbitrage chiffré : ce qui change, les étapes à redonner à calculer_budget si le client accepte,
     * le total essentiel et s'il tient dans le budget.
     *
     * @return array<string, mixed>
     */
    private static function arbitrage(Arbitrage $arbitrage): array
    {
        return [
            'type' => $arbitrage->type,
            'libelle' => $arbitrage->libelle,
            'etapes' => array_map(static fn (Etape $e): array => ['dateDebut' => $e->dateDebut->format('Y-m-d'), 'nombreDeNuits' => $e->nombreDeNuits], $arbitrage->etapes),
            'totalEuros' => Montant::nombre($arbitrage->essentiel->totalCentimes),
            'parPersonneEuros' => Montant::nombre($arbitrage->essentiel->parPersonneCentimes),
            'tientDansLeBudget' => $arbitrage->tientDansLeBudget(),
            'ecartAuBudgetEuros' => Montant::nombre($arbitrage->ecartCentimes()),
            'hypotheses' => $arbitrage->essentiel->hypotheses,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function niveau(Niveau $niveau): array
    {
        return [
            'logements' => $niveau->logements,
            'activites' => $niveau->activites,
            'transport' => $niveau->transport->label(),
            'postes' => array_map(static fn (Poste $p): array => [
                'type' => $p->type,
                'libelle' => $p->libelle,
                'calcul' => $p->calcul,
                'montantEuros' => Montant::nombre($p->centimes),
                'estime' => $p->estime,
            ], $niveau->postes),
            'coutPrestationsEuros' => Montant::nombre($niveau->coutPrestationsCentimes),
            'margeEuros' => Montant::nombre($niveau->margeCentimes),
            'fraisDeDossierEuros' => Montant::nombre($niveau->fraisDeDossierCentimes),
            'totalEuros' => Montant::nombre($niveau->totalCentimes),
            'parPersonneEuros' => Montant::nombre($niveau->parPersonneCentimes),
            'hypotheses' => $niveau->hypotheses,
        ];
    }
}
