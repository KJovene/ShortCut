<?php

namespace App\Tests\Proposition;

use App\Chiffrage\ChiffrageCache;
use App\Entity\Demande;
use App\Enum\NiveauBudget;
use App\Enum\StatutDemande;
use App\Enum\StatutProposition;
use App\Enum\TypeLigne;
use App\Proposition\PropositionRefusee;
use App\Proposition\PropositionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Enregistrement d'une proposition rédigée (#207) sur un chiffrage mis en cache par le test : validation de la sortie
 * structurée du modèle (Serializer, Validator), cohérence avec le chiffrage, versions. Nécessite `make db-test`.
 */
final class PropositionServiceTest extends KernelTestCase
{
    private const LOGEMENT = 101;
    private const PALACE = 102;
    private const BATEAU = 201;

    private EntityManagerInterface $em;
    private PropositionService $service;
    private Demande $demande;
    private string $chiffrageId;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();
        $this->service = static::getContainer()->get(PropositionService::class);

        $this->demande = (new Demande())->setNombreDeJours(3)->setAdultes(2);
        $this->em->persist($this->demande);
        $this->em->flush();
        $this->chiffrageId = static::getContainer()->get(ChiffrageCache::class)->enregistrer($this->demande, self::chiffrage());
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testEnregistreLaVersion1EnBrouillonAvecLesMontantsDuChiffrage(): void
    {
        $proposition = $this->service->enregistrer($this->demande, $this->service->lire($this->entree()));

        self::assertSame(1, $proposition->getVersion());
        self::assertSame(StatutProposition::Brouillon, $proposition->getStatut());
        self::assertSame(StatutDemande::Nouvelle, $this->demande->getStatut());
        self::assertSame(NiveauBudget::Equilibre, $proposition->getNiveauBudget());
        self::assertSame(123456, $proposition->getTotalCentimes());
        self::assertSame(61728, $proposition->getTotalParPersonneCentimes());
        self::assertSame(['essentiel' => ['totalCentimes' => 90000, 'parPersonneCentimes' => 45000], 'equilibre' => ['totalCentimes' => 123456, 'parPersonneCentimes' => 61728], 'confort' => ['totalCentimes' => 200000, 'parPersonneCentimes' => 100000]], $proposition->getBudgetsParNiveau());
        self::assertSame(['Hôtel du port : 3 nuits'], $proposition->getHypotheses());
        self::assertSame(['2027-06-12', '2027-06-13', '2027-06-14', '2027-06-15'], array_column($proposition->getProgramme(), 'date'));
        self::assertSame([[self::BATEAU], [], [], []], array_column($proposition->getProgramme(), 'activiteIds'));
        self::assertSame([
            ['id' => self::LOGEMENT, 'type' => 'logement', 'nom' => 'Hôtel du port', 'pointsForts' => ['Vue mer'], 'niveaux' => ['essentiel', 'equilibre']],
            ['id' => self::PALACE, 'type' => 'logement', 'nom' => 'Palace', 'pointsForts' => ['Spa'], 'niveaux' => ['confort']],
            ['id' => self::BATEAU, 'type' => 'activite', 'nom' => 'Sortie en bateau', 'pointsForts' => ['Coucher de soleil'], 'niveaux' => ['equilibre', 'confort']],
        ], $proposition->getPrestations());

        $lignes = array_values($proposition->getLignes()->toArray());
        self::assertSame([TypeLigne::Logement, TypeLigne::Transport, TypeLigne::Activite], array_map(static fn ($l) => $l->getType(), $lignes));
        self::assertSame([60000, 40045, 23411], array_map(static fn ($l) => $l->getPrixEstimeCentimes(), $lignes));
        self::assertSame('3 nuits × 200 €', $lignes[0]->getCalcul());
        self::assertSame('2027-06-15', $lignes[0]->getDateFin()?->format('Y-m-d'));
    }

    public function testLeConseillerEnvoieLeBrouillon(): void
    {
        $proposition = $this->service->enregistrer($this->demande, $this->service->lire($this->entree()));
        $this->service->envoyer($proposition);

        self::assertSame(StatutProposition::Envoyee, $proposition->getStatut());
        self::assertSame(StatutDemande::PropositionEnvoyee, $this->demande->getStatut());

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('La version 1 est déjà envoyée au client.');
        $this->service->envoyer($proposition);
    }

