<?php

namespace App\Tests\Chatbot\Outil;

use App\Chatbot\ContexteAgent;
use App\Chatbot\Outil\EnregistrerExigences;
use App\Chatbot\Outil\OutilException;
use App\Entity\Conversation;
use App\Entity\Demande;
use App\Enum\AgentIA;
use App\Enum\Langue;
use App\Enum\TypeDeVoyage;
use Doctrine\Bundle\FixturesBundle\Loader\SymfonyFixturesLoader;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Coordonnées du client données dans le chat : la demande lui est rattachée, sauf s'il a déjà 3 voyages en cours
 * (Camille dans le jeu de démo). Nécessite `make db-test`.
 */
final class EnregistrerExigencesTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private EnregistrerExigences $outil;
    private ContexteAgent $contexte;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();
        /** @var SymfonyFixturesLoader $loader */
        $loader = self::getContainer()->get('doctrine.fixtures.loader');
        (new ORMExecutor($this->em, new ORMPurger($this->em)))->execute($loader->getFixtures());

        $this->outil = self::getContainer()->get(EnregistrerExigences::class);
        $demande = (new Demande())->setLieuOuZone('Rome');
        $conversation = new Conversation();
        $demande->setConversation($conversation);
        $this->em->persist($demande);
        $this->em->persist($conversation);
        $this->em->flush();
        $this->contexte = new ContexteAgent($demande, $conversation, AgentIA::Conversation);
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testUnClientAvecDeuxVoyagesEnCoursEstRattache(): void
    {
        $this->outil->executer($this->contexte, ['client' => ['email' => 'julien@shortcut.test'], 'recapitulatifConfirme' => false]);

        self::assertSame('julien@shortcut.test', $this->contexte->demande->getClient()?->getEmail());
    }

    public function testLaLangueDuClientEstEnregistree(): void
    {
        $sortie = $this->outil->executer($this->contexte, ['langue' => 'en', 'recapitulatifConfirme' => false]);

        self::assertSame(Langue::Anglais, $this->contexte->demande->getLangue());
        self::assertSame('en', $sortie['langue']);
        self::assertArrayNotHasKey('langue', $sortie['fiche']);
    }

    public function testUnAppelSansExigenceEstRefuse(): void
    {
        try {
            $this->outil->executer($this->contexte, ['langue' => 'fr', 'recapitulatifConfirme' => false]);
            self::fail('Un appel avec la seule langue déjà enregistrée doit être refusé.');
        } catch (OutilException $e) {
            self::assertStringContainsString('Aucune exigence reçue', $e->getMessage());
        }

        $this->expectException(OutilException::class);
        $this->outil->executer($this->contexte, ['recapitulatifConfirme' => false]);
    }

    public function testLesChampsSontVerifiesParLeServeur(): void
    {
        self::assertFalse($this->outil->definition()['strict']);

        foreach ([
            ['temperatureMin' => 20],
            ['adultes' => 'deux'],
            ['datesFlexibles' => 'false'],
            ['envies' => ['plage', 3]],
            ['agesEnfants' => ['huit']],
        ] as $entree) {
            try {
                $this->outil->executer($this->contexte, $entree + ['recapitulatifConfirme' => false]);
                self::fail(\sprintf('Entrée acceptée à tort : %s', json_encode($entree)));
            } catch (OutilException) {
            }
        }

        // Un champ null vaut absent ; dans l'ordre que le modèle veut.
        $sortie = $this->outil->executer($this->contexte, ['recapitulatifConfirme' => false, 'langue' => 'fr', 'villeDepart' => 'Paris', 'budgetEuros' => null]);
        self::assertSame('Paris', $sortie['fiche']['villeDepart']);
        self::assertNull($sortie['fiche']['budgetEuros']);
    }

    public function testUneConfirmationSeuleEstAcceptee(): void
    {
        $this->contexte->demande->setTypeDeVoyage(TypeDeVoyage::Sejour)->setNombreDeJours(5);

        $sortie = $this->outil->executer($this->contexte, ['recapitulatifConfirme' => true]);

        self::assertTrue($sortie['recapitulatifConfirme']);
    }

    public function testUneLangueInconnueEstRefusee(): void
    {
        $this->expectException(OutilException::class);
        $this->outil->executer($this->contexte, ['langue' => 'de', 'recapitulatifConfirme' => false]);
    }

    public function testUnClientAvecTroisVoyagesEnCoursNEstPasRattache(): void
    {
        try {
            $this->outil->executer($this->contexte, ['client' => ['email' => 'client@shortcut.test'], 'recapitulatifConfirme' => false]);
            self::fail('Un 4e voyage en cours ne doit pas être rattaché à Camille.');
        } catch (OutilException $e) {
            self::assertStringContainsString('déjà 3 voyages en cours', $e->getMessage());
            self::assertStringContainsString('« Mon espace »', $e->getMessage());
        }

        self::assertNull($this->contexte->demande->getClient());
    }
}
