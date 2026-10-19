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
 * Activités d'une destination ouvertes à tout le groupe (docs/outils.md, ticket #202) : âge minimum, accessibilité,
 * durée et tarif au premier jour du voyage, comme pour le chiffrage. Une capacité trop petite ne l'écarte pas :
 * l'activité se fait en plusieurs sessions.
 */
final class ChercherActivites implements Outil
{
    public function __construct(
        private readonly DestinationRepository $destinations,
        private readonly PrestationRepository $prestations,
    ) {
    }

    public function nom(): string
    {
        return 'chercher_activites';
    }

    public function definition(): array
    {
        return [
            'name' => $this->nom(),
            'description' => 'Liste les activités compatibles d\'une destination du catalogue, ouvertes à tous les voyageurs selon leur âge, avec le prix par personne aux dates. Utilise l\'identifiant renvoyé (activiteId) pour calculer le budget. Les prix sont des prix d\'achat, hors marge : ne les annonce jamais au client, seuls les montants de calculer_budget le sont.',
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

        $participants = $adultes + \count($agesEnfants);
        $activites = [];
        $ecartees = [];
        foreach ($this->prestations->duCatalogue([$destination], TypePrestation::Activite) as $activite) {
            $raison = self::raison($activite, $agesEnfants, $accessible, $dureeMax, $jour);
            if (null !== $raison) {
                $ecartees[] = ['nom' => $activite->getNom(), 'raison' => $raison];
                continue;
            }

            $correspondantes = Correspondance::communs($envies, $activite->getEnvies());
            $activites[] = [[-\count($correspondantes), $activite->getNom()], [
                'activiteId' => $activite->getId(),
                'nom' => $activite->getNom(),
                'prestataire' => $activite->getPrestataire()?->getNom(),
                'categorie' => $activite->getCategorie(),
                'description' => $activite->getDescription(),
                'dureeMinutes' => $activite->getDureeMinutes(),
                'ageMinimum' => $activite->getAgeMinimum(),
                'participants' => $participants,
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
     *
     * @param list<int> $agesEnfants
     */
    private static function raison(Prestation $activite, array $agesEnfants, bool $accessible, ?int $dureeMax, \DateTimeImmutable $jour): ?string
    {
        if ($accessible && !$activite->isAccessible()) {
            return 'Non accessible : un voyageur a besoin d\'une activité accessible.';
        }

        $ageMinimum = $activite->getAgeMinimum() ?? 0;
        $tropJeunes = array_values(array_filter($agesEnfants, static fn (int $age): bool => $age < $ageMinimum));
        sort($tropJeunes);
        if ([] !== $tropJeunes) {
            return \sprintf(
                'Âge minimum %d ans : %s de %s ans ne %s pas participer.',
                $ageMinimum,
                1 === \count($tropJeunes) ? 'l\'enfant' : 'les enfants',
                self::enumerer($tropJeunes),
                1 === \count($tropJeunes) ? 'peut' : 'peuvent',
            );
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

    /**
     * « 9 », « 6 et 9 », « 4, 6 et 9 ».
     *
     * @param list<int> $nombres
     */
    private static function enumerer(array $nombres): string
    {
        $dernier = array_pop($nombres);

        return [] === $nombres ? (string) $dernier : implode(', ', $nombres).' et '.$dernier;
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
