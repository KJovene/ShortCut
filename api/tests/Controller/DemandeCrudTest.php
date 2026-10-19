<?php

namespace App\Tests\Controller;

use App\Entity\Demande;
use App\Entity\Utilisateur;
use App\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Liste des demandes du backoffice (US01) : un conseiller ne voit que ses demandes, l'administrateur
 * les voit toutes et peut filtrer par conseiller ; les prioritaires d'abord. Nécessite `make db-test`.
 */
final class DemandeCrudTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Utilisateur $lea;
    private Utilisateur $hugo;
    private Utilisateur $admin;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();

        $this->lea = $this->creerCompte('lea.test@shortcut.test', Role::Conseiller);
        $this->hugo = $this->creerCompte('hugo.test@shortcut.test', Role::Conseiller);
        $this->admin = $this->creerCompte('admin.test@shortcut.test', Role::Administrateur);
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testUnConseillerNeVoitQueSesDemandesLesPrioritairesEnPremier(): void
    {
        $ancienne = $this->creerDemande($this->lea, 'Lisbonne', false, '-1 day');
        $recente = $this->creerDemande($this->lea, 'Rome', false, '-1 hour');
        $prioritaire = $this->creerDemande($this->lea, 'Santorin', true, '-3 days');
        $this->creerDemande($this->hugo, 'Islande', true, '-1 hour');
        $this->creerDemande(null, 'Bali', true, '-1 hour');

        $this->client->loginUser($this->lea);
        $this->client->request('GET', '/admin/demande');

        self::assertResponseIsSuccessful();
        self::assertSame([$prioritaire->getId(), $recente->getId(), $ancienne->getId()], $this->idsAffiches());
    }

    public function testUnConseillerNOuvrePasLaDemandeDUnAutre(): void
    {
        $demandeDeHugo = $this->creerDemande($this->hugo, 'Islande', false, '-1 hour');

        $this->client->loginUser($this->lea);
        $this->client->request('GET', '/admin/demande/'.$demandeDeHugo->getId());
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/admin/demande/'.$demandeDeHugo->getId().'/edit');
        self::assertResponseStatusCodeSame(403);
    }

    public function testLAdministrateurVoitToutEtFiltreParConseiller(): void
    {
        $deLea = $this->creerDemande($this->lea, 'Lisbonne', false, '-1 hour');
        $deHugo = $this->creerDemande($this->hugo, 'Islande', true, '-1 day');
        $nonAttribuee = $this->creerDemande(null, 'Bali', false, '-2 hours');

        $this->client->loginUser($this->admin);
        $this->client->request('GET', '/admin/demande');
        self::assertResponseIsSuccessful();
        $ids = $this->idsAffiches();
        foreach ([$deLea, $deHugo, $nonAttribuee] as $demande) {
            self::assertContains($demande->getId(), $ids);
        }
        self::assertSame($deHugo->getId(), $ids[0]);

        $this->client->request('GET', '/admin/demande', [
            'filters' => ['conseiller' => ['comparison' => '=', 'value' => $this->hugo->getId()]],
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame([$deHugo->getId()], array_values(array_intersect(
            $this->idsAffiches(),
            [$deLea->getId(), $deHugo->getId(), $nonAttribuee->getId()],
        )));
    }

    public function testUneDemandeCreeeParUnConseillerLuiEstAttribuee(): void
    {
        $this->client->loginUser($this->lea);
        $crawler = $this->client->request('GET', '/admin/demande/new');
        self::assertResponseIsSuccessful();

        $this->client->submit($crawler->filter('form[name="Demande"]')->form([
            'Demande[lieuOuZone]' => 'Tenerife',
            'Demande[adultes]' => 2,
            'Demande[enfants]' => 0,
        ]));
        self::assertResponseRedirects();

        $creee = $this->em->getRepository(Demande::class)->findOneBy(['lieuOuZone' => 'Tenerife']);
        self::assertNotNull($creee);
        self::assertSame($this->lea->getId(), $creee->getConseiller()?->getId());
    }

    public function testUnConseillerConsulteModifieEtSupprimeSaDemande(): void
    {
        $demande = $this->creerDemande($this->lea, 'Lisbonne', false, '-1 day');
        $id = $demande->getId();
        $this->client->loginUser($this->lea);

        $this->client->request('GET', '/admin/demande/'.$id);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Lisbonne');

        $crawler = $this->client->request('GET', '/admin/demande/'.$id.'/edit');
        self::assertResponseIsSuccessful();
        // Le conseiller ne réattribue pas la demande depuis ce formulaire.
        self::assertCount(0, $crawler->filter('[name="Demande[conseiller]"]'));
        $this->client->submit($crawler->filter('form[name="Demande"]')->form([
            'Demande[lieuOuZone]' => 'Porto',
            'Demande[prioritaire]' => true,
        ]));
        self::assertResponseRedirects();
        $this->em->clear();
        $modifiee = $this->em->find(Demande::class, $id);
        self::assertSame('Porto', $modifiee?->getLieuOuZone());
        self::assertTrue($modifiee->isPrioritaire());
        self::assertSame($this->lea->getId(), $modifiee->getConseiller()?->getId());

        $crawler = $this->client->request('GET', '/admin/demande/'.$id);
        $jeton = $crawler->filter('#action-confirmation-form input[name="token"]')->attr('value');
        $this->client->request('POST', '/admin/demande/'.$id.'/delete', ['token' => $jeton]);
        self::assertResponseRedirects();
        $this->em->clear();
        self::assertNull($this->em->find(Demande::class, $id));
    }

    /** @return list<int> */
    private function idsAffiches(): array
    {
        return array_map('intval', $this->client->getCrawler()->filter('table.datagrid tbody tr[data-id]')->each(
            static fn ($ligne) => $ligne->attr('data-id'),
        ));
    }

    private function creerDemande(?Utilisateur $conseiller, string $lieu, bool $prioritaire, string $recue): Demande
    {
        $date = new \DateTimeImmutable($recue);
        $demande = (new Demande())
            ->setConseiller($conseiller)
            ->setLieuOuZone($lieu)
            ->setPrioritaire($prioritaire)
            ->setCreeLe($date)
            ->setModifieLe($date);
        $this->em->persist($demande);
        $this->em->flush();

        return $demande;
    }

    private function creerCompte(string $email, Role $role): Utilisateur
    {
        $utilisateur = (new Utilisateur())->setEmail($email)->setNom('Compte de test')->setRole($role);
        $this->em->persist($utilisateur);
        $this->em->flush();

        return $utilisateur;
    }
}
