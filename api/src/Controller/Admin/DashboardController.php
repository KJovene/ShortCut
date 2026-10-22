<?php

namespace App\Controller\Admin;

use App\Entity\Utilisateur;
use App\Enum\StatutDemande;
use App\Repository\DemandeRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;

/** Backoffice des conseillers et de l'administrateur (accès protégé par access_control sur /admin). */
#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    public function __construct(
        #[Autowire('%env(SITE_URL)%')] private readonly string $siteUrl,
        private readonly DemandeRepository $demandes,
    ) {
    }

    public function index(): Response
    {
        $conseiller = $this->conseillerConnecte();
        $compteurs = $this->demandes->compterParStatut($conseiller);

        return $this->render('admin/tableau_de_bord.html.twig', [
            'compteurs' => $compteurs,
            'prioritaires' => $this->demandes->compterPrioritaires($conseiller),
            'a_aiguiller' => $this->demandes->compterAAiguiller(),
            'a_traiter' => $this->demandes->aTraiter($conseiller),
            'statuts' => StatutDemande::cases(),
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            // Logo et favicon Détour, copiés du site dans public/admin (servis aussi par le proxy Vite).
            ->setTitle('<img class="bo-logo-marque" src="/admin/logo-detour.svg" alt=""><span class="bo-logo-texte"><strong>Détour</strong><small>Backoffice</small></span>')
            ->setFaviconPath('admin/favicon.svg')
            ->setLocales(['fr'])
            // La maquette n'a qu'un thème clair.
            ->disableDarkMode();
    }

    public function configureAssets(): Assets
    {
        // Sous /admin pour passer par le proxy Vite du site (localhost:5173), comme les pages du backoffice. La date
        // du fichier dans l'adresse : le navigateur recharge la feuille dès qu'elle change, sans vider son cache.
        $version = fn (string $fichier): string => $fichier.'?v='.(@filemtime($this->getParameter('kernel.project_dir').'/public/'.$fichier) ?: 0);

        return Assets::new()
            ->addCssFile($version('admin/backoffice.css'))
            ->addJsFile($version('admin/lieux.js'));
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-home');
        yield MenuItem::linkTo(DemandeCrudController::class, 'Demandes', 'fa fa-inbox');
        yield MenuItem::linkTo(ClientCrudController::class, 'Clients', 'fa fa-address-book');

        yield MenuItem::section('Catalogue');
        yield MenuItem::linkTo(DestinationCrudController::class, 'Destinations', 'fa fa-earth-europe');
        yield MenuItem::linkTo(LogementCrudController::class, 'Logements', 'fa fa-bed');
        yield MenuItem::linkTo(ActiviteCrudController::class, 'Activités', 'fa fa-person-hiking');
        yield MenuItem::linkTo(PrestataireCrudController::class, 'Prestataires', 'fa fa-handshake');

        yield MenuItem::section('Administration')->setPermission('ROLE_ADMIN');
        yield MenuItem::linkTo(PageDuSiteCrudController::class, 'Contenus du site', 'fa fa-pen-to-square')->setPermission('ROLE_ADMIN');
        yield MenuItem::linkTo(UtilisateurCrudController::class, 'Comptes', 'fa fa-users')->setPermission('ROLE_ADMIN');
        yield MenuItem::linkTo(JournalIACrudController::class, 'Journal IA', 'fa fa-list')->setPermission('ROLE_ADMIN');

        yield MenuItem::section();
        yield MenuItem::linkToUrl('Retour au site', 'fa fa-arrow-left', rtrim($this->siteUrl, '/').'/');
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        $menu = parent::configureUserMenu($user);

        return $user instanceof Utilisateur ? $menu->setName($user->getNom()) : $menu;
    }

    /** Un conseiller ne compte que ses demandes ; null pour l'administrateur, qui voit tout. */
    private function conseillerConnecte(): ?Utilisateur
    {
        $user = $this->getUser();

        return !$this->isGranted('ROLE_ADMIN') && $user instanceof Utilisateur ? $user : null;
    }
}