    public function testUnNouveauBrouillonRemplaceLeBrouillonMaisPasLaVersionEnvoyee(): void
    {
        $envoyee = $this->service->enregistrer($this->demande, $this->service->lire($this->entree()));
        $this->service->envoyer($envoyee);
        $brouillon = $this->service->enregistrer($this->demande, $this->service->lire($this->entree()));
        $suivant = $this->service->enregistrer($this->demande, $this->service->lire($this->entree(['resumeDesChangements' => 'Hôtel plus calme.'])));

        self::assertSame(3, $suivant->getVersion());
        self::assertSame(StatutProposition::Remplacee, $brouillon->getStatut());
        // Le client garde la version envoyée jusqu'à ce que le conseiller envoie la nouvelle.
        self::assertSame(StatutProposition::Envoyee, $envoyee->getStatut());

        $this->service->envoyer($suivant);
        self::assertSame(StatutProposition::Remplacee, $envoyee->getStatut());
        self::assertSame(StatutProposition::Envoyee, $suivant->getStatut());
    }

    /**
     * @param array<string, mixed> $entree
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('entreesInvalides')]
    public function testRefuseUneSortieMalFormee(array $entree, string $raison): void
    {
        try {
            $this->service->lire($this->entree($entree));
            self::fail('Sortie acceptée.');
        } catch (PropositionRefusee $e) {
            self::assertStringContainsString($raison, $e->getMessage());
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function entreesInvalides(): iterable
    {
        yield 'niveau inconnu' => [['niveauDetaille' => 'luxe'], 'niveauDetaille'];
        yield 'jour sans titre' => [['programme' => [['jour' => 1, 'titre' => '', 'description' => 'Arrivée.', 'activiteIds' => []], ['jour' => 2, 'titre' => 'Départ', 'description' => 'Retour.', 'activiteIds' => []]]], 'programme[0].titre'];
        yield 'jours dans le désordre' => [['programme' => [['jour' => 2, 'titre' => 'A', 'description' => 'A.', 'activiteIds' => []], ['jour' => 1, 'titre' => 'B', 'description' => 'B.', 'activiteIds' => []]]], 'numérotés 1, 2, 3'];
        yield 'argumentaire vide' => [['argumentaire' => []], 'Relie au moins une exigence'];
        yield 'trop de points forts' => [['prestations' => [['id' => self::LOGEMENT, 'pointsForts' => ['a', 'b', 'c', 'd', 'e']]]], 'prestations[0].pointsForts'];
        yield 'champ inconnu' => [['prix' => 1000], 'prix'];
        yield 'type faux' => [['programme' => 'jour 1'], 'programme'];
    }

    public function testRefuseUneRedactionQuiNeCollePasAuChiffrage(): void
    {
        $entree = $this->entree([
            'programme' => array_map(static fn (int $j): array => ['jour' => $j, 'titre' => 'Jour '.$j, 'description' => 'Libre.', 'activiteIds' => 1 === $j ? [999] : []], range(1, 3)),
            'prestations' => [['id' => self::LOGEMENT, 'pointsForts' => ['Vue mer']], ['id' => 777, 'pointsForts' => ['?']], ['id' => self::LOGEMENT, 'pointsForts' => ['Vue mer']]],
        ]);

        try {
            $this->service->enregistrer($this->demande, $this->service->lire($entree));
            self::fail('Proposition acceptée.');
        } catch (PropositionRefusee $e) {
            self::assertSame([
                'Le programme compte 3 jours ; le voyage en compte 4 (3 nuits, du jour d\'arrivée au jour du départ).',
                'Donne aussi les points forts de « Sortie en bateau » (id 201).',
                'Donne aussi les points forts de « Palace » (id 102).',
                'La prestation 777 n\'est retenue par aucun niveau : retire-la de prestations.',
                'La prestation 101 est présentée deux fois.',
                'Place l\'activité « Sortie en bateau » (id 201) dans un jour du programme (activiteIds).',
                'L\'activité 999 n\'est pas dans le niveau Équilibré : retire-la du programme.',
            ], $e->raisons);
        }
        self::assertCount(0, $this->demande->getPropositions());
    }

    public function testRefuseUnChiffrageInconnu(): void
    {
        $this->expectException(PropositionRefusee::class);
        $this->expectExceptionMessage('Le chiffrage « chf_00000000 » est inconnu ou a expiré');

        $this->service->enregistrer($this->demande, $this->service->lire($this->entree(['chiffrageId' => 'chf_00000000'])));
    }

    /**
     * Une rédaction valide pour le chiffrage du test, à modifier champ par champ.
     *
     * @param array<string, mixed> $remplacements
     *
     * @return array<string, mixed>
     */
    private function entree(array $remplacements = []): array
    {
        return array_replace([
            'chiffrageId' => $this->chiffrageId,
            'niveauDetaille' => 'equilibre',
            'pourquoi' => 'Le soleil et la mer, à deux heures de vol.',
            'argumentaire' => [['exigence' => 'du soleil', 'reponse' => '25 °C de moyenne en juin.']],
            'prestations' => [
                ['id' => self::LOGEMENT, 'pointsForts' => ['Vue mer']],
                ['id' => self::PALACE, 'pointsForts' => ['Spa']],
                ['id' => self::BATEAU, 'pointsForts' => ['Coucher de soleil']],
            ],
            'programme' => array_map(static fn (int $j): array => ['jour' => $j, 'titre' => 'Jour '.$j, 'description' => 'Programme du jour.', 'activiteIds' => 1 === $j ? [self::BATEAU] : []], range(1, 4)),
        ], $remplacements);
    }

