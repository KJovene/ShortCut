<?php

namespace App\DataFixtures;

use App\Entity\Demande;
use App\Entity\Utilisateur;
use App\Enum\StatutDemande;
use App\Enum\TypeDeVoyage;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Demandes de démo réparties entre Léa, Hugo et une demande non attribuée, dont certaines prioritaires,
 * pour vérifier la liste « Mes demandes » du backoffice.
 */
final class DemandeFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $client = $this->getReference(UtilisateurFixtures::CLIENT, Utilisateur::class);

        // [conseiller, lieu, type, statut, prioritaire, reçue il y a]
        $demandes = [
            [UtilisateurFixtures::LEA, 'Lisbonne', TypeDeVoyage::Sejour, StatutDemande::Nouvelle, false, '-1 hour'],
            [UtilisateurFixtures::LEA, 'Santorin', TypeDeVoyage::Noces, StatutDemande::Nouvelle, true, '-3 days'],
            [UtilisateurFixtures::LEA, 'Rome', TypeDeVoyage::Circuit, StatutDemande::PropositionEnvoyee, false, '-5 days'],
            [UtilisateurFixtures::LEA, 'Marrakech', TypeDeVoyage::Groupe, StatutDemande::AConfirmer, true, '-1 day'],
            [UtilisateurFixtures::HUGO, 'Islande', TypeDeVoyage::Circuit, StatutDemande::Nouvelle, true, '-2 hours'],
            [UtilisateurFixtures::HUGO, 'Bali', TypeDeVoyage::Sejour, StatutDemande::Confirmee, false, '-10 days'],
            [UtilisateurFixtures::HUGO, 'Tenerife', TypeDeVoyage::Entreprise, StatutDemande::Perdue, false, '-20 days'],
            [null, 'Costa Rica', TypeDeVoyage::Sejour, StatutDemande::Nouvelle, true, '-30 minutes'],
        ];

        foreach ($demandes as [$conseiller, $lieu, $type, $statut, $prioritaire, $recue]) {
            $date = new \DateTimeImmutable($recue);
            $manager->persist((new Demande())
                ->setClient($client)
                ->setConseiller(null === $conseiller ? null : $this->getReference($conseiller, Utilisateur::class))
                ->setLieuOuZone($lieu)
                ->setTypeDeVoyage($type)
                ->setStatut($statut)
                ->setPrioritaire($prioritaire)
                ->setAdultes(2)
                ->setNombreDeJours(7)
                ->setBudgetCentimes(300000)
                ->setCreeLe($date)
                ->setModifieLe($date));
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [UtilisateurFixtures::class];
    }
}
