<?php

namespace App\Tests\Controller;

use App\Entity\Demande;
use App\Entity\LigneDePrestation;
use App\Entity\Prestation;
use App\Entity\Proposition;
use App\Entity\Utilisateur;
use App\Enum\StatutDemande;
use App\Enum\StatutProposition;
use App\Enum\TypeProposition;
use Doctrine\Bundle\FixturesBundle\Loader\SymfonyFixturesLoader;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Écran de confirmation (#209) sur la proposition de démo (Marrakech, Léa) : prix confirmés, écarts en euros et en
 * pourcentage, ajustements, puis confirmation seulement quand tous les prix sont saisis. Nécessite `make db-test`.
 */
final class ConfirmationTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private int $demandeId;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();

        /** @var SymfonyFixturesLoader $loader */
        $loader = static::getContainer()->get('doctrine.fixtures.loader');
        (new ORMExecutor($this->em, new ORMPurger($this->em)))->execute($loader->getFixtures());

        $this->demandeId = (int) $this->em->getRepository(Demande::class)->findOneBy(['lieuOuZone' => 'Marrakech'])?->getId();
        $this->client->loginUser($this->compte('lea@shortcut.test'));
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testLaFicheDeLaDemandeMeneALEcranDeConfirmation(): void
    {
        $crawler = $this->client->request('GET', '/admin/demande/'.$this->demandeId);
        self::assertResponseIsSuccessful();

        $this->client->click($crawler->selectLink('Confirmer la proposition')->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Confirmer la proposition');
    }

    public function testLEcranMontreLaPropositionSesHypothesesEtPrepareUneNouvelleVersion(): void
    {
        $crawler = $this->ouvrir();

        self::assertCount(5, $crawler->filter('tbody tr[data-ligne]'));
        self::assertSelectorTextContains('.bo-confirmation tbody', 'Riad Dar Yasmine, riad entier');
        self::assertSelectorTextContains('.bo-confirmation tfoot', '9 452 €');
        self::assertSelectorTextContains('body', 'Transport inclus : vols et transferts estimés au milieu de la fourchette');
        self::assertSelectorTextContains('body', 'Jour 2 · Médina et souks');
        self::assertSelectorTextContains('.bo-conversation', 'nous sommes 12 amis');

        // La proposition envoyée au client reste intacte : le conseiller travaille sur la version 2.
        [$estimation, $version] = $this->versions();
        self::assertSame(StatutProposition::Envoyee, $estimation->getStatut());
        self::assertSame(2, $version->getVersion());
        self::assertSame(TypeProposition::Confirmee, $version->getType());
        self::assertSame(StatutProposition::Brouillon, $version->getStatut());
        self::assertCount(5, $version->getLignes());
        self::assertNull($this->ligneEnBase($version, 'Riad Dar Yasmine, riad entier')->getPrixConfirmeCentimes());
    }

    public function testLEcartSAfficheEnEurosEtEnPourcentageLigneParLigne(): void
    {
        $crawler = $this->ouvrir();
        $this->client->submit($crawler->selectButton('Enregistrer')->form([
            $this->champ($crawler, 'Riad Dar Yasmine, riad entier') => '3300',
            $this->champ($crawler, 'Vol aller-retour Paris – Marrakech') => '3600',
        ]));
        self::assertResponseRedirects();
        $crawler = $this->client->followRedirect();

        // Riad : 3 300 € au lieu de 3 120 € ; vols : 3 600 € au lieu de 3 180 €, au-delà des 10 % signalés.
        self::assertSame('+180 € +5,8 %', $this->ecart($crawler, 'Riad Dar Yasmine, riad entier'));
        self::assertSame('+420 € +13,2 %', $this->ecart($crawler, 'Vol aller-retour Paris – Marrakech'));
        self::assertCount(1, $this->ligne($crawler, 'Vol aller-retour Paris – Marrakech')->filter('.bo-ecart--fort'));
        self::assertSame('À saisir', $this->ecart($crawler, 'Cours de cuisine marocaine et tajine'));
        // Le total attend tous les prix.
        self::assertStringContainsString('3 prix sur 5 restent à saisir', $crawler->filter('.bo-bandeau')->text());
    }

    public function testOnNeConfirmePasTantQuUnPrixManque(): void
    {
        $crawler = $this->ouvrir();
        $this->client->submit($crawler->selectButton('Confirmer la proposition')->form([
            $this->champ($crawler, 'Riad Dar Yasmine, riad entier') => '3120',
        ]));
        $crawler = $this->client->followRedirect();

        self::assertSelectorTextContains('body', 'Saisissez d\'abord le prix confirmé de : Vol aller-retour Paris – Marrakech');
        [, $version] = $this->versions();
        self::assertSame(StatutProposition::Brouillon, $version->getStatut());
        self::assertSame(312000, $this->ligneEnBase($version, 'Riad Dar Yasmine, riad entier')->getPrixConfirmeCentimes());
    }

    public function testConfirmerCalculeLesTotauxEtEngageLaVersion(): void
    {
        $crawler = $this->ouvrir();
        $this->client->submit($crawler->selectButton('Confirmer la proposition')->form([
            $this->champ($crawler, 'Riad Dar Yasmine, riad entier') => '3300',
            $this->champ($crawler, 'Vol aller-retour Paris – Marrakech') => '3180',
            $this->champ($crawler, 'Transferts aéroport – hébergement à Marrakech') => '330',
            $this->champ($crawler, 'Visite guidée de la médina et des souks') => '432',
            $this->champ($crawler, 'Cours de cuisine marocaine et tajine') => '648',
            'confirmation[fraisDeDossier]' => '150',
        ]));
        $crawler = $this->client->followRedirect();

        // Coût 7 890 € + marge 20 % 1 578 € + frais 150 € = 9 618 €, contre 9 452 € annoncés au client.
        [$estimation, $version] = $this->versions();
        self::assertSame(StatutProposition::Confirmee, $version->getStatut());
        self::assertSame(961800, $version->getTotalCentimes());
        self::assertSame(80150, $version->getTotalParPersonneCentimes());
        self::assertSame(15000, $version->getFraisDeDossierCentimes());
        self::assertSame(['equilibre' => ['totalCentimes' => 961800, 'parPersonneCentimes' => 80150]], $version->getBudgetsParNiveau());
        self::assertSame('lea@shortcut.test', $version->getConfirmeePar()?->getEmail());
        self::assertNotNull($version->getConfirmeeLe());
        self::assertContains('Transport inclus : vols et transferts au prix confirmé, pour 12 voyageurs', $version->getHypotheses());
        self::assertContains('Frais de dossier : 150 €', $version->getHypotheses());
        self::assertSame(StatutDemande::Confirmee, $version->getDemande()?->getStatut());
        self::assertSame(945200, $estimation->getTotalCentimes());

        // Lecture seule, avec l'écart total.
        self::assertSelectorTextContains('h1', 'Proposition confirmée');
        self::assertSelectorTextContains('.bo-bandeau', 'Version 2 confirmée');
        self::assertCount(0, $crawler->filter('input[name^="confirmation["]'));
        self::assertSame('+166 € +1,8 %', trim($crawler->filter('tfoot .bo-total td')->last()->text()));
    }

    public function testLeConseillerRetireUneActiviteEtRemplaceUnLogement(): void
    {
        $crawler = $this->ouvrir();
        $cuisine = (int) $this->ligne($crawler, 'Cours de cuisine marocaine et tajine')->attr('data-ligne');
        $riad = (int) $this->ligne($crawler, 'Riad Dar Yasmine, riad entier')->attr('data-ligne');

        $this->client->request('POST', '/admin/demande/'.$this->demandeId.'/confirmation/retirer', [
            'ligne' => $cuisine,
            '_token' => $crawler->filter('#retirer-'.$cuisine.' input[name="_token"]')->attr('value'),
        ]);
        self::assertResponseRedirects();

        // Kasbah Atlas : 4 personnes par chambre, 160 € × 1,2 en octobre ; 3 chambres × 4 nuits × 192 € = 2 304 €.
        $kasbah = $this->em->getRepository(Prestation::class)->findOneBy(['nom' => 'Kasbah Atlas, chambre familiale']);
        $this->client->request('POST', '/admin/demande/'.$this->demandeId.'/confirmation/remplacer', [
            'ligne' => $riad,
            'logement' => $kasbah?->getId(),
            '_token' => $crawler->filter('#remplacer-'.$riad.' input[name="_token"]')->attr('value'),
        ]);
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Logement remplacé par Kasbah Atlas, chambre familiale');

        [$estimation, $version] = $this->versions();
        self::assertCount(5, $estimation->getLignes());
        self::assertCount(4, $version->getLignes());
        $logement = $this->ligneEnBase($version, 'Kasbah Atlas, chambre familiale');
        self::assertSame(230400, $logement->getPrixEstimeCentimes());
        self::assertSame(3, $logement->getQuantite());
        self::assertSame('Riads & Kasbahs du Sud', $logement->getPrestataire()?->getNom());
        self::assertCount(0, $crawler->filter('#retirer-'.$cuisine));
    }

    public function testUnLogementDUneAutreDestinationNeRemplacePasLeRiad(): void
    {
        $crawler = $this->ouvrir();
        $riad = (int) $this->ligne($crawler, 'Riad Dar Yasmine, riad entier')->attr('data-ligne');
        $lisbonne = $this->em->getRepository(Prestation::class)->findOneBy(['nom' => 'Tejo Palace, suite vue fleuve']);

        $this->client->request('POST', '/admin/demande/'.$this->demandeId.'/confirmation/remplacer', [
            'ligne' => $riad,
            'logement' => $lisbonne?->getId(),
            '_token' => $crawler->filter('#remplacer-'.$riad.' input[name="_token"]')->attr('value'),
        ]);
        $this->client->followRedirect();

        self::assertSelectorTextContains('body', 'Tejo Palace, suite vue fleuve ne peut pas remplacer Riad Dar Yasmine, riad entier');
        [, $version] = $this->versions();
        self::assertSame(312000, $this->ligneEnBase($version, 'Riad Dar Yasmine, riad entier')->getPrixEstimeCentimes());
    }

    public function testUnAutreConseillerNOuvrePasLEcran(): void
    {
        $this->client->loginUser($this->compte('hugo@shortcut.test'));
        $this->client->request('GET', '/admin/demande/'.$this->demandeId.'/confirmation');

        self::assertResponseStatusCodeSame(403);
    }

    private function ouvrir(): Crawler
    {
        $crawler = $this->client->request('GET', '/admin/demande/'.$this->demandeId.'/confirmation');
        self::assertResponseIsSuccessful();

        return $crawler;
    }

    /** @return array{Proposition, Proposition} la proposition envoyée et la version de confirmation */
    private function versions(): array
    {
        $this->em->clear();
        $propositions = $this->em->getRepository(Proposition::class)->findBy(['demande' => $this->demandeId], ['version' => 'ASC']);
        self::assertCount(2, $propositions);

        return [$propositions[0], $propositions[1]];
    }

    /** Les lignes reviennent de la base sans ordre garanti : on les retrouve par leur libellé. */
    private function ligneEnBase(Proposition $proposition, string $libelle): LigneDePrestation
    {
        foreach ($proposition->getLignes() as $ligne) {
            if ($libelle === $ligne->getLibelle()) {
                return $ligne;
            }
        }

        self::fail(\sprintf('Ligne « %s » absente de la version %d.', $libelle, $proposition->getVersion()));
    }

    private function ligne(Crawler $crawler, string $libelle): Crawler
    {
        return $crawler->filter('tbody tr[data-ligne]')->reduce(static fn (Crawler $tr) => str_contains($tr->text(), $libelle))->first();
    }

    private function champ(Crawler $crawler, string $libelle): string
    {
        return (string) $this->ligne($crawler, $libelle)->filter('input[name^="confirmation[ligne_"]')->attr('name');
    }

    private function ecart(Crawler $crawler, string $libelle): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $this->ligne($crawler, $libelle)->filter('td')->eq(5)->text()));
    }

    private function compte(string $email): Utilisateur
    {
        return $this->em->getRepository(Utilisateur::class)->findOneBy(['email' => $email]) ?? throw new \LogicException($email);
    }
}
