<?php

namespace App\Tests\Controller;

use App\Entity\Demande;
use App\Entity\JournalIA;
use App\Entity\Utilisateur;
use App\Enum\AgentIA;
use App\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Journal de l'IA dans le backoffice (US13, ticket #214) : le responsable contrôle chaque appel des agents.
 * Nécessite la base de test : `make db-test`.
 */
final class JournalIACrudTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Utilisateur $admin;
    private Utilisateur $lea;
    private Demande $demande;
    private Demande $autre;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();

        $this->admin = $this->creerCompte('admin.test@shortcut.test', Role::Administrateur, 'Responsable');
        $this->lea = $this->creerCompte('lea.test@shortcut.test', Role::Conseiller, 'Léa');
        $camille = $this->creerCompte('camille.test@shortcut.test', Role::Client, 'Camille Durand');

        $this->demande = (new Demande())->setClient($camille)->setConseiller($this->lea)->setLieuOuZone('Lisbonne');
        $this->autre = (new Demande())->setLieuOuZone('Bali');
        $this->em->persist($this->demande);
        $this->em->persist($this->autre);
        $this->em->flush();

        $this->journaliser($this->demande, AgentIA::Conversation, [
            'contenu' => [['type' => 'tool_use', 'id' => 'outil_1', 'name' => 'enregistrer_exigences', 'input' => []]],
            'stopReason' => 'tool_use',
            'resultatsOutils' => [],
        ], 1250);
        $this->journaliser($this->demande, AgentIA::Recherche, ['erreur' => 'API Claude indisponible (500).'], 30000);
        $this->journaliser($this->autre, AgentIA::Conversation, ['contenu' => [['type' => 'text', 'text' => 'Bonjour !']], 'stopReason' => 'end_turn'], 800);
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testLeResponsableVoitLAgentLaDemandeLeClientEtLaDate(): void
    {
        $this->client->loginUser($this->admin);
        $crawler = $this->client->request('GET', '/admin/journal-ia');

        self::assertResponseIsSuccessful();
        $lignes = $crawler->filter('table.datagrid tbody tr');
        self::assertCount(3, $lignes);
        $texte = $crawler->filter('table.datagrid')->text();
        self::assertStringContainsString('Conversation', $texte);
        self::assertStringContainsString('Recherche', $texte);
        self::assertStringContainsString('n° '.$this->demande->getId(), $texte);
        self::assertStringContainsString('Camille Durand', $texte);
        self::assertStringContainsString('enregistrer_exigences', $texte);
        self::assertStringContainsString('1,3 s', $texte);
        self::assertStringContainsString('API Claude indisponible', $texte);
        self::assertMatchesRegularExpression('#\d{2}/\d{2}/\d{4} \d{2}:\d{2}#', $texte);
    }

    public function testLeJournalEstReserveAuResponsable(): void
    {
        $this->client->loginUser($this->lea);
        $this->client->request('GET', '/admin/journal-ia');

        self::assertResponseStatusCodeSame(403);
    }

    public function testLeJournalSeFiltreParDemande(): void
    {
        $this->client->loginUser($this->admin);
        $crawler = $this->client->request('GET', '/admin/journal-ia', [
            'filters' => ['demande' => ['comparison' => '=', 'value' => $this->autre->getId()]],
        ]);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('table.datagrid tbody tr'));
        self::assertStringContainsString('n° '.$this->autre->getId(), $crawler->filter('table.datagrid')->text());
    }

    public function testLeDetailMontreLEntreeEtLaSortie(): void
    {
        $journal = $this->em->getRepository(JournalIA::class)->findOneBy(['demande' => $this->demande, 'agent' => AgentIA::Conversation]);

        $this->client->loginUser($this->admin);
        $this->client->request('GET', '/admin/journal-ia/'.$journal?->getId());

        self::assertResponseIsSuccessful();
        // Deux blocs JSON : l'entrée envoyée à Claude, puis sa sortie.
        $blocs = $this->client->getCrawler()->filter('pre.bo-json');
        self::assertCount(2, $blocs);
        self::assertStringContainsString('Bonjour, je cherche du soleil.', $blocs->eq(0)->text());
        self::assertStringContainsString('"name": "enregistrer_exigences"', $blocs->eq(1)->text());
    }

    public function testLaFicheDemandeOuvreSonJournalEnUnClic(): void
    {
        $this->client->loginUser($this->admin);
        $crawler = $this->client->request('GET', '/admin/demande/'.$this->demande->getId());

        // Le bouton d'action de la fiche, pas l'entrée du menu latéral qui porte le même nom.
        $lien = $crawler->filter('a.action-journalIA');
        self::assertCount(1, $lien);
        $crawler = $this->client->click($lien->link());

        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('table.datagrid tbody tr'));

        // Le conseiller n'a pas ce bouton : le journal est réservé au responsable.
        $this->client->loginUser($this->lea);
        $crawler = $this->client->request('GET', '/admin/demande/'.$this->demande->getId());
        self::assertCount(0, $crawler->filter('a.action-journalIA'));
        self::assertCount(0, $crawler->selectLink('Journal IA'), 'Ni dans le menu.');
    }

    private function creerCompte(string $email, Role $role, string $nom): Utilisateur
    {
        $utilisateur = (new Utilisateur())->setEmail($email)->setNom($nom)->setRole($role);
        $this->em->persist($utilisateur);
        $this->em->flush();

        return $utilisateur;
    }

    /** @param array<string, mixed> $sortie */
    private function journaliser(Demande $demande, AgentIA $agent, array $sortie, int $dureeMs): void
    {
        $journal = (new JournalIA())
            ->setAgent($agent)
            ->setDemande($demande)
            ->setModele('claude-sonnet-5-5')
            ->setEntree(['messages' => [['role' => 'user', 'content' => 'Bonjour, je cherche du soleil.']]])
            ->setSortie($sortie)
            ->setDureeMs($dureeMs);
        $this->em->persist($journal);
        $this->em->flush();
    }
}
