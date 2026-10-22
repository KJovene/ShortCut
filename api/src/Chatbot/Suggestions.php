<?php

namespace App\Chatbot;

use App\Entity\Prestation;
use App\Repository\PrestationRepository;

/**
 * Les logements et activités recommandés au client, en cartes cliquables sous le message de l'agent Conversation
 * (événement « suggestions ») : chaque carte ouvre la fiche du backoffice (photos, description, équipements, durée).
 * Elles sont faites des fiches renvoyées par consulter_catalogue, jamais d'un texte du modèle, et sans prix : le
 * chatbot n'en donne pas avant la proposition.
 */
final class Suggestions
{
    /** Au-delà, les cartes ne tiennent plus sous un message. */
    private const MAX = 8;

    /**
     * La carte des fiches renvoyées par consulter_catalogue pendant le tour, dans leur ordre ; null s'il n'y en a pas.
     *
     * @return array<string, mixed>|null
     */
    public static function depuis(Tour $tour, PrestationRepository $prestations): ?array
    {
        $ids = [];
        foreach ($tour->sorties as $sortie) {
            if ('consulter_catalogue' === $sortie['nom']) {
                array_push($ids, ...array_column($sortie['sortie']['prestations'] ?? [], 'id'));
            }
        }
        $ids = \array_slice(array_values(array_unique($ids)), 0, self::MAX);
        if ([] === $ids) {
            return null;
        }

        $parId = [];
        foreach ($prestations->findBy(['id' => $ids]) as $prestation) {
            $parId[$prestation->getId()] = $prestation;
        }
        $elements = array_map(self::element(...), array_values(array_filter(array_map(static fn (int $id): ?Prestation => $parId[$id] ?? null, $ids))));

        return ['type' => 'suggestions', 'elements' => $elements];
    }

    /**
     * @return array<string, mixed>
     */
    private static function element(Prestation $p): array
    {
        $destination = $p->getDestination();

        return [
            'id' => $p->getId(),
            'type' => $p->getType()?->value,
            'nom' => $p->getNom(),
            'categorie' => $p->getCategorie(),
            'description' => $p->getDescription(),
            'destination' => ['slug' => $destination?->getSlug(), 'ville' => $destination?->getVille(), 'pays' => $destination?->getPays()],
            'photos' => $p->getUrlsDesPhotos(),
            'capacite' => $p->getCapacite(),
            'chambres' => $p->getChambres(),
            'equipements' => $p->getEquipements(),
            'accessible' => $p->isAccessible(),
            'dureeMinutes' => $p->getDureeMinutes(),
            'ageMinimum' => $p->getAgeMinimum(),
        ];
    }
}