    /**
     * Chiffrage tel que le laisse retenir_combinaison en cache : sortie de calculer_budget et combinaison.
     *
     * @return array<string, mixed>
     */
    private static function chiffrage(): array
    {
        $hotel = ['id' => self::LOGEMENT, 'nom' => 'Hôtel du port', 'fiche' => '/api/logements/101'];
        $palace = ['id' => self::PALACE, 'nom' => 'Palace', 'fiche' => '/api/logements/102'];
        $bateau = ['id' => self::BATEAU, 'nom' => 'Sortie en bateau', 'fiche' => '/api/activites/201'];
        $niveau = static fn (float|int $total, float|int $parPersonne, array $postes): array => [
            'postes' => $postes,
            'fraisDeDossierEuros' => 150,
            'totalEuros' => $total,
            'parPersonneEuros' => $parPersonne,
            'hypotheses' => ['Hôtel du port : 3 nuits'],
        ];
        $poste = static fn (string $type, string $libelle, string $calcul, float|int $euros): array => ['type' => $type, 'libelle' => $libelle, 'calcul' => $calcul, 'montantEuros' => $euros, 'estime' => 'transport' === $type];

        return [
            'chiffrageId' => 'chf_test',
            'niveaux' => [
                'essentiel' => $niveau(900, 450, [$poste('hebergement', 'Hôtel du port', '3 nuits × 200 €', 600)]),
                'equilibre' => $niveau(1234.56, 617.28, [
                    $poste('hebergement', 'Hôtel du port', '3 nuits × 200 €', 600),
                    $poste('transport', 'Vol aller-retour Paris – Santorin', '2 voyageurs × 200,23 €', 400.45),
                    $poste('activite', 'Sortie en bateau', '2 participants × 117,06 €', 234.11),
                ]),
                'confort' => $niveau(2000, 1000, [$poste('hebergement', 'Palace', '3 nuits × 400 €', 1200)]),
            ],
            'combinaison' => [
                'destination' => ['slug' => 'santorin', 'ville' => 'Santorin', 'pays' => 'Grèce', 'fiche' => '/api/destinations/1'],
                'etapes' => [['dateDebut' => '2027-06-12', 'nombreDeNuits' => 3, 'logements' => [$hotel, $palace]]],
                'justification' => [],
                'niveaux' => [
                    'essentiel' => ['logements' => [$hotel], 'activites' => [], 'transport' => 'minimum', 'totalEuros' => 900, 'parPersonneEuros' => 450],
                    'equilibre' => ['logements' => [$hotel], 'activites' => [$bateau], 'transport' => 'milieu', 'totalEuros' => 1234.56, 'parPersonneEuros' => 617.28],
                    'confort' => ['logements' => [$palace], 'activites' => [$bateau], 'transport' => 'maximum', 'totalEuros' => 2000, 'parPersonneEuros' => 1000],
                ],
            ],
        ];
    }
}
