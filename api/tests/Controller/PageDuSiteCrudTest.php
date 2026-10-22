<?php

namespace App\Tests\Controller;

use App\Entity\PageDuSite;
use App\Entity\Utilisateur;
use App\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\DomCrawler\Field\FileFormField;

/**
 * Contenus du site dans le backoffice (#217) : l'administrateur modifie textes et photos, le site lit
 * la modification au rechargement. Nécessite `make db-test`.
 */
final class PageDuSiteCrudTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private PageDuSite $page;
    /** @var list<string> */
    private array $fichiersEnvoyes = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();

        $this->page = (new PageDuSite())
            ->setSlug('page-test')
            ->setTitre('Page de test')
            ->setContenu('<p>Ancien texte</p>')
            ->setPubliee(true);
        $this->em->persist($this->page);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        foreach ($this->fichiersEnvoyes as $fichier) {
            @unlink(static::getContainer()->getParameter('kernel.project_dir').'/public/uploads/pages/'.$fichier);
        }
        parent::tearDown();
    }

    public function testUneModificationEnregistreeEstLueParLeSite(): void
    {
        $this->client->loginUser($this->compte(Role::Administrateur));
        $crawler = $this->client->request('GET', '/admin/page-du-site/'.$this->page->getId().'/edit');
        self::assertResponseIsSuccessful();
        // L'adresse d'une page existante ne se change pas : le site la demande par son slug.
        self::assertSelectorExists('#PageDuSite_slug[disabled]');

        $formulaire = $crawler->filter('form[name="PageDuSite"]')->form([
            'PageDuSite[titre]' => 'Nouveau titre',
            'PageDuSite[contenu]' => '<p>Nouveau texte</p><h2>Intertitre</h2>',
            'PageDuSite[seoTitre]' => 'Titre Google',
        ]);
        $photos = $formulaire['PageDuSite[photos][file][0]'];
        self::assertInstanceOf(FileFormField::class, $photos);
        $photos->upload($this->photo());
        $this->client->submit($formulaire);
        self::assertResponseRedirects();

        $this->client->request('GET', '/api/pages/page-test', server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();
        $page = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('Nouveau titre', $page['titre']);
        self::assertSame('<p>Nouveau texte</p><h2>Intertitre</h2>', $page['contenu']);
        self::assertSame('Titre Google', $page['seoTitre']);
        self::assertCount(1, $page['photos']);
        self::assertMatchesRegularExpression('#^/uploads/pages/sejour-[\w]+\.png$#', $page['photos'][0]);
        $this->fichiersEnvoyes[] = basename($page['photos'][0]);
    }

    public function testLAccueilALeTitreDesVoyagesFroidsModifiable(): void
    {
        $accueil = $this->em->getRepository(PageDuSite::class)->findOneBy(['slug' => 'accueil'])
            ?? (new PageDuSite())->setSlug('accueil')->setTitre('Où irez-vous *chercher le soleil* ?')->setPubliee(true);
        $this->em->persist($accueil);
        $this->em->flush();

        $this->client->loginUser($this->compte(Role::Administrateur));
        $crawler = $this->client->request('GET', '/admin/page-du-site/'.$accueil->getId().'/edit');
        self::assertResponseIsSuccessful();

        $this->client->submit($crawler->filter('form[name="PageDuSite"]')->form([
            'PageDuSite[titreFroid]' => 'Cap sur *les aurores* !',
        ]));
        self::assertResponseRedirects();

        $this->client->request('GET', '/api/pages/accueil', server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertSame('Cap sur *les aurores* !', json_decode((string) $this->client->getResponse()->getContent(), true)['titreFroid']);

        // Les autres pages n'ont qu'un titre.
        $this->client->request('GET', '/admin/page-du-site/'.$this->page->getId().'/edit');
        self::assertSelectorNotExists('#PageDuSite_titreFroid');
    }

    public function testOnRemplaceUnePhotoEnUnSeulEnregistrement(): void
    {
        // Deux photos à part, pour ne pas toucher aux photos du jeu de démo.
        $dossier = static::getContainer()->getParameter('kernel.project_dir').'/public/uploads/pages/';
        foreach (['essai-a.png', 'essai-b.png'] as $fichier) {
            copy($this->photo(), $dossier.$fichier);
            $this->fichiersEnvoyes[] = $fichier;
        }
        $this->page->setPhotos(['essai-a.png', 'essai-b.png']);
        $this->em->flush();

        $this->client->loginUser($this->compte(Role::Administrateur));
        $crawler = $this->client->request('GET', '/admin/page-du-site/'.$this->page->getId().'/edit');
        $formulaire = $crawler->filter('form[name="PageDuSite"]')->form();
        // Ce que fait la croix d'une photo dans le navigateur.
        $formulaire['PageDuSite[photos][deleted_files]'] = '["essai-a.png"]';
        $photos = $formulaire['PageDuSite[photos][file][0]'];
        self::assertInstanceOf(FileFormField::class, $photos);
        $photos->upload($this->photo());
        $this->client->submit($formulaire);
        self::assertResponseRedirects();

        $this->em->clear();
        $page = $this->em->getRepository(PageDuSite::class)->find($this->page->getId());
        self::assertNotNull($page);
        self::assertCount(2, $page->getPhotos());
        self::assertSame('essai-b.png', $page->getPhotos()[0]);
        self::assertMatchesRegularExpression('#^sejour-\w+\.png$#', $page->getPhotos()[1]);
        $this->fichiersEnvoyes[] = $page->getPhotos()[1];
        self::assertFileExists($dossier.$page->getPhotos()[1]);
    }

    public function testUnePageDepublieeDisparaitDuSite(): void
    {
        $this->client->loginUser($this->compte(Role::Administrateur));
        $crawler = $this->client->request('GET', '/admin/page-du-site/'.$this->page->getId().'/edit');
        $formulaire = $crawler->filter('form[name="PageDuSite"]')->form();
        $publiee = $formulaire['PageDuSite[publiee]'];
        self::assertInstanceOf(ChoiceFormField::class, $publiee);
        $publiee->untick();
        $this->client->submit($formulaire);
        self::assertResponseRedirects();

        $this->client->request('GET', '/api/pages/page-test', server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(404);
    }

    public function testSeulLAdministrateurModifieLesContenus(): void
    {
        $this->client->loginUser($this->compte(Role::Conseiller));

        $this->client->request('GET', '/admin/page-du-site');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/admin/page-du-site/'.$this->page->getId().'/edit');
        self::assertResponseStatusCodeSame(403);

        // Le menu du conseiller ne propose pas le module.
        $this->client->request('GET', '/admin');
        self::assertSelectorTextNotContains('nav', 'Contenus du site');
    }

    public function testLeMenuDeLAdministrateurOuvreLesContenus(): void
    {
        $this->client->loginUser($this->compte(Role::Administrateur));
        $crawler = $this->client->request('GET', '/admin');

        $lien = $crawler->selectLink('Contenus du site');
        self::assertCount(1, $lien);
        $this->client->click($lien->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table.datagrid', 'Page de test');
    }

    private function compte(Role $role): Utilisateur
    {
        $compte = (new Utilisateur())->setEmail($role->value.'.contenus@shortcut.test')->setNom('Compte test')->setRole($role);
        $this->em->persist($compte);
        $this->em->flush();

        return $compte;
    }

    /** Photo de 1 × 1 pixel nommée « sejour.png », comme l'envoie le navigateur. */
    private function photo(): string
    {
        $dossier = sys_get_temp_dir().'/'.uniqid('contenus', true);
        mkdir($dossier);
        $chemin = $dossier.'/sejour.png';
        file_put_contents($chemin, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true));

        return $chemin;
    }
}
