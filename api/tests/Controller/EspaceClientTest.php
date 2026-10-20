<?php

namespace App\Tests\Controller;

use App\Entity\Conversation;
use App\Entity\Demande;
use App\Entity\Proposition;
use App\Entity\Utilisateur;
use App\Enum\MotifTransmission;
use App\Enum\Role;
use App\Enum\StatutDemande;
use App\Enum\StatutProposition;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * « Mon espace » du site : le client connecté retrouve ses demandes, leur proposition et ses conversations.
 * Nécessite la base de test : `make db-test`.
 */
final class EspaceClientTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testSansSessionLaRouteRepond401(): void
    {
        $this->client->request('GET', '/api/moi/demandes');

        self::assertResponseStatusCodeSame(401);
    }

    public function testLeClientNeVoitQueSesDemandesLesPlusRecentesDAbord(): void
    {
        $camille = $this->creerCompte('camille.test@shortcut.test', Role::Client);
        $autre = $this->creerCompte('autre.test@shortcut.test', Role::Client);
        $conseillere = $this->creerCompte('lea.test@shortcut.test', Role::Conseiller)->setPrenom('Léa');

        $ancienne = $this->creerDemande($camille, 'Séville', '-1 month');
        $recente = $this->creerDemande($camille, 'Chypre', '-1 day')
            ->setStatut(StatutDemande::AConfirmer)
            ->setConseiller($conseillere);
        $this->creerDemande($autre, 'Bali', '-1 hour');

        $conversation = (new Conversation())->setMessages([
            ['role' => 'client', 'contenu' => 'Du soleil en mai, à deux.', 'le' => '2026-10-16T15:02:00+02:00'],
        ]);
        $recente->setConversation($conversation)
            ->transmettre(MotifTransmission::DemandeDuClient, 'Le client veut parler à un conseiller.');
        $this->em->persist($conversation);
        $this->em->flush();

        $this->client->loginUser($camille);
        $this->client->request('GET', '/api/moi/demandes');

        self::assertResponseIsSuccessful();
        $demandes = $this->reponse();
        self::assertSame([$recente->getId(), $ancienne->getId()], array_column($demandes, 'id'));
        self::assertSame('a_confirmer', $demandes[0]['statut']);
        self::assertSame(['prenom' => 'Léa', 'nom' => 'Compte de test'], $demandes[0]['conseiller']);
        self::assertSame('Du soleil en mai, à deux.', $demandes[0]['conversation']['messages'][0]['contenu']);
        // « Modifier le voyage » : de quoi reprendre la conversation dans le chat.
        self::assertSame($conversation->getJeton(), $demandes[0]['conversation']['jeton']);
        self::assertFalse($demandes[0]['conversation']['repriseParConseiller']);
        self::assertSame('Chypre', $demandes[0]['conversation']['fiche']['lieuOuZone']);
        self::assertNull($demandes[1]['conversation']);
        self::assertNotNull($demandes[0]['transmiseLe']);
        self::assertNull($demandes[1]['transmiseLe']);
    }

    public function testLesBrouillonsDePropositionRestentCachesAuClient(): void
    {
        $camille = $this->creerCompte('camille.test@shortcut.test', Role::Client);
        $demande = $this->creerDemande($camille, 'Chypre', '-1 day');
        $this->creerProposition($demande, 1, StatutProposition::Envoyee, 376200);
        $this->creerProposition($demande, 2, StatutProposition::Brouillon, 410000);

        $this->client->loginUser($camille);
        $this->client->request('GET', '/api/moi/demandes');

        $proposition = $this->reponse()[0]['proposition'];
        self::assertSame(1, $proposition['version']);
        self::assertSame(376200, $proposition['totalCentimes']);
    }

    private function creerCompte(string $email, Role $role): Utilisateur
    {
        $utilisateur = (new Utilisateur())->setEmail($email)->setNom('Compte de test')->setRole($role);
        $this->em->persist($utilisateur);
        $this->em->flush();

        return $utilisateur;
    }

    private function creerDemande(Utilisateur $client, string $lieu, string $recue): Demande
    {
        $date = new \DateTimeImmutable($recue);
        $demande = (new Demande())->setClient($client)->setLieuOuZone($lieu)->setCreeLe($date)->setModifieLe($date);
        $this->em->persist($demande);
        $this->em->flush();

        return $demande;
    }

    private function creerProposition(Demande $demande, int $version, StatutProposition $statut, int $totalCentimes): void
    {
        $proposition = (new Proposition())->setVersion($version)->setStatut($statut)->setTotalCentimes($totalCentimes);
        $demande->addProposition($proposition);
        $this->em->persist($proposition);
        $this->em->flush();
    }

    /** @return list<array<string, mixed>> */
    private function reponse(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
