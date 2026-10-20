<?php

namespace App\Tests\Controller;

use App\Entity\Conversation;
use App\Entity\Demande;
use App\Entity\Utilisateur;
use App\Enum\MotifTransmission;
use App\Enum\Role;
use App\Enum\StatutDemande;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Liste des demandes du backoffice (US01) : un conseiller ne voit que ses demandes, l'administrateur
 * les voit toutes et peut filtrer par conseiller ; les prioritaires d'abord. Aiguillage (US06) : la vue
 * « À aiguiller » est commune à tous et chaque demande s'y attribue en un clic ; la fiche montre la transmission
 * et la conversation, que le conseiller reprend en un clic. Nécessite `make db-test`.
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

    public function testTousLesConseillersVoientLesDemandesAAiguiller(): void
    {
        $saisie = $this->creerDemande(null, 'Bali', false, '-2 hours');
        $conseillerDemande = $this->creerDemandeDuChatbot('Islande', prioritaire: true, terminee: false);
        $recapitulatifConfirme = $this->creerDemandeDuChatbot('Rome', prioritaire: false, terminee: true);
        $enCours = $this->creerDemandeDuChatbot('Lisbonne', prioritaire: false, terminee: false);
        $perdue = $this->creerDemande(null, 'Oslo', false, '-1 hour')->setStatut(StatutDemande::Perdue);
        $deHugo = $this->creerDemande($this->hugo, 'Santorin', true, '-1 hour');
        $this->em->flush();

        $this->client->loginUser($this->lea);
        $this->client->request('GET', '/admin/demande', ['aiguiller' => 1]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Demandes à aiguiller');
        $ids = $this->idsAffiches();
        self::assertSame($conseillerDemande->getId(), $ids[0], 'Les prioritaires d\'abord.');
        foreach ([$saisie, $conseillerDemande, $recapitulatifConfirme] as $demande) {
            self::assertContains($demande->getId(), $ids);
        }
        foreach ([$enCours, $perdue, $deHugo] as $demande) {
            self::assertNotContains($demande->getId(), $ids);
        }
        self::assertSelectorTextContains('.bo-onglet[aria-current="page"]', 'À aiguiller');
    }

    public function testUnConseillerSAttribueUneDemandeEnUnClic(): void
    {
        $demande = $this->creerDemandeDuChatbot('Islande', prioritaire: true, terminee: false);
        $id = $demande->getId();

        $this->client->loginUser($this->lea);
        $crawler = $this->client->request('GET', '/admin/demande', ['aiguiller' => 1]);
        $url = $crawler->filter(\sprintf('form[action*="/admin/demandes/%d/attribuer"]', $id))->attr('action');
        self::assertNotNull($url, 'Bouton « M\'attribuer » sur la ligne.');

        $this->client->request('POST', $url);
        self::assertResponseRedirects('/admin/demande/'.$id);
        $this->em->clear();
        self::assertSame($this->lea->getId(), $this->em->find(Demande::class, $id)?->getConseiller()?->getId());

        // Attribuée : elle quitte la vue « À aiguiller » et n'y propose plus le bouton.
        $crawler = $this->client->request('GET', '/admin/demande/'.$id);
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('form[action*="/attribuer"]'));
    }

    public function testLAttributionRefuseUnJetonInvalide(): void
    {
        $demande = $this->creerDemande(null, 'Bali', false, '-1 hour');

        $this->client->loginUser($this->lea);
        $this->client->request('POST', '/admin/demandes/'.$demande->getId().'/attribuer?_token=faux');

        self::assertResponseStatusCodeSame(403);
        $this->em->clear();
        self::assertNull($this->em->find(Demande::class, $demande->getId())?->getConseiller());
    }

    public function testUneDemandeDejaAttribueeNeChangePasDeConseiller(): void
    {
        $demande = $this->creerDemande(null, 'Bali', false, '-1 hour');
        $id = $demande->getId();

        $this->client->loginUser($this->lea);
        $crawler = $this->client->request('GET', '/admin/demande/'.$id);
        $url = $crawler->filter('form[action*="/attribuer"]')->attr('action');

        // Hugo clique le premier.
        $this->em->find(Demande::class, $id)?->setConseiller($this->hugo);
        $this->em->flush();

        $this->client->request('POST', (string) $url);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'vient d\'être attribuée');
        $this->em->clear();
        self::assertSame($this->hugo->getId(), $this->em->find(Demande::class, $id)?->getConseiller()?->getId());
    }

    public function testLaFicheMontreLaTransmissionEtLaConversation(): void
    {
        $demande = $this->creerDemandeTransmise();

        $this->client->loginUser($this->lea);
        $crawler = $this->client->request('GET', '/admin/demande/'.$demande->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', MotifTransmission::DemandeDuClient->label());
        self::assertSelectorTextContains('body', 'Couple, Islande en janvier, veut un devis par téléphone.');
        self::assertSelectorTextContains('.conversation', 'Je préfère parler à quelqu\'un.');
        self::assertSelectorTextContains('body', 'le chatbot ne répond plus');
        // Le chatbot a déjà passé la main : il reste à s'attribuer la demande.
        self::assertCount(1, $crawler->filter('form[action*="/attribuer"]'));
        self::assertCount(0, $crawler->filter('form[action*="/reprendre"]'));
    }

    public function testUnConseillerReprendLaConversationEnUnClic(): void
    {
        $demande = $this->creerDemandeTransmise();
        $demande->getConversation()?->setRepriseParConseiller(false);
        $this->em->flush();
        $id = $demande->getId();

        $this->client->loginUser($this->lea);
        $crawler = $this->client->request('GET', '/admin/demande/'.$id);
        $url = $crawler->filter('form[action*="/reprendre"]')->attr('action');

        $this->client->request('POST', (string) $url);
        self::assertResponseRedirects('/admin/demande/'.$id);

        $this->em->clear();
        $reprise = $this->em->find(Demande::class, $id);
        self::assertSame($this->lea->getId(), $reprise?->getConseiller()?->getId(), 'Attribuée au passage.');
        self::assertTrue($reprise->getConversation()?->isRepriseParConseiller());
        self::assertNotNull($reprise->getConversation()->getFinLe());

        $crawler = $this->client->request('GET', '/admin/demande/'.$id);
        self::assertCount(0, $crawler->filter('form[action*="/reprendre"]'));
        self::assertSelectorTextContains('.conversation', 'Je préfère parler à quelqu\'un.');
    }

    public function testSAttribuerLaDemandeReprendLaConversation(): void
    {
        $demande = $this->creerDemandeTransmise();
        $demande->getConversation()?->setRepriseParConseiller(false);
        $this->em->flush();
        $id = $demande->getId();

        $this->client->loginUser($this->lea);
        $crawler = $this->client->request('GET', '/admin/demande/'.$id);
        $url = $crawler->filter('form[action*="/attribuer"]')->attr('action');

        $this->client->request('POST', (string) $url);
        self::assertResponseRedirects('/admin/demande/'.$id);

        $this->em->clear();
        $priseEnCharge = $this->em->find(Demande::class, $id);
        self::assertSame($this->lea->getId(), $priseEnCharge?->getConseiller()?->getId());
        self::assertTrue($priseEnCharge->getConversation()?->isRepriseParConseiller(), 'Le chatbot ne répond plus au client.');
        self::assertNotNull($priseEnCharge->getConversation()->getFinLe());

        // Plus rien à reprendre ni à s'attribuer sur la fiche.
        $crawler = $this->client->request('GET', '/admin/demande/'.$id);
        self::assertCount(0, $crawler->filter('form[action*="/reprendre"]'));
        self::assertCount(0, $crawler->filter('form[action*="/attribuer"]'));
    }

    public function testUnConseillerNeReprendPasLaConversationDUnAutre(): void
    {
        $demande = $this->creerDemandeTransmise();
        $demande->getConversation()?->setRepriseParConseiller(false);
        $id = $demande->getId();

        $this->client->loginUser($this->lea);
        $crawler = $this->client->request('GET', '/admin/demande/'.$id);
        $url = $crawler->filter('form[action*="/reprendre"]')->attr('action');

        // Hugo s'attribue la demande avant que Léa ne clique.
        $this->em->find(Demande::class, $id)?->setConseiller($this->hugo);
        $this->em->flush();

        $this->client->request('POST', (string) $url);
        self::assertResponseRedirects();
        $this->em->clear();
        $demande = $this->em->find(Demande::class, $id);
        self::assertSame($this->hugo->getId(), $demande?->getConseiller()?->getId());
        self::assertFalse($demande->getConversation()?->isRepriseParConseiller());
    }

    /** Demande du chatbot transmise au conseiller, sans conseiller attribué. */
    private function creerDemandeTransmise(): Demande
    {
        $demande = $this->creerDemandeDuChatbot('Islande', prioritaire: false, terminee: false);
        $demande->transmettre(MotifTransmission::DemandeDuClient, 'Couple, Islande en janvier, veut un devis par téléphone.');
        $demande->getConversation()
            ?->ajouterMessage('client', 'Je préfère parler à quelqu\'un.')
            ->ajouterMessage('assistant', 'Un conseiller va vous recontacter.')
            ->setRepriseParConseiller(true);
        $this->em->flush();

        return $demande;
    }

    private function creerDemandeDuChatbot(string $lieu, bool $prioritaire, bool $terminee): Demande
    {
        $demande = $this->creerDemande(null, $lieu, $prioritaire, '-1 hour');
        $conversation = (new Conversation())->setFinLe($terminee ? new \DateTimeImmutable() : null);
        $demande->setConversation($conversation);
        $this->em->persist($conversation);
        $this->em->flush();

        return $demande;
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
