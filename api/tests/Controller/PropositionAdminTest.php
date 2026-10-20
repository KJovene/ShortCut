<?php

namespace App\Tests\Controller;

use App\Entity\Demande;
use App\Entity\LigneDePrestation;
use App\Entity\Proposition;
use App\Entity\Utilisateur;
use App\Enum\Role;
use App\Enum\StatutDemande;
use App\Enum\StatutProposition;
use App\Enum\TypeLigne;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Le conseiller relit sur la fiche demande le brouillon préparé par le chatbot, puis l'envoie au client (#207).
 * Nécessite `make db-test`.
 */
final class PropositionAdminTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Utilisateur $lea;
    private Demande $demande;
    private Proposition $brouillon;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();

        $this->lea = $this->creerCompte('lea.test@shortcut.test');
        $this->demande = (new Demande())->setConseiller($this->lea)->setLieuOuZone('Lisbonne');
        $this->brouillon = (new Proposition())
            ->setProgramme([
                ['jour' => 1, 'date' => '2027-05-14', 'titre' => 'Arrivée à Lisbonne', 'description' => 'Installation à l\'hôtel.', 'activiteIds' => []],
                ['jour' => 2, 'date' => '2027-05-15', 'titre' => 'Départ', 'description' => 'Vol retour.', 'activiteIds' => []],
            ])
            ->setTotalCentimes(117400)
            ->setPourquoi('Lisbonne se visite très bien en fauteuil.');
        $ligne = (new LigneDePrestation())->setType(TypeLigne::Logement)->setLibelle('Hôtel Alfama Vista')->setCalcul('1 nuit × 110 €')->setPrixEstimeCentimes(11000);
        $this->brouillon->addLigne($ligne);
        $this->demande->addProposition($this->brouillon);
        foreach ([$this->demande, $this->brouillon, $ligne] as $entite) {
            $this->em->persist($entite);
        }
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testLeConseillerRelitPuisEnvoieLeBrouillon(): void
    {
        $this->client->loginUser($this->lea);
        $crawler = $this->client->request('GET', '/admin/demande/'.$this->demande->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Version 1');
        self::assertSelectorTextContains('body', 'Arrivée à Lisbonne');
        self::assertSelectorTextContains('body', '1 nuit × 110 €');

        $this->client->submit($crawler->selectButton('Envoyer la proposition au client')->form());
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'La version 1 de la proposition a été envoyée au client.');
        self::assertSelectorNotExists('form[action$="/envoyer"]');

        $this->em->clear();
        $proposition = $this->em->find(Proposition::class, $this->brouillon->getId());
        self::assertSame(StatutProposition::Envoyee, $proposition?->getStatut());
        self::assertSame(StatutDemande::PropositionEnvoyee, $proposition->getDemande()?->getStatut());
    }

    public function testUnAutreConseillerNEnvoiePasLaProposition(): void
    {
        $this->client->loginUser($this->creerCompte('hugo.test@shortcut.test'));
        $this->client->request('POST', '/admin/propositions/'.$this->brouillon->getId().'/envoyer', ['_token' => 'x']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testUnJetonInvalideEstRefuse(): void
    {
        $this->client->loginUser($this->lea);
        $this->client->request('POST', '/admin/propositions/'.$this->brouillon->getId().'/envoyer', ['_token' => 'faux']);

        self::assertResponseStatusCodeSame(403);
        $this->em->clear();
        self::assertSame(StatutProposition::Brouillon, $this->em->find(Proposition::class, $this->brouillon->getId())?->getStatut());
    }

    private function creerCompte(string $email): Utilisateur
    {
        $utilisateur = (new Utilisateur())->setEmail($email)->setNom('Compte de test')->setRole(Role::Conseiller);
        $this->em->persist($utilisateur);
        $this->em->flush();

        return $utilisateur;
    }
}
