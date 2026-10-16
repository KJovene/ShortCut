<?php

namespace App\Tests\Controller;

use App\Entity\Utilisateur;
use App\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Inscription depuis le site (compte client), modification du profil et rôle changé par l'administrateur.
 * Nécessite la base de test : `make db-test`.
 */
final class CompteTest extends WebTestCase
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

    public function testLInscriptionCreeUnClientConnecte(): void
    {
        $this->inscrire(['prenom' => 'Camille', 'nom' => 'Durand', 'email' => ' Camille@Example.com ', 'motDePasse' => self::MOT_DE_PASSE]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('ROLE_CLIENT', $this->reponse()['role']);
        self::assertSame('camille@example.com', $this->reponse()['email']);

        $cree = $this->utilisateur('camille@example.com');
        self::assertSame(Role::Client, $cree->getRole());
        self::assertTrue(static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($cree, self::MOT_DE_PASSE));

        // Connecté d'office, mais le backoffice lui reste fermé.
        $this->client->request('GET', '/api/moi');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/admin');
        self::assertResponseStatusCodeSame(403);
    }

    public function testLInscriptionNePermetPasDeChoisirSonRole(): void
    {
        $this->inscrire(['nom' => 'Pirate', 'email' => 'pirate@example.com', 'motDePasse' => self::MOT_DE_PASSE, 'role' => 'ROLE_ADMIN']);

        self::assertResponseStatusCodeSame(201);
        self::assertSame(Role::Client, $this->utilisateur('pirate@example.com')->getRole());
    }

    public function testLInscriptionSignaleLesErreursParChamp(): void
    {
        $this->creerCompte('pris@example.com', Role::Client);

        $this->inscrire(['nom' => '', 'email' => 'pris@example.com', 'motDePasse' => 'court']);

        self::assertResponseStatusCodeSame(422);
        self::assertEquals([
            'nom' => 'Saisissez votre nom.',
            'email' => 'Un compte existe déjà avec cet e-mail.',
            'motDePasse' => 'Le mot de passe doit faire au moins 8 caractères.',
        ], $this->reponse()['erreurs']);
    }

    public function testLUtilisateurModifieSesCoordonnees(): void
    {
        $this->client->loginUser($this->creerCompte('camille@example.com', Role::Client));

        $this->client->jsonRequest('PATCH', '/api/moi', ['prenom' => 'Camille', 'nom' => 'Martin', 'telephone' => '06 12 34 56 78']);

        self::assertResponseIsSuccessful();
        $modifie = $this->utilisateur('camille@example.com');
        self::assertSame('Martin', $modifie->getNom());
        self::assertSame('06 12 34 56 78', $modifie->getTelephone());
    }

    public function testChangerDEmailOuDeMotDePasseExigeLeMotDePasseActuel(): void
    {
        $this->client->loginUser($this->creerCompte('camille@example.com', Role::Client));

        $this->client->jsonRequest('PATCH', '/api/moi', ['email' => 'nouveau@example.com', 'motDePasseActuel' => 'pas-le-bon']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['motDePasseActuel' => 'Mot de passe actuel incorrect.'], $this->reponse()['erreurs']);

        $this->client->jsonRequest('PATCH', '/api/moi', ['nouveauMotDePasse' => 'encore-plus-solide']);
        self::assertResponseStatusCodeSame(422);

        $this->em->clear();
        self::assertNotNull($this->em->getRepository(Utilisateur::class)->findOneBy(['email' => 'camille@example.com']));
    }

    public function testApresUnChangementDIdentifiantsLaSessionResteOuverte(): void
    {
        $this->creerCompte('camille@example.com', Role::Client);
        $this->client->jsonRequest('POST', '/api/connexion', ['email' => 'camille@example.com', 'motDePasse' => self::MOT_DE_PASSE]);

        $this->client->jsonRequest('PATCH', '/api/moi', [
            'email' => 'nouveau@example.com',
            'nouveauMotDePasse' => 'encore-plus-solide',
            'motDePasseActuel' => self::MOT_DE_PASSE,
        ]);
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/api/moi');
        self::assertResponseIsSuccessful();
        self::assertSame('nouveau@example.com', $this->reponse()['email']);

        // Les nouveaux identifiants fonctionnent.
        $this->client->request('POST', '/api/deconnexion');
        $this->client->jsonRequest('POST', '/api/connexion', ['email' => 'nouveau@example.com', 'motDePasse' => 'encore-plus-solide']);
        self::assertResponseIsSuccessful();
    }

    public function testLUtilisateurNePeutPasChangerSonPropreRole(): void
    {
        $this->client->loginUser($this->creerCompte('camille@example.com', Role::Client));

        $this->client->jsonRequest('PATCH', '/api/moi', ['role' => 'ROLE_ADMIN']);

        self::assertResponseIsSuccessful();
        self::assertSame('ROLE_CLIENT', $this->reponse()['role']);
    }

    public function testLAdministrateurPromeutUnClientEnConseiller(): void
    {
        $client = $this->creerCompte('camille@example.com', Role::Client);
        $this->client->loginUser($this->creerCompte('admin.test@shortcut.test', Role::Administrateur));

        $crawler = $this->client->request('GET', \sprintf('/admin/utilisateur/%d/edit', $client->getId()));
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->filter('form[name="Utilisateur"]')->form([
            'Utilisateur[role]' => Role::Conseiller->value,
        ]));
        self::assertResponseRedirects();

        self::assertSame(Role::Conseiller, $this->utilisateur('camille@example.com')->getRole());
    }

    /** @param array<string, mixed> $donnees */
    private function inscrire(array $donnees): void
    {
        $this->client->jsonRequest('POST', '/api/inscription', $donnees);
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

    private function utilisateur(string $email): Utilisateur
    {
        $this->em->clear();
        $utilisateur = $this->em->getRepository(Utilisateur::class)->findOneBy(['email' => $email]);
        self::assertNotNull($utilisateur);

        return $utilisateur;
    }

    /** @return array<string, mixed> */
    private function reponse(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
