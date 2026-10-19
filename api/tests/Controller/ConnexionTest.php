<?php

namespace App\Tests\Controller;

use App\Entity\Utilisateur;
use App\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Connexion au backoffice (ticket #180) : le site React poste e-mail et mot de passe en JSON,
 * la session Symfony ouvre ensuite le backoffice. Nécessite la base de test : `make db-test`.
 */
final class ConnexionTest extends WebTestCase
{
    private const MOT_DE_PASSE = 'motdepasse-solide';

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

    public function testSansSessionLeBackofficeRenvoieVersLaConnexionDuSite(): void
    {
        $this->client->request('GET', '/admin');
        self::assertResponseRedirects('http://localhost:5173/connexion');

        $this->client->request('GET', '/api/moi');
        self::assertResponseStatusCodeSame(401);
    }

    public function testUnCompteCreeParLAdministrateurPeutSeConnecter(): void
    {
        $this->creerCompte('lea.test@shortcut.test', Role::Conseiller);

        $this->seConnecter('lea.test@shortcut.test', self::MOT_DE_PASSE);
        self::assertResponseIsSuccessful();
        self::assertSame(
            ['email' => 'lea.test@shortcut.test', 'prenom' => null, 'nom' => 'Compte de test', 'telephone' => null, 'role' => 'ROLE_CONSEILLER'],
            $this->reponse(),
        );

        // La session ouverte par le site donne accès au backoffice.
        $this->client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Bonjour Compte de test');
    }

    public function testUnMauvaisMotDePasseRenvoieUnMessageClair(): void
    {
        $this->creerCompte('lea.test@shortcut.test', Role::Conseiller);

        $this->seConnecter('lea.test@shortcut.test', 'pas-le-bon');

        self::assertResponseStatusCodeSame(401);
        self::assertSame(['error' => 'Identifiants invalides.'], $this->reponse());

        $this->client->request('GET', '/admin');
        self::assertResponseRedirects('http://localhost:5173/connexion');
    }

    public function testLeMotDePasseEstHacheEnBase(): void
    {
        $utilisateur = $this->creerCompte('lea.test@shortcut.test', Role::Conseiller);

        self::assertNotSame(self::MOT_DE_PASSE, $utilisateur->getPassword());
        self::assertStringStartsWith('$', (string) $utilisateur->getPassword());
    }

    public function testLaDeconnexionFermeLaSession(): void
    {
        $this->creerCompte('lea.test@shortcut.test', Role::Conseiller);

        // Depuis le site (fetch) : 204.
        $this->seConnecter('lea.test@shortcut.test', self::MOT_DE_PASSE);
        $this->client->request('POST', '/api/deconnexion');
        self::assertResponseStatusCodeSame(204);
        $this->client->request('GET', '/api/moi');
        self::assertResponseStatusCodeSame(401);

        // Depuis le menu EasyAdmin (lien) : retour à la page de connexion.
        $this->seConnecter('lea.test@shortcut.test', self::MOT_DE_PASSE);
        $this->client->request('GET', '/api/deconnexion');
        self::assertResponseRedirects('http://localhost:5173/connexion');
        $this->client->request('GET', '/admin');
        self::assertResponseRedirects('http://localhost:5173/connexion');
    }

    public function testSeulLAdministrateurGereLesComptes(): void
    {
        $conseiller = $this->creerCompte('lea.test@shortcut.test', Role::Conseiller);
        $this->client->loginUser($conseiller);
        $this->client->request('GET', '/admin/utilisateur');
        self::assertResponseStatusCodeSame(403);

        $admin = $this->creerCompte('admin.test@shortcut.test', Role::Administrateur);
        $this->client->loginUser($admin);
        $this->client->request('GET', '/admin/utilisateur');
        self::assertResponseIsSuccessful();
    }

    public function testLAdministrateurCreeUnCompteAvecUnMotDePasseHache(): void
    {
        $this->client->loginUser($this->creerCompte('admin.test@shortcut.test', Role::Administrateur));

        $crawler = $this->client->request('GET', '/admin/utilisateur/new');
        self::assertResponseIsSuccessful();

        $formulaire = $crawler->filter('form[name="Utilisateur"]')->form([
            'Utilisateur[nom]' => 'Nouvelle Conseillère',
            'Utilisateur[email]' => 'nouvelle@shortcut.test',
            'Utilisateur[role]' => Role::Conseiller->value,
            'Utilisateur[motDePasse][first]' => self::MOT_DE_PASSE,
            'Utilisateur[motDePasse][second]' => self::MOT_DE_PASSE,
        ]);
        $this->client->submit($formulaire);
        self::assertResponseRedirects();

        $cree = $this->em->getRepository(Utilisateur::class)->findOneBy(['email' => 'nouvelle@shortcut.test']);
        self::assertNotNull($cree);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($cree, self::MOT_DE_PASSE));

        // Et ce compte peut ensuite se connecter depuis le site.
        $this->client->request('POST', '/api/deconnexion');
        $this->seConnecter('nouvelle@shortcut.test', self::MOT_DE_PASSE);
        self::assertResponseIsSuccessful();
    }

    private function creerCompte(string $email, Role $role): Utilisateur
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $utilisateur = (new Utilisateur())->setEmail($email)->setNom('Compte de test')->setRole($role);
        $utilisateur->setMotDePasse($hasher->hashPassword($utilisateur, self::MOT_DE_PASSE));
        $this->em->persist($utilisateur);
        $this->em->flush();

        return $utilisateur;
    }

    private function seConnecter(string $email, string $motDePasse): void
    {
        $this->client->jsonRequest('POST', '/api/connexion', ['email' => $email, 'motDePasse' => $motDePasse]);
    }

    /** @return array<string, mixed> */
    private function reponse(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
