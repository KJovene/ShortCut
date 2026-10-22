<?php

namespace App\DataFixtures;

use App\Entity\Conversation;
use App\Entity\Demande;
use App\Entity\Utilisateur;
use App\Enum\MotifTransmission;
use App\Enum\StatutDemande;
use App\Enum\TypeDeVoyage;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Demandes de démo réparties entre Léa, Hugo et une demande non attribuée, dont certaines prioritaires,
 * pour vérifier la liste « Mes demandes » du backoffice ; et entre trois clients, pour « Mon espace ».
 */
final class DemandeFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // Camille (client@shortcut.test) a 3 voyages en cours, le maximum : Santorin (deux versions après une
        // modification, PropositionFixtures), Marrakech (à confirmer) et Costa Rica (en discussion, transmise).
        // [client, conseiller, lieu, type, statut, prioritaire, reçue il y a]
        $demandes = [
            [UtilisateurFixtures::CLIENT_JULIEN, UtilisateurFixtures::LEA, 'Lisbonne', TypeDeVoyage::Sejour, StatutDemande::Nouvelle, false, '-1 hour'],
            [UtilisateurFixtures::CLIENT, UtilisateurFixtures::LEA, 'Santorin', TypeDeVoyage::Noces, StatutDemande::PropositionEnvoyee, false, '-3 days'],
            [UtilisateurFixtures::CLIENT_JULIEN, UtilisateurFixtures::LEA, 'Rome', TypeDeVoyage::Circuit, StatutDemande::PropositionEnvoyee, false, '-5 days'],
            [UtilisateurFixtures::CLIENT, UtilisateurFixtures::LEA, 'Marrakech', TypeDeVoyage::Groupe, StatutDemande::AConfirmer, true, '-1 day'],
            [UtilisateurFixtures::CLIENT_SARAH, UtilisateurFixtures::HUGO, 'Islande', TypeDeVoyage::Circuit, StatutDemande::Nouvelle, true, '-2 hours'],
            [UtilisateurFixtures::CLIENT_SARAH, UtilisateurFixtures::HUGO, 'Bali', TypeDeVoyage::Sejour, StatutDemande::Confirmee, false, '-10 days'],
            [UtilisateurFixtures::CLIENT_SARAH, UtilisateurFixtures::HUGO, 'Tenerife', TypeDeVoyage::Entreprise, StatutDemande::Perdue, false, '-20 days'],
            [UtilisateurFixtures::CLIENT, null, 'Costa Rica', TypeDeVoyage::Sejour, StatutDemande::Nouvelle, true, '-30 minutes'],
        ];

        foreach ($demandes as [$client, $conseiller, $lieu, $type, $statut, $prioritaire, $recue]) {
            $date = new \DateTimeImmutable($recue);
            $manager->persist($demande = (new Demande())
                ->setClient($this->getReference($client, Utilisateur::class))
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
        // La dernière, non attribuée : le client a demandé un conseiller au chatbot (US06).
        $this->transmettreAuConseiller($manager, $demande);

        $manager->flush();
    }

    private function transmettreAuConseiller(ObjectManager $manager, Demande $demande): void
    {
        $conversation = (new Conversation())
            ->ajouterMessage('client', 'Bonjour, nous aimerions découvrir le Costa Rica à deux, une semaine de nature.')
            ->ajouterMessage('assistant', 'Une semaine de nature au Costa Rica : les paresseux vous attendent, sans se presser. À quelle période pensez-vous partir ?')
            ->ajouterMessage('client', 'Plutôt en février. En fait, je préférerais en parler directement avec quelqu\'un.')
            ->ajouterMessage('assistant', 'Bien sûr. Un conseiller de l\'agence reprend votre demande et vous recontacte sous 2 jours ouvrés.')
            ->setRepriseParConseiller(true);
        $demande->setConversation($conversation);
        $demande->transmettre(
            MotifTransmission::DemandeDuClient,
            'Couple, une semaine de nature au Costa Rica en février, budget 3 000 €. Le client préfère échanger avec un conseiller ; dates et températures à préciser.',
        );
        $manager->persist($conversation);
    }

    public function getDependencies(): array
    {
        return [UtilisateurFixtures::class];
    }
}
