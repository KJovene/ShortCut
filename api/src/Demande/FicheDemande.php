<?php

namespace App\Demande;

use App\Entity\Demande;

/**
 * La fiche demande : les exigences du client telles que le chatbot les recueille (EnregistrerExigences), que les
 * agents les lisent, que l'espace client les affiche et que chaque proposition les garde au moment du chiffrage.
 */
final class FicheDemande
{
    /**
     * Les exigences du client, dans la forme qu'en lisent les agents, l'espace client et la comparaison des
     * versions (Proposition::$exigences).
     *
     * @return array<string, mixed>
     */
    public static function depuis(Demande $demande): array
    {
        $budget = $demande->getBudgetCentimes();

        return [
            'typeDeVoyage' => $demande->getTypeDeVoyage()?->value,
            'lieuOuZone' => $demande->getLieuOuZone(),
            'villeDepart' => $demande->getVilleDepart(),
            'dateDebut' => $demande->getDateDebut()?->format('Y-m-d'),
            'dateFin' => $demande->getDateFin()?->format('Y-m-d'),
            'datesFlexibles' => $demande->isDatesFlexibles(),
            'nombreDeJours' => $demande->getNombreDeJours(),
            'adultes' => $demande->getAdultes(),
            'enfants' => $demande->getEnfants(),
            'agesEnfants' => $demande->getAgesEnfants(),
            'budgetEuros' => null === $budget ? null : intdiv($budget, 100),
            'budgetParPersonne' => $demande->isBudgetParPersonne(),
            'transportInclus' => $demande->isTransportInclus(),
            'envies' => $demande->getEnvies(),
            'contraintes' => $demande->getContraintes(),
        ];
    }

    /**
     * Exigences encore inconnues, parmi celles que la fiche sait distinguer d'une valeur par défaut.
     *
     * @return list<string>
     */
    public static function manquantes(Demande $demande): array
    {
        $manquantes = [
            'type de voyage' => null === $demande->getTypeDeVoyage(),
            'lieu ou zone' => null === $demande->getLieuOuZone(),
            'dates' => null === $demande->getDateDebut(),
            'nombre de jours' => null === $demande->getNombreDeJours(),
            'ville de départ' => null === $demande->getVilleDepart(),
            'budget' => null === $demande->getBudgetCentimes(),
            'envies' => [] === $demande->getEnvies(),
        ];

        return array_keys(array_filter($manquantes));
    }
}
