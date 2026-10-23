<?php

namespace App\Chatbot;

use App\Chiffrage\Montant;
use App\Demande\FicheDemande;
use App\Enum\Langue;
use App\Enum\TypeDeVoyage;

/**
 * Ce que le client a changé dans sa demande depuis une version de proposition (#211), champ par champ de la fiche
 * (FicheDemande::depuis).
 *
 * Dates, durée, voyageurs, budget, ville de départ et contraintes se recalculent sur la même
 * combinaison : retenir_combinaison revérifie chaque prestation (tarifs, capacité, âges, accessibilité, climat). Un
 * autre type de voyage, une autre zone ou d'autres envies demandent une nouvelle recherche.
 */
final readonly class Changement
{
    /** Champs dont le changement remet en cause la destination ou les activités. */
    private const RECHERCHE = ['typeDeVoyage', 'lieuOuZone', 'envies'];

    /** Groupes de champs, dans l'ordre où le changement est présenté au client. */
    private const GROUPES = [
        'type de voyage' => ['typeDeVoyage'],
        'destination' => ['lieuOuZone'],
        'dates' => ['dateDebut', 'dateFin', 'nombreDeJours', 'datesFlexibles'],
        'voyageurs' => ['adultes', 'enfants', 'agesEnfants'],
        'ville de départ' => ['villeDepart'],
        'budget' => ['budgetEuros', 'budgetParPersonne', 'transportInclus'],
        'envies' => ['envies'],
        'contraintes' => ['contraintes'],
    ];

    /** Les mêmes groupes, pour un client qui écrit en anglais. */
    private const GROUPES_EN = [
        'type de voyage' => 'trip type',
        'destination' => 'destination',
        'dates' => 'dates',
        'voyageurs' => 'travellers',
        'ville de départ' => 'departure city',
        'budget' => 'budget',
        'envies' => 'wishes',
        'contraintes' => 'constraints',
    ];

    /**
     * @param array<string, array{avant: mixed, apres: mixed}> $champs champs modifiés
     * @param array<string, mixed>                             $apres  fiche actuelle
     */
    private function __construct(
        public array $champs,
        private array $apres,
    ) {
    }

    /**
     * @param array<string, mixed> $avant fiche enregistrée avec la version
     * @param array<string, mixed> $apres fiche actuelle
     */
    public static function entre(array $avant, array $apres): self
    {
        $champs = [];
        foreach ($apres as $champ => $valeur) {
            if (!\array_key_exists($champ, $avant) || self::normaliser($avant[$champ]) !== self::normaliser($valeur)) {
                $champs[$champ] = ['avant' => $avant[$champ] ?? null, 'apres' => $valeur];
            }
        }

        return new self($champs, $apres);
    }

    public function vide(): bool
    {
        return [] === $this->champs;
    }

    /** Faut-il chercher à nouveau destination, logements ou activités ? */
    public function rechercheNecessaire(): bool
    {
        return [] !== array_intersect(array_keys($this->champs), self::RECHERCHE);
    }

    /**
     * Le changement en clair, pour le client : « dates : du 21/05/2027 au 24/05/2027 », « voyageurs : 3 adultes ».
     *
     * @return list<string>
     */
    public function resume(Langue $langue = Langue::Francais): array
    {
        $lignes = [];
        foreach (self::GROUPES as $groupe => $champs) {
            if ([] !== array_intersect($champs, array_keys($this->champs))) {
                $lignes[] = Langue::Anglais === $langue
                    ? self::GROUPES_EN[$groupe].': '.$this->valeurEnAnglais($groupe)
                    : $groupe.' : '.$this->valeur($groupe);
            }
        }

        return $lignes;
    }

    /** Pour un client qui écrit en anglais (US15) ; le lieu, les envies et les contraintes restent ceux de la fiche. */
    private function valeurEnAnglais(string $groupe): string
    {
        $f = $this->apres;
        $nombre = static fn (int $n, string $mot): string => \sprintf('%d %s%s', $n, $mot, 1 === $n ? '' : 's');

        return match ($groupe) {
            'type de voyage' => TypeDeVoyage::tryFrom((string) $f['typeDeVoyage'])?->labelEnAnglais() ?? 'to be specified',
            'dates' => null === $f['dateDebut'] ? 'to be specified' : \sprintf(
                '%s%s%s',
                null === $f['dateFin'] ? 'from '.self::date($f['dateDebut']) : \sprintf('from %s to %s', self::date($f['dateDebut']), self::date($f['dateFin'])),
                null === $f['nombreDeJours'] ? '' : ', '.$nombre((int) $f['nombreDeJours'], 'night'),
                $f['datesFlexibles'] ? ', flexible dates' : '',
            ),
            'voyageurs' => $nombre((int) $f['adultes'], 'adult').match ((int) $f['enfants']) {
                0 => '',
                1 => ', 1 child',
                default => \sprintf(', %d children', $f['enfants']),
            },
            'ville de départ' => (string) ($f['villeDepart'] ?? 'to be specified'),
            'budget' => null === $f['budgetEuros'] ? 'no set budget' : \sprintf(
                '%s%s, transport %s',
                Montant::euros((int) $f['budgetEuros'] * 100),
                $f['budgetParPersonne'] ? ' per person' : ' in total',
                $f['transportInclus'] ? 'included' : 'not included',
            ),
            'envies', 'contraintes' => [] === $f[$groupe] ? 'none' : implode(', ', (array) $f[$groupe]),
            default => $this->valeur($groupe),
        };
    }

    private function valeur(string $groupe): string
    {
        $f = $this->apres;

        return match ($groupe) {
            'type de voyage' => mb_strtolower(TypeDeVoyage::tryFrom((string) $f['typeDeVoyage'])?->label() ?? 'à préciser'),
            'destination' => (string) $f['lieuOuZone'],
            'dates' => null === $f['dateDebut'] ? 'à préciser' : \sprintf(
                '%s%s%s',
                null === $f['dateFin'] ? 'à partir du '.self::date($f['dateDebut']) : \sprintf('du %s au %s', self::date($f['dateDebut']), self::date($f['dateFin'])),
                null === $f['nombreDeJours'] ? '' : ', '.Montant::quantite((int) $f['nombreDeJours'], 'nuit'),
                $f['datesFlexibles'] ? ', dates flexibles' : '',
            ),
            'voyageurs' => Montant::quantite((int) $f['adultes'], 'adulte').(0 === (int) $f['enfants'] ? '' : ', '.Montant::quantite((int) $f['enfants'], 'enfant')),
            'ville de départ' => (string) ($f['villeDepart'] ?? 'à préciser'),
            'budget' => null === $f['budgetEuros'] ? 'sans budget fixé' : \sprintf(
                '%s%s, transport %s',
                Montant::euros((int) $f['budgetEuros'] * 100),
                $f['budgetParPersonne'] ? ' par personne' : ' au total',
                $f['transportInclus'] ? 'inclus' : 'non inclus',
            ),
            'envies', 'contraintes' => [] === $f[$groupe] ? 'aucune' : implode(', ', (array) $f[$groupe]),
            default => '',
        };
    }

    private static function date(mixed $iso): string
    {
        return (new \DateTimeImmutable((string) $iso))->format('d/m/Y');
    }

    /** Listes comparées sans tenir compte de l'ordre ; le reste à l'identique. */
    private static function normaliser(mixed $valeur): mixed
    {
        if (\is_array($valeur) && array_is_list($valeur)) {
            sort($valeur);
        }

        return $valeur;
    }
}
