<?php

namespace App\Tests\Controller;

use App\Confirmation\ConfirmationService;
use App\Entity\Demande;
use App\Entity\Proposition;
use App\Entity\Utilisateur;
use App\Enum\Langue;
use App\Proposition\PropositionPdf;
use App\Twig\DateExtension;
use Doctrine\Bundle\FixturesBundle\Loader\SymfonyFixturesLoader;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * La proposition en PDF (#213) sur le jeu de démo : Santorin (deux versions estimées) et Marrakech (confirmée par
 * Léa). Le statut est en première page, aucun prix d'achat n'apparaît. Nécessite `make db-test`.
 */
final class PropositionPdfTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();

        /** @var SymfonyFixturesLoader $loader */
        $loader = static::getContainer()->get('doctrine.fixtures.loader');
        (new ORMExecutor($this->em, new ORMPurger($this->em)))->execute($loader->getFixtures());
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testLePdfSeTelechargeEnUnClicEnMoinsDeCinqSecondes(): void
    {
        [$v1] = $this->versions('Santorin');

        $debut = microtime(true);
        $this->client->request('GET', '/api/propositions/'.$v1->getJetonPublic().'/versions/2/pdf');
        $duree = microtime(true) - $debut;

        self::assertResponseIsSuccessful();
        self::assertLessThan(5, $duree);
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        // Le lien de la version 1 donne aussi accès à la version qui fait foi.
        self::assertResponseHeaderSame('Content-Disposition', 'attachment; filename=proposition-santorin-v2.pdf');
        self::assertStringStartsWith('%PDF-', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/api/propositions/'.$v1->getJetonPublic().'/versions/1/pdf');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Disposition', 'attachment; filename=proposition-santorin-v1.pdf');
    }

    public function testLeStatutEstimeEstEnPremierePageSansPrixDAchat(): void
    {
        [, $v2] = $this->versions('Santorin');
        $html = static::getContainer()->get(PropositionPdf::class)->html($v2);
        $premierePage = strstr($html, '<h2 class="saut">', true);

        self::assertIsString($premierePage);
        self::assertStringContainsString('Proposition estimée', $premierePage);
        self::assertStringContainsString(Proposition::MENTION, $premierePage);
        // Le titre, avec le O qui saute de la charte sur son premier « o ».
        self::assertStringContainsString('Santorin, 9 jours', strip_tags($premierePage));
        self::assertStringContainsString('Sant<span class="saut-o">o</span>rin, 9 jours', $premierePage);
        self::assertStringContainsString('12 juin 2027', $premierePage);
        self::assertStringContainsString('3 012 €', $premierePage);
        self::assertStringContainsString('sam. 12 juin', $html);
        // Le budget en trois niveaux, mais jamais le prix d'achat d'une ligne (Fira Blue : 1 200 € ; vols : 740 €).
        self::assertStringContainsString('2 610 €', $html);
        self::assertStringContainsString('6 006 €', $html);
        self::assertStringNotContainsString('1 200 €', $html);
        self::assertStringNotContainsString('740 €', $html);
    }

    public function testUneVersionConfirmeeLeDitEnPremierePage(): void
    {
        $marrakech = $this->em->getRepository(Demande::class)->findOneBy(['lieuOuZone' => 'Marrakech']) ?? throw new \LogicException();
        $service = static::getContainer()->get(ConfirmationService::class);
        $estimation = $service->estimation($marrakech) ?? throw new \LogicException();
        $version = $service->versionDeConfirmation($marrakech, $estimation);
        $prix = [];
        foreach ($version->getLignes() as $ligne) {
            $prix[(int) $ligne->getId()] = $ligne->getPrixEstimeCentimes();
        }
        $service->saisir($version, $prix, 20000);
        $service->confirmer($version, $estimation, $this->em->getRepository(Utilisateur::class)->findOneBy(['email' => 'lea@shortcut.test']) ?? throw new \LogicException());

        $html = static::getContainer()->get(PropositionPdf::class)->html($version);
        $premierePage = (string) strstr($html, '<h2 class="saut">', true);

        self::assertStringContainsString('Proposition confirmée', $premierePage);
        self::assertStringContainsString('Confirmée par votre conseiller le', $premierePage);
        self::assertStringNotContainsString(Proposition::MENTION, $premierePage);
        self::assertStringContainsString('Votre budget confirmé', $premierePage);
    }

    public function testUnBrouillonOuUnJetonInconnuNOntPasDePdf(): void
    {
        $marrakech = $this->em->getRepository(Demande::class)->findOneBy(['lieuOuZone' => 'Marrakech']) ?? throw new \LogicException();
        $service = static::getContainer()->get(ConfirmationService::class);
        $brouillon = $service->versionDeConfirmation($marrakech, $service->estimation($marrakech) ?? throw new \LogicException());

        $this->client->request('GET', '/api/propositions/'.$brouillon->getJetonPublic().'/versions/'.$brouillon->getVersion().'/pdf');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/api/propositions/'.str_repeat('0', 64).'/versions/1/pdf');
        self::assertResponseStatusCodeSame(404);
    }

    public function testLesDatesSontEcritesEnFrancais(): void
    {
        self::assertSame('1er mai 2027', DateExtension::dateLongue('2027-05-01'));
        self::assertSame('ven. 22 oct.', DateExtension::dateJour('2027-10-22'));
    }

    public function testUnClientQuiDialogueEnAnglaisRecoitSonPdfEnAnglais(): void
    {
        [, $v2] = $this->versions('Santorin');
        $v2->getDemande()?->setLangue(Langue::Anglais);
        $html = static::getContainer()->get(PropositionPdf::class)->html($v2);

        self::assertStringContainsString('<html lang="en">', $html);
        self::assertStringContainsString('Estimated proposal', $html);
        self::assertStringContainsString(Proposition::MENTION_EN, $html);
        // Le titre fait sauter son premier « o » (charte DÉTOUR), comme en français.
        self::assertStringContainsString('Santorin, 9 days', strip_tags($html));
        self::assertStringContainsString('Sant<span class="saut-o">o</span>rin, 9 days', $html);
        self::assertStringContainsString('12 June 2027', $html);
        self::assertStringContainsString('Sat 12 Jun', $html);
        self::assertStringContainsString('Day-by-day itinerary', $html);
        self::assertStringContainsString('Budget assumptions', $html);
        self::assertStringContainsString('Balanced', $html);
        // Les montants restent ceux de la version française.
        self::assertStringContainsString('3 012 €', $html);
        self::assertStringNotContainsString('Programme jour par jour', $html);
        self::assertStringNotContainsString('Hypothèses de budget', $html);
        self::assertStringNotContainsString('Frais de dossier', $html);
    }

    /** @return list<Proposition> les versions de la demande, dans l'ordre */
    private function versions(string $lieu): array
    {
        $demande = $this->em->getRepository(Demande::class)->findOneBy(['lieuOuZone' => $lieu]) ?? throw new \LogicException();

        return $this->em->getRepository(Proposition::class)->findBy(['demande' => $demande], ['version' => 'ASC']);
    }
}
