<?php

namespace App\Chatbot\Outil;

use App\Chatbot\ContexteAgent;
use App\Chiffrage\Montant;
use App\Entity\Destination;
use App\Entity\Prestation;
use App\Enum\TypePrestation;
use App\Repository\DestinationRepository;
use App\Repository\PrestationRepository;
use App\Texte\Correspondance;

/**
 * Activités d'une destination (docs/outils.md, ticket #202) : accessibilité, durée et tarif au premier jour du
 * voyage, comme pour le chiffrage. Un enfant trop jeune n'écarte pas l'activité : elle est proposée aux seuls
 * voyageurs qui ont l'âge minimum (`participants`, `exclus`), comme le chiffrage la compte. Une capacité trop
 * petite ne l'écarte pas non plus : l'activité se fait en plusieurs sessions.
 */
final class ChercherActivites implements Outil
{
    public const NOM = 'chercher_activites';

    public function __construct(
        private readonly DestinationRepository $destinations,
        private readonly PrestationRepository $prestations,
    ) {
    }

    public function nom(): string
    {
        return self::NOM;
    }

    public function definition(): array
    {
        return [
            'name' => $this->nom(),
            'description' => 'Liste les activités compatibles d\'une destination du catalogue, avec les voyageurs qui ont l\'âge d\'y participer et le prix par personne aux dates. Une activité qui exclut un enfant ne se propose qu\'aux autres voyageurs, en le disant. Utilise l\'identifiant renvoyé (activiteId) pour calculer le budget. Les prix sont des prix d\'achat, hors marge : ne les annonce jamais au client, seuls les montants de calculer_budget le sont.',
            'strict' => false,
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'destination' => ['type' => 'string', 'description' => 'Slug renvoyé par chercher_destinations, en minuscules (ex. « santorin »).'],
                    'date' => ['type' => 'string', 'description' => 'Premier jour du voyage, AAAA-MM-JJ.'],
                    'adultes' => ['type' => 'integer'],
                    'agesEnfants' => ['type' => 'array', 'items' => ['type' => 'integer']],
                    'accessibleObligatoire' => ['type' => 'boolean'],
                    'envies' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Les activités qui y répondent le mieux sont listées en premier.'],
                    'dureeMaxMinutes' => ['type' => 'integer', 'description' => 'Durée maximale d\'une activité ; omettre si le client n\'en a pas donné.'],
                ],
                'required' => ['destination', 'date', 'adultes', 'agesEnfants', 'accessibleObligatoire'],
                'additionalProperties' => false,
            ],
        ];
    }

    public function executer(ContexteAgent $contexte, array $entree): array
    {
        $destination = $this->destination(Entree::chaine($entree, 'destination'));
        $jour = Entree::date($entree, 'date') ?? throw new OutilException('Indique date, le premier jour du voyage (AAAA-MM-JJ).');
        $adultes = Entree::entier($entree, 'adultes', 1, 1000) ?? throw new OutilException('Indique adultes, au moins 1.');
        $agesEnfants = Entree::entiers($entree, 'agesEnfants', 0, 17);
        $accessible = Entree::booleen($entree, 'accessibleObligatoire') ?? throw new OutilException('Indique accessibleObligatoire (true ou false).');
        $envies = Entree::chaines($entree, 'envies');
        $dureeMax = Entree::entier($entree, 'dureeMaxMinutes', 1);

        $activites = [];
        $ecartees = [];
        foreach ($this->prestations->duCatalogue([$destination], TypePrestation::Activite) as $activite) {
            $raison = self::raison($activite, $accessible, $dureeMax, $jour);
            if (null !== $raison) {
                $ecartees[] = ['nom' => $activite->getNom(), 'raison' => $raison];
                continue;
            }

            // Les adultes ont toujours l'âge minimum : seuls les enfants peuvent être exclus.
            $ageMinimum = $activite->getAgeMinimum() ?? 0;
            $tropJeunes = array_values(array_filter($agesEnfants, static fn (int $age): bool => $age < $ageMinimum));
            sort($tropJeunes);
            $participants = $adultes + \count($agesEnfants) - \count($tropJeunes);
            $correspondantes = Correspondance::communs($envies, $activite->motsCles());
            // Ce que toute la famille peut faire d'abord, puis les envies.
            $activites[] = [[[] !== $tropJeunes, -\count($correspondantes), $activite->getNom()], [
                'activiteId' => $activite->getId(),
                'nom' => $activite->getNom(),
                'prestataire' => $activite->getPrestataire()?->getNom(),
                'categorie' => $activite->getCategorie(),
                'description' => $activite->getDescription(),
                'dureeMinutes' => $activite->getDureeMinutes(),
                'ageMinimum' => $activite->getAgeMinimum(),
                'participants' => $participants,
                'exclus' => array_map(static fn (int $age): string => \sprintf('enfant de %d ans', $age), $tropJeunes),
                'capacite' => $activite->getCapacite(),
                'sessions' => (int) ceil($participants / max(1, $activite->getCapacite())),
                'accessible' => $activite->isAccessible(),
                'envies' => $activite->getEnvies(),
                'enviesCorrespondantes' => $correspondantes,
                'photos' => $activite->getPhotos(),
                'prixParPersonneEuros' => Montant::nombre((int) $activite->tarifLe($jour)?->getPrixCentimes()),
            ]];
        }

        usort($activites, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return ['activites' => array_column($activites, 1), 'ecartees' => $ecartees];
    }

    private function destination(?string $slug): Destination
    {
        if (null === $slug) {
            throw new OutilException('Indique la destination (slug renvoyé par chercher_destinations).');
        }

        return $this->destinations->publieeCorrespondant($slug)
            ?? throw new OutilException(\sprintf('La destination « %s » n\'existe pas dans le catalogue.', $slug));
    }

    /**
     * Pourquoi l'activité ne convient pas, ou null si elle convient.
     */
    private static function raison(Prestation $activite, bool $accessible, ?int $dureeMax, \DateTimeImmutable $jour): ?string
    {
        if ($accessible && !$activite->isAccessible()) {
            return 'Non accessible : un voyageur a besoin d\'une activité accessible.';
        }

        $duree = $activite->getDureeMinutes();
        if (null !== $dureeMax && null !== $duree && $duree > $dureeMax) {
            return \sprintf('Dure %s, plus que les %s souhaitées.', self::duree($duree), self::duree($dureeMax));
        }

        if (null === $activite->tarifLe($jour)) {
            return \sprintf('Aucun tarif saisi au %s.', $jour->format('d/m/Y'));
        }

        return null;
    }

    /** « 3 h », « 2 h 30 », « 45 min ». */
    private static function duree(int $minutes): string
    {
        $heures = intdiv($minutes, 60);
        $reste = $minutes % 60;

        return match (true) {
            0 === $heures => $reste.' min',
            0 === $reste => $heures.' h',
            default => \sprintf('%d h %02d', $heures, $reste),
        };
    }
}
