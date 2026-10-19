<?php

namespace App\Tests\Controller;

use App\Entity\Conversation;
use App\Entity\Demande;
use App\Entity\Utilisateur;
use App\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Fiche client du backoffice (#183, #184) : une recherche par nom ou e-mail retrouve le client,
 * sa fiche montre ses demandes et ses conversations. Nécessite `make db-test`.
 */
final class ClientCrudTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Utilisateur $lea;
    private Utilisateur $hugo;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();

        $this->lea = $this->creerCompte('lea.test@shortcut.test', 'Léa Test', Role::Conseiller);
        $this->hugo = $this->creerCompte('hugo.test@shortcut.test', 'Hugo Test', Role::Conseiller);
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testUneRechercheParNomOuEmailRetrouveLeClient(): void
    {
        $zoe = $this->creerCompte('zoe.fontaine@exemple.test', 'Fontaine', Role::Client);
        $autre = $this->creerCompte('marc.petit@exemple.test', 'Petit', Role::Client);
        $this->client->loginUser($this->lea);

        $this->client->request('GET', '/admin/client', ['query' => 'fontaine']);
        self::assertResponseIsSuccessful();
        self::assertSame([$zoe->getId()], $this->idsAffiches());

        $this->client->request('GET', '/admin/client', ['query' => 'marc.petit@']);
        self::assertSame([$autre->getId()], $this->idsAffiches());
    }

    public function testLaListeNeMontreQueLesClients(): void
    {
        $zoe = $this->creerCompte('zoe.fontaine@exemple.test', 'Fontaine', Role::Client);
        $this->client->loginUser($this->lea);

        $this->client->request('GET', '/admin/client', ['query' => 'test']);
        self::assertResponseIsSuccessful();
        self::assertNotContains($this->hugo->getId(), $this->idsAffiches());

        $this->client->request('GET', '/admin/client/'.$this->hugo->getId());
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/admin/client/'.$zoe->getId().'/edit');
        self::assertResponseStatusCodeSame(403);
    }

    public function testLaFicheMontreLesDemandesEtLesConversations(): void
    {
        $zoe = $this->creerCompte('zoe.fontaine@exemple.test', 'Fontaine', Role::Client);
        $lisbonne = $this->creerDemande($zoe, $this->lea, 'Lisbonne', ['Je voyage avec mon fauteuil roulant.']);
        $islande = $this->creerDemande($zoe, $this->hugo, 'Islande', ['Je veux voir des aurores boréales.']);
        $this->client->loginUser($this->lea);

        $crawler = $this->client->request('GET', '/admin/client/'.$zoe->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Historique (2 demandes)');

        $deLea = $crawler->filter('[data-demande="'.$lisbonne->getId().'"]');
        self::assertStringContainsString('Lisbonne', $deLea->text());
        self::assertStringContainsString('Je voyage avec mon fauteuil roulant.', $deLea->text());

        // La demande d'un autre conseiller est signalée, sans sa conversation.
        $deHugo = $crawler->filter('[data-demande="'.$islande->getId().'"]');
        self::assertStringContainsString('Suivie par Hugo Test', $deHugo->text());
        self::assertStringNotContainsString('aurores boréales', $crawler->text());
    }

    /** @return list<int> */
    private function idsAffiches(): array
    {
        return array_map('intval', $this->client->getCrawler()->filter('table.datagrid tbody tr[data-id]')->each(
            static fn ($ligne) => $ligne->attr('data-id'),
        ));
    }

    /**
     * @param list<string> $messagesDuClient
     */
    private function creerDemande(Utilisateur $client, Utilisateur $conseiller, string $lieu, array $messagesDuClient): Demande
    {
        $demande = (new Demande())->setClient($client)->setConseiller($conseiller)->setLieuOuZone($lieu);
        $conversation = (new Conversation())->setMessages(array_map(
            static fn (string $contenu) => ['role' => 'client', 'contenu' => $contenu, 'le' => '2026-10-19T10:00:00+02:00'],
            $messagesDuClient,
        ));
        $demande->setConversation($conversation);
        $this->em->persist($demande);
        $this->em->persist($conversation);
        $this->em->flush();

        return $demande;
    }

    private function creerCompte(string $email, string $nom, Role $role): Utilisateur
    {
        $utilisateur = (new Utilisateur())->setEmail($email)->setNom($nom)->setRole($role);
        $this->em->persist($utilisateur);
        $this->em->flush();

        return $utilisateur;
    }
}
