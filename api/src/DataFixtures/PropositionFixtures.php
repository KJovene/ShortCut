<?php

namespace App\DataFixtures;

use App\Entity\Conversation;
use App\Entity\Demande;
use App\Entity\Destination;
use App\Entity\LigneDePrestation;
use App\Entity\Prestataire;
use App\Entity\Prestation;
use App\Entity\Proposition;
use App\Enum\NiveauBudget;
use App\Enum\StatutProposition;
use App\Enum\TypeLigne;
use App\Enum\TypeProposition;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Une première proposition envoyée au client, pour essayer l'écran de confirmation (#209) : la demande de groupe à
 * Marrakech (Léa, « à confirmer »), douze amis du 21 au 25 octobre 2027 au niveau équilibré. Montants calculés
 * avec docs/chiffrage.md : riad 4 × 780 €, vols 12 × 265 €, transferts 12 × 27,50 €, médina 12 × 36 €,
 * cuisine 12 × 54 € ; coût 7 710 €, marge 1 542 €, frais 200 €, total 9 452 €.
 */
final class PropositionFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $demande = $manager->getRepository(Demande::class)->findOneBy(['lieuOuZone' => 'Marrakech']) ?? throw new \LogicException('Demande Marrakech introuvable.');
        $demande->setAdultes(12)->setNombreDeJours(5)->setVilleDepart('Paris')
            ->setDateDebut(new \DateTimeImmutable('2027-10-21'))->setDateFin(new \DateTimeImmutable('2027-10-25'))
            ->setBudgetCentimes(80000)->setBudgetParPersonne(true)->setTransportInclus(true)
            ->setEnvies(['culture', 'gastronomie', 'détente'])->setContraintes([]);

        // La conversation qui a mené à la proposition, affichée à côté des prix à confirmer.
        $conversation = (new Conversation())->setDebutLe(new \DateTimeImmutable('-1 day'));
        foreach ([
            ['client', 'Bonjour, nous sommes 12 amis et cherchons un riad à Marrakech du 21 au 25 octobre 2027, départ de Paris.'],
            ['assistant', 'Avec plaisir ! Quel budget envisagez-vous, et quelles envies pour ce séjour ?'],
            ['client', 'Environ 800 € par personne, vols compris. On aime la culture et la cuisine marocaine.'],
            ['assistant', 'Je vous propose le Riad Dar Yasmine privatisé, une visite guidée de la médina et un cours de cuisine : 9 452 € pour le groupe, soit 787,67 € par personne. Première proposition, prix estimés, à confirmer par votre conseiller.'],
        ] as [$role, $contenu]) {
            $conversation->ajouterMessage($role, $contenu);
        }
        $demande->setConversation($conversation);
        $manager->persist($conversation);

        $prestation = static fn (string $nom): Prestation => $manager->getRepository(Prestation::class)->findOneBy(['nom' => $nom]) ?? throw new \LogicException($nom);
        $prestataire = static fn (string $nom): Prestataire => $manager->getRepository(Prestataire::class)->findOneBy(['nom' => $nom]) ?? throw new \LogicException($nom);
        $riad = $prestation('Riad Dar Yasmine, riad entier');
        $medina = $prestation('Visite guidée de la médina et des souks');
        $cuisine = $prestation('Cours de cuisine marocaine et tajine');
        $hammam = $prestation('Hammam traditionnel et massage');

        // Format de l'agent Rédaction (#207) : destination, argumentaire, points forts, programme daté.
        $proposition = (new Proposition())
            ->setVersion(1)
            ->setType(TypeProposition::Premiere)
            ->setStatut(StatutProposition::Envoyee)
            ->setNiveauBudget(NiveauBudget::Equilibre)
            ->setDestination($manager->getRepository(Destination::class)->findOneBy(['slug' => 'marrakech']))
            ->setPourquoi('Un riad privatisé pour vivre ensemble à douze au cœur de la médina, avec la culture et la cuisine marocaine que le groupe recherche, à 3 h 30 de Paris.')
            ->setArgumentaire([
                ['exigence' => '12 amis ensemble', 'reponse' => 'Le Riad Dar Yasmine, privatisé, loge tout le groupe autour de son patio.'],
                ['exigence' => 'Culture', 'reponse' => 'Visite guidée de la médina et des souks.'],
                ['exigence' => 'Cuisine marocaine', 'reponse' => 'Cours de cuisine et tajine.'],
                ['exigence' => 'Environ 800 € par personne', 'reponse' => '787,67 € par personne au niveau équilibré, vols compris.'],
            ])
            ->setPrestations([
                ['id' => (int) $riad->getId(), 'type' => 'logement', 'nom' => (string) $riad->getNom(), 'pointsForts' => ['Privatisé pour le groupe', 'Piscine sur le patio'], 'niveaux' => ['essentiel', 'equilibre', 'confort']],
                ['id' => (int) $medina->getId(), 'type' => 'activite', 'nom' => (string) $medina->getNom(), 'pointsForts' => ['Guide francophone'], 'niveaux' => ['equilibre', 'confort']],
                ['id' => (int) $cuisine->getId(), 'type' => 'activite', 'nom' => (string) $cuisine->getNom(), 'pointsForts' => ['Tajine préparé ensemble'], 'niveaux' => ['equilibre', 'confort']],
                ['id' => (int) $hammam->getId(), 'type' => 'activite', 'nom' => (string) $hammam->getNom(), 'pointsForts' => ['Moment de détente'], 'niveaux' => ['confort']],
            ])
            ->setFraisDeDossierCentimes(20000)
            ->setTotalCentimes(945200)
            ->setTotalParPersonneCentimes(78767)
            ->setBudgetsParNiveau([
                NiveauBudget::Essentiel->value => ['totalCentimes' => 632000, 'parPersonneCentimes' => 52667],
                NiveauBudget::Equilibre->value => ['totalCentimes' => 945200, 'parPersonneCentimes' => 78767],
                NiveauBudget::Confort->value => ['totalCentimes' => 1249760, 'parPersonneCentimes' => 104147],
            ])
            ->setHypotheses([
                'Riad Dar Yasmine, riad entier : Riad privatisé, 6 chambres, 4 nuits du 21/10/2027 au 25/10/2027, tarif d\'octobre à décembre 2027',
                'Transport inclus : vols et transferts estimés au milieu de la fourchette, pour 12 voyageurs',
                'Frais de dossier groupe : 200 €',
            ])
            ->setProgramme([
                ['jour' => 1, 'date' => '2027-10-21', 'titre' => 'Arrivée à Marrakech', 'description' => 'Vol depuis Paris, transfert et installation au Riad Dar Yasmine, dîner sur la terrasse.', 'activiteIds' => []],
                ['jour' => 2, 'date' => '2027-10-22', 'titre' => 'Médina et souks', 'description' => 'Visite guidée de la médina et des souks, puis place Jemaa el-Fna au coucher du soleil.', 'activiteIds' => [(int) $medina->getId()]],
                ['jour' => 3, 'date' => '2027-10-23', 'titre' => 'Cuisine marocaine', 'description' => 'Cours de cuisine et tajine le matin, après-midi libre au bord de la piscine du riad.', 'activiteIds' => [(int) $cuisine->getId()]],
                ['jour' => 4, 'date' => '2027-10-24', 'titre' => 'Journée libre', 'description' => 'Jardin Majorelle, hammam ou shopping : chacun à son rythme.', 'activiteIds' => []],
                ['jour' => 5, 'date' => '2027-10-25', 'titre' => 'Retour', 'description' => 'Petit-déjeuner au riad, transfert à l\'aéroport et vol retour vers Paris.', 'activiteIds' => []],
            ])
            ->setCreeLe(new \DateTimeImmutable('-1 day'));
        $demande->addProposition($proposition);
        $manager->persist($proposition);

        // [type, libellé, prestation, prestataire, début, fin, quantité, calcul, prix estimé]
        $lignes = [
            [TypeLigne::Logement, $riad->getNom(), $riad, $riad->getPrestataire(), '2027-10-21', '2027-10-25', 1, '4 nuits × 780 €', 312000],
            [TypeLigne::Transport, 'Vol aller-retour Paris – Marrakech', null, $prestataire('Air Horizon'), '2027-10-21', '2027-10-25', 12, '12 voyageurs × 265 €', 318000],
            [TypeLigne::Transport, 'Transferts aéroport – hébergement à Marrakech', null, $prestataire('Autocars Méridien'), '2027-10-21', '2027-10-25', 12, '12 voyageurs × 27,50 €', 33000],
            [TypeLigne::Activite, $medina->getNom(), $medina, $medina->getPrestataire(), '2027-10-22', '2027-10-22', 12, '12 participants × 36 €', 43200],
            [TypeLigne::Activite, $cuisine->getNom(), $cuisine, $cuisine->getPrestataire(), '2027-10-23', '2027-10-23', 12, '12 participants × 54 €', 64800],
        ];
        foreach ($lignes as [$type, $libelle, $source, $fournisseur, $debut, $fin, $quantite, $calcul, $prix]) {
            $ligne = (new LigneDePrestation())
                ->setType($type)
                ->setLibelle((string) $libelle)
                ->setPrestation($source)
                ->setPrestataire($fournisseur)
                ->setDateDebut(new \DateTimeImmutable($debut))
                ->setDateFin(new \DateTimeImmutable($fin))
                ->setQuantite($quantite)
                ->setCalcul($calcul)
                ->setPrixEstimeCentimes($prix);
            $proposition->addLigne($ligne);
            $manager->persist($ligne);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [DemandeFixtures::class, CatalogueFixtures::class];
    }
}
