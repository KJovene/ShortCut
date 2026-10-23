<?php

namespace App\DataFixtures;

use App\Chatbot\Outil\CalculerBudget;
use App\Chiffrage\Periode;
use App\Demande\FicheDemande;
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
 * Propositions de démo, pour le client Camille (« Mon espace ») :
 * - Marrakech (Léa, « à confirmer ») : une première proposition, pour essayer l'écran de confirmation (#209). Douze
 *   amis du 21 au 25 octobre 2027 au niveau équilibré ; montants de docs/chiffrage.md : riad 4 × 780 €, vols
 *   12 × 265 €, transferts 12 × 27,50 €, médina 12 × 36 €, cuisine 12 × 54 € ; coût 7 710 €, marge 1 542 €,
 *   frais 200 €, total 9 452 € ;
 * - Santorin (Léa) : deux versions envoyées après une modification du client, à comparer (#211, #212).
 */
final class PropositionFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $this->marrakech($manager);
        $this->santorin($manager);
        $manager->flush();
    }

    private function marrakech(ObjectManager $manager): void
    {
        $demande = $manager->getRepository(Demande::class)->findOneBy(['lieuOuZone' => 'Marrakech']) ?? throw new \LogicException('Demande Marrakech introuvable.');
        $demande->setAdultes(12)->setNombreDeJours(5)->setVilleDepart('Paris')
            ->setDateDebut(new \DateTimeImmutable('2027-10-21'))->setDateFin(new \DateTimeImmutable('2027-10-25'))
            ->setBudgetCentimes(80000)->setBudgetParPersonne(true)->setTransportInclus(true)
            ->setEnvies(['culture', 'gastronomie', 'détente'])->setContraintes([]);

        // La conversation qui a mené à la proposition, affichée à côté des prix à confirmer.
        $debut = new \DateTimeImmutable('-1 day');
        $conversation = (new Conversation())->setDebutLe($debut);
        foreach ([
            ['client', 'Bonjour, nous sommes 12 amis et cherchons un riad à Marrakech du 21 au 25 octobre 2027, départ de Paris.'],
            ['assistant', 'Douze amis dans un riad rien que pour vous : la médina n\'a qu\'à bien se tenir. Quel budget envisagez-vous, par personne ou pour le groupe, et quelles envies pour ce séjour ?'],
            ['client', 'Environ 800 € par personne, vols compris. On aime la culture et la cuisine marocaine.'],
            ['assistant', 'Je vous propose le Riad Dar Yasmine privatisé, une visite guidée de la médina et un cours de cuisine : 9 452 € pour le groupe, soit 787,67 € par personne, sous vos 800 €. '.\sprintf(CalculerBudget::AVERTISSEMENT, Periode::jour($debut), '10 h 20')],
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
        $choix = [
            'destination' => 'marrakech',
            'etapes' => [['dateDebut' => '2027-10-21', 'nombreDeNuits' => 4, 'logementIds' => [(int) $riad->getId()]]],
            'activitesDemandees' => [],
            'activitesSuggerees' => [(int) $medina->getId(), (int) $cuisine->getId(), (int) $hammam->getId()],
            'pourquoi' => 'Un riad privatisé pour le groupe, la médina et la cuisine marocaine.',
        ];

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
            ->setChoix($choix)
            ->setExigences(FicheDemande::depuis($demande))
            ->setCreeLe(new \DateTimeImmutable('-1 day'))
            ->setEnvoyeeLe(new \DateTimeImmutable('-1 day'));
        $demande->addProposition($proposition);
        $manager->persist($proposition);

        // [type, libellé, prestation, prestataire, début, fin, quantité, calcul, prix estimé]
        $this->lignes($manager, $proposition, [
            [TypeLigne::Logement, $riad->getNom(), $riad, $riad->getPrestataire(), '2027-10-21', '2027-10-25', 1, '4 nuits × 780 €', 312000],
            [TypeLigne::Transport, 'Vol aller-retour Paris – Marrakech', null, $prestataire('Air Horizon'), '2027-10-21', '2027-10-25', 12, '12 voyageurs × 265 €', 318000],
            [TypeLigne::Transport, 'Transferts aéroport – hébergement à Marrakech', null, $prestataire('Autocars Méridien'), '2027-10-21', '2027-10-25', 12, '12 voyageurs × 27,50 €', 33000],
            [TypeLigne::Activite, $medina->getNom(), $medina, $medina->getPrestataire(), '2027-10-22', '2027-10-22', 12, '12 participants × 36 €', 43200],
            [TypeLigne::Activite, $cuisine->getNom(), $cuisine, $cuisine->getPrestataire(), '2027-10-23', '2027-10-23', 12, '12 participants × 54 €', 64800],
        ]);
    }

    /**
     * Voyage de noces à Santorin (exemple 3 de docs/chiffrage.md), modifié par le client dans le chat (#211) :
     * version 1 du 12 au 19 juin 2027 (Fira Blue, croisière ; 2 688 € au niveau équilibré), puis version 2 avec une
     * nuit de plus et une dégustation de vins (3 012 €). Les deux sont envoyées : le client les compare (#212).
     * Équilibré v2 : 8 nuits × 150 € + vols 2 × 370 € + transferts 2 × 42,50 € + croisière 2 × 120 € + dégustation
     * 2 × 60 € = 2 385 €, marge 477 €, frais 150 €.
     */
    private function santorin(ObjectManager $manager): void
    {
        $demande = $manager->getRepository(Demande::class)->findOneBy(['lieuOuZone' => 'Santorin']) ?? throw new \LogicException('Demande Santorin introuvable.');
        $prestation = static fn (string $nom): Prestation => $manager->getRepository(Prestation::class)->findOneBy(['nom' => $nom]) ?? throw new \LogicException($nom);
        $prestataire = static fn (string $nom): Prestataire => $manager->getRepository(Prestataire::class)->findOneBy(['nom' => $nom]) ?? throw new \LogicException($nom);
        $fira = $prestation('Hôtel Fira Blue, chambre triple');
        $suite = $prestation('Suite Caldera Oia, piscine privée');
        $croisiere = $prestation('Croisière en catamaran dans la caldeira');
        $vins = $prestation('Dégustation de vins volcaniques');
        $ids = static fn (Prestation ...$p): array => array_map(static fn (Prestation $x): int => (int) $x->getId(), $p);

        $demande->setAdultes(2)->setVilleDepart('Paris')->setDateDebut(new \DateTimeImmutable('2027-06-12'))
            ->setDateFin(new \DateTimeImmutable('2027-06-19'))->setNombreDeJours(7)->setBudgetCentimes(null)
            ->setTransportInclus(true)->setEnvies(['romantique', 'plage'])->setContraintes([]);

        $debut = new \DateTimeImmutable('-3 days');
        $conversation = (new Conversation())->setDebutLe($debut);
        foreach ([
            ['client', 'Bonjour, nous préparons notre voyage de noces à Santorin, du 12 au 19 juin 2027, au départ de Paris. Nous voulons faire une croisière.'],
            ['assistant', 'Toutes nos félicitations. Je vous propose l\'Hôtel Fira Blue et une croisière en catamaran dans la caldeira : 2 688 € pour vous deux au niveau équilibré. '.\sprintf(CalculerBudget::AVERTISSEMENT, Periode::jour($debut), '18 h 05')],
            ['client', 'Pouvez-vous ajouter une nuit, jusqu\'au 20 juin, et une dégustation de vins ?'],
            ['assistant', 'Voici votre proposition mise à jour (dates : du 12/06/2027 au 20/06/2027, 8 nuits ; envies : romantique, plage, gastronomie). Équilibré : Hôtel Fira Blue, croisière et dégustation, 3 012 €.'."\n".\sprintf(CalculerBudget::AVERTISSEMENT, Periode::jour($debut->modify('+1 day')), '9 h 40')],
            ['assistant', 'Votre proposition détaillée est à jour : la version 2 est sur votre page « Ma proposition », la version 1 reste consultable.'],
        ] as [$role, $contenu]) {
            $conversation->ajouterMessage($role, $contenu);
        }
        $demande->setConversation($conversation);
        $manager->persist($conversation);

        $commun = [
            'destination' => $manager->getRepository(Destination::class)->findOneBy(['slug' => 'santorin']),
            'pourquoi' => 'Des couchers de soleil sur la caldeira, une croisière en catamaran et le calme d\'un hôtel à Fira : un voyage de noces à votre rythme.',
        ];
        $jour = static fn (int $n, string $date, string $titre, string $description, array $activites = []): array => ['jour' => $n, 'date' => $date, 'titre' => $titre, 'description' => $description, 'activiteIds' => $activites];
        $debut = [
            $jour(1, '2027-06-12', 'Arrivée à Santorin', 'Vol depuis Paris, transfert à Fira et installation à l\'Hôtel Fira Blue.'),
            $jour(2, '2027-06-13', 'Fira et le chemin des crêtes', 'Balade dans les ruelles blanches, puis coucher de soleil sur la caldeira.'),
            $jour(3, '2027-06-14', 'Croisière dans la caldeira', 'Croisière en catamaran : volcan, sources chaudes et baignade.', $ids($croisiere)),
            $jour(4, '2027-06-15', 'Plage de Kamari', 'Journée de détente sur le sable noir.'),
        ];

        // Version 1 : 7 nuits, remplacée par la version 2 mais toujours consultable.
        $v1 = (new Proposition())
            ->setVersion(1)->setStatut(StatutProposition::Remplacee)->setNiveauBudget(NiveauBudget::Equilibre)
            ->setDestination($commun['destination'])->setPourquoi($commun['pourquoi'])
            ->setFraisDeDossierCentimes(15000)->setTotalCentimes(268800)->setTotalParPersonneCentimes(134400)
            ->setBudgetsParNiveau([
                NiveauBudget::Essentiel->value => ['totalCentimes' => 228600, 'parPersonneCentimes' => 114300],
                NiveauBudget::Equilibre->value => ['totalCentimes' => 268800, 'parPersonneCentimes' => 134400],
                NiveauBudget::Confort->value => ['totalCentimes' => 550200, 'parPersonneCentimes' => 275100],
            ])
            ->setHypotheses([
                'Hôtel Fira Blue, chambre triple : Hôtel 3 étoiles, 1 chambre, 7 nuits du 12/06/2027 au 19/06/2027, tarif d\'avril à juin 2027',
                'Transport inclus : vols et transferts estimés au milieu de la fourchette, pour 2 voyageurs',
                'Frais de dossier voyage de noces : 150 €',
            ])
            ->setArgumentaire([
                ['exigence' => 'Un voyage de noces romantique', 'reponse' => 'Couchers de soleil sur la caldeira, depuis Fira.'],
                ['exigence' => 'Une croisière', 'reponse' => 'Croisière en catamaran dans la caldeira le troisième jour.'],
            ])
            ->setPrestations([
                ['id' => (int) $fira->getId(), 'type' => 'logement', 'nom' => (string) $fira->getNom(), 'pointsForts' => ['Piscine', 'Au cœur de Fira'], 'niveaux' => ['essentiel', 'equilibre']],
                ['id' => (int) $suite->getId(), 'type' => 'logement', 'nom' => (string) $suite->getNom(), 'pointsForts' => ['Piscine privée', 'Vue caldeira'], 'niveaux' => ['confort']],
                ['id' => (int) $croisiere->getId(), 'type' => 'activite', 'nom' => (string) $croisiere->getNom(), 'pointsForts' => ['Volcan et sources chaudes'], 'niveaux' => ['essentiel', 'equilibre', 'confort']],
                ['id' => (int) $vins->getId(), 'type' => 'activite', 'nom' => (string) $vins->getNom(), 'pointsForts' => ['Vins du volcan'], 'niveaux' => ['confort']],
            ])
            ->setProgramme([
                ...$debut,
                $jour(5, '2027-06-16', 'Village d\'Oia', 'Journée à Oia, ses moulins et son coucher de soleil célèbre.'),
                $jour(6, '2027-06-17', 'Akrotiri', 'Site archéologique d\'Akrotiri, puis la plage rouge.'),
                $jour(7, '2027-06-18', 'Journée libre', 'Piscine, balades ou shopping : à votre rythme.'),
                $jour(8, '2027-06-19', 'Retour', 'Transfert à l\'aéroport et vol vers Paris.'),
            ])
            ->setChoix([
                'destination' => 'santorin',
                'etapes' => [['dateDebut' => '2027-06-12', 'nombreDeNuits' => 7, 'logementIds' => $ids($fira, $suite)]],
                'activitesDemandees' => $ids($croisiere),
                'activitesSuggerees' => $ids($vins),
                'pourquoi' => 'Voyage de noces : couchers de soleil et croisière.',
            ])
            ->setExigences(FicheDemande::depuis($demande))
            ->setCreeLe(new \DateTimeImmutable('-3 days'))
            ->setEnvoyeeLe(new \DateTimeImmutable('-3 days'));
        $demande->addProposition($v1);
        $manager->persist($v1);
        $this->lignes($manager, $v1, [
            [TypeLigne::Logement, $fira->getNom(), $fira, $fira->getPrestataire(), '2027-06-12', '2027-06-19', 1, '7 nuits × 150 €', 105000],
            [TypeLigne::Transport, 'Vol aller-retour Paris – Santorin', null, $prestataire('Air Horizon'), '2027-06-12', '2027-06-19', 2, '2 voyageurs × 370 €', 74000],
            [TypeLigne::Transport, 'Transferts aéroport – hébergement à Santorin', null, $prestataire('Autocars Méridien'), '2027-06-12', '2027-06-19', 2, '2 voyageurs × 42,50 €', 8500],
            [TypeLigne::Activite, $croisiere->getNom(), $croisiere, $croisiere->getPrestataire(), '2027-06-14', '2027-06-14', 2, '2 participants × 120 €', 24000],
        ]);

        // La modification du client : une nuit de plus et une dégustation de vins, demandée à tous les niveaux.
        $demande->setDateFin(new \DateTimeImmutable('2027-06-20'))->setNombreDeJours(8)->setEnvies(['romantique', 'plage', 'gastronomie']);
        $v2 = (new Proposition())
            ->setVersion(2)->setStatut(StatutProposition::Envoyee)->setNiveauBudget(NiveauBudget::Equilibre)
            ->setDestination($commun['destination'])->setPourquoi($commun['pourquoi'])
            ->setFraisDeDossierCentimes(15000)->setTotalCentimes(301200)->setTotalParPersonneCentimes(150600)
            ->setBudgetsParNiveau([
                NiveauBudget::Essentiel->value => ['totalCentimes' => 261000, 'parPersonneCentimes' => 130500],
                NiveauBudget::Equilibre->value => ['totalCentimes' => 301200, 'parPersonneCentimes' => 150600],
                NiveauBudget::Confort->value => ['totalCentimes' => 600600, 'parPersonneCentimes' => 300300],
            ])
            ->setHypotheses([
                'Hôtel Fira Blue, chambre triple : Hôtel 3 étoiles, 1 chambre, 8 nuits du 12/06/2027 au 20/06/2027, tarif d\'avril à juin 2027',
                'Transport inclus : vols et transferts estimés au milieu de la fourchette, pour 2 voyageurs',
                'Frais de dossier voyage de noces : 150 €',
            ])
            ->setArgumentaire([
                ['exigence' => 'Un voyage de noces romantique', 'reponse' => 'Couchers de soleil sur la caldeira, depuis Fira.'],
                ['exigence' => 'Une croisière', 'reponse' => 'Croisière en catamaran dans la caldeira le troisième jour.'],
                ['exigence' => 'Une dégustation de vins', 'reponse' => 'Dégustation de vins volcaniques le cinquième jour.'],
            ])
            ->setPrestations([
                ['id' => (int) $fira->getId(), 'type' => 'logement', 'nom' => (string) $fira->getNom(), 'pointsForts' => ['Piscine', 'Au cœur de Fira'], 'niveaux' => ['essentiel', 'equilibre']],
                ['id' => (int) $suite->getId(), 'type' => 'logement', 'nom' => (string) $suite->getNom(), 'pointsForts' => ['Piscine privée', 'Vue caldeira'], 'niveaux' => ['confort']],
                ['id' => (int) $croisiere->getId(), 'type' => 'activite', 'nom' => (string) $croisiere->getNom(), 'pointsForts' => ['Volcan et sources chaudes'], 'niveaux' => ['essentiel', 'equilibre', 'confort']],
                ['id' => (int) $vins->getId(), 'type' => 'activite', 'nom' => (string) $vins->getNom(), 'pointsForts' => ['Vins du volcan'], 'niveaux' => ['essentiel', 'equilibre', 'confort']],
            ])
            ->setProgramme([
                ...$debut,
                $jour(5, '2027-06-16', 'Dégustation de vins volcaniques', 'Visite d\'un domaine et dégustation des vins de l\'île, à Pyrgos.', $ids($vins)),
                $jour(6, '2027-06-17', 'Village d\'Oia', 'Journée à Oia, ses moulins et son coucher de soleil célèbre.'),
                $jour(7, '2027-06-18', 'Akrotiri', 'Site archéologique d\'Akrotiri, puis la plage rouge.'),
                $jour(8, '2027-06-19', 'Journée libre', 'Piscine, balades ou shopping : à votre rythme.'),
                $jour(9, '2027-06-20', 'Retour', 'Transfert à l\'aéroport et vol vers Paris.'),
            ])
            ->setChoix([
                'destination' => 'santorin',
                'etapes' => [['dateDebut' => '2027-06-12', 'nombreDeNuits' => 8, 'logementIds' => $ids($fira, $suite)]],
                'activitesDemandees' => $ids($croisiere, $vins),
                'activitesSuggerees' => [],
                'pourquoi' => 'Voyage de noces : couchers de soleil, croisière et vins.',
            ])
            ->setExigences(FicheDemande::depuis($demande))
            ->setCreeLe(new \DateTimeImmutable('-2 days'))
            ->setEnvoyeeLe(new \DateTimeImmutable('-2 days'));
        $demande->addProposition($v2);
        $manager->persist($v2);
        $this->lignes($manager, $v2, [
            [TypeLigne::Logement, $fira->getNom(), $fira, $fira->getPrestataire(), '2027-06-12', '2027-06-20', 1, '8 nuits × 150 €', 120000],
            [TypeLigne::Transport, 'Vol aller-retour Paris – Santorin', null, $prestataire('Air Horizon'), '2027-06-12', '2027-06-20', 2, '2 voyageurs × 370 €', 74000],
            [TypeLigne::Transport, 'Transferts aéroport – hébergement à Santorin', null, $prestataire('Autocars Méridien'), '2027-06-12', '2027-06-20', 2, '2 voyageurs × 42,50 €', 8500],
            [TypeLigne::Activite, $croisiere->getNom(), $croisiere, $croisiere->getPrestataire(), '2027-06-14', '2027-06-14', 2, '2 participants × 120 €', 24000],
            [TypeLigne::Activite, $vins->getNom(), $vins, $vins->getPrestataire(), '2027-06-16', '2027-06-16', 2, '2 participants × 60 €', 12000],
        ]);
    }

    /**
     * @param list<array{TypeLigne, string|null, Prestation|null, Prestataire|null, string, string, int, string, int}> $lignes
     */
    private function lignes(ObjectManager $manager, Proposition $proposition, array $lignes): void
    {
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
    }

    public function getDependencies(): array
    {
        return [DemandeFixtures::class, CatalogueFixtures::class];
    }
}
