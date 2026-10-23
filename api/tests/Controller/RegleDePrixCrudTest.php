<?php

namespace App\Tests\Controller;

use App\Entity\RegleDePrix;
use App\Entity\Utilisateur;
use App\Enum\Role;
use App\Enum\TypeDeVoyage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Règles de prix (D16, D21, D23) : l'administrateur seul modifie la marge et le barème des frais de dossier, que le
 * site lit ensuite. Nécessite la base de test : `make db-test`.
 */
final class RegleDePrixCrudTest extends WebTestCase
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

    public function testSeulLAdministrateurModifieLesReglesDePrix(): void
    {
        $this->client->loginUser($this->compte(Role::Conseiller));
        $this->client->request('GET', '/admin/regle-de-prix');
        self::assertResponseStatusCodeSame(403);
    }

    public function testLAdministrateurModifieLeBaremeQueLeSiteAffiche(): void
    {
        $this->client->loginUser($this->compte(Role::Administrateur));

        // Le menu mène directement à la règle unique, en modification.
        $this->client->request('GET', '/admin/regle-de-prix');
        self::assertResponseRedirects();
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Règles de prix');

        $formulaire = $crawler->filter('form[name="RegleDePrix"]')->form([
            'RegleDePrix[baremeFraisDossier][sejour]' => '120',
        ]);
        $this->client->submit($formulaire);
        self::assertResponseRedirects();

        $this->em->clear();
        $regles = $this->em->getRepository(RegleDePrix::class)->findAll();
        self::assertCount(1, $regles);
        self::assertSame(12000, $regles[0]->getFraisDeDossierCentimes(TypeDeVoyage::Sejour));
        self::assertSame(15000, $regles[0]->getFraisDeDossierCentimes(TypeDeVoyage::Noces));

        $this->client->request('GET', '/api/public/frais-de-dossier');
        self::assertResponseIsSuccessful();
        self::assertSame(
            ['sejour' => 12000, 'circuit' => 15000, 'groupe' => 20000, 'noces' => 15000, 'entreprise' => 20000],
            json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR),
        );
    }

    public function testDesFraisHorsDesBornesSontRefuses(): void
    {
        $this->client->loginUser($this->compte(Role::Administrateur));
        $this->client->request('GET', '/admin/regle-de-prix');
        $crawler = $this->client->followRedirect();

        $formulaire = $crawler->filter('form[name="RegleDePrix"]')->form([
            'RegleDePrix[baremeFraisDossier][groupe]' => '250',
        ]);
        $this->client->submit($formulaire);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Les frais de dossier « Groupe » doivent être compris entre le minimum et le maximum.');
    }

    private function compte(Role $role): Utilisateur
    {
        $compte = (new Utilisateur())->setEmail($role->value.'.regles@shortcut.test')->setNom('Compte test')->setRole($role);
        $this->em->persist($compte);
        $this->em->flush();

        return $compte;
    }
}
