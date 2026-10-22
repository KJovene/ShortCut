<?php

namespace App\Controller\Admin;

use App\Entity\Destination;
use App\Entity\PeriodeClimatique;
use App\Entity\Utilisateur;
use App\Enum\StatutDemande;
use App\Form\ClimatMensuelType;
use App\Repository\DestinationRepository;
use App\Repository\DemandeRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;
use Doctrine\ORM\EntityManagerInterface;

/** Backoffice des conseillers et de l'administrateur (accès protégé par access_control sur /admin). */
#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    public function __construct(
        #[Autowire('%env(SITE_URL)%')] private readonly string $siteUrl,
        private readonly DemandeRepository $demandes,
        private readonly DestinationRepository $destinations,
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

    #[AdminRoute('/climat', name: 'climat', options: ['methods' => ['GET']])]
    public function climat(): Response
    {
        return $this->render('admin/climat/index.html.twig', [
            'destinations' => $this->destinations->findBy([], ['ville' => 'ASC']),
        ]);
    }

    #[AdminRoute('/climat/{id}', name: 'climat_edit', options: ['methods' => ['GET', 'POST']])]
    public function modifierClimat(
        Destination $destination,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $periodes = [];
        foreach ($destination->getPeriodesClimatiques() as $periode) {
            if (null !== $periode->getDebut() && null !== $periode->getTemperatureMoyenne()) {
                $periodes[(int) $periode->getDebut()->format('n')] ??= $periode;
            }
        }

        $temperatures = [];
        for ($mois = 1; $mois <= 12; ++$mois) {
            $temperatures['mois'.$mois] = isset($periodes[$mois]) ? $periodes[$mois]->getTemperatureMoyenne() : null;
        }

        $form = $this->createForm(ClimatMensuelType::class, $temperatures);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $utilisateur = $this->getUser();
            $data = $form->getData();
            for ($mois = 1; $mois <= 12; ++$mois) {
                $temperature = $data['mois'.$mois] ?? null;
                if (null === $temperature) {
                    continue;
                }

                $periode = $periodes[$mois] ?? new PeriodeClimatique();
                if (null === $periode->getDestination()) {
                    $periode->setDestination($destination);
                }
                if (null === $periode->getDebut() || null === $periode->getFin()) {
                    $annee = (int) (new \DateTimeImmutable())->format('Y');
                    $debut = new \DateTimeImmutable(\sprintf('%d-%02d-01', $annee, $mois));
                    $periode
                        ->setDebut($debut)
                        ->setFin($debut->modify('last day of this month'));
                }
                $periode
                    ->setTemperatureMoyenne((float) $temperature)
                    ->setSaisiPar($utilisateur instanceof Utilisateur ? $utilisateur : null)
                    ->setModifieLe(new \DateTimeImmutable());
                $entityManager->persist($periode);
            }

            $entityManager->flush();
            $this->addFlash('success', 'Les températures mensuelles ont été enregistrées.');

            return $this->redirectToRoute('admin_climat_edit', ['id' => $destination->getId()]);
        }

        return $this->render('admin/climat/edit.html.twig', [
            'destination' => $destination,
            'form' => $form,
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
        // Sous /admin pour passer par le proxy Vite du site (localhost:5173), comme les pages du backoffice.
        return Assets::new()->addCssFile('admin/backoffice.css');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-home');
        yield MenuItem::linkTo(DemandeCrudController::class, 'Demandes', 'fa fa-inbox');
        yield MenuItem::linkTo(ClientCrudController::class, 'Clients', 'fa fa-address-book');

        yield MenuItem::section('Catalogue');
        yield MenuItem::linkTo(DestinationCrudController::class, 'Destinations', 'fa fa-earth-europe');
        yield MenuItem::linkToUrl('Températures', 'fa fa-temperature-half', $this->generateUrl('admin_climat'));
        yield MenuItem::linkTo(LogementCrudController::class, 'Logements', 'fa fa-bed');
        yield MenuItem::linkTo(ActiviteCrudController::class, 'Activités', 'fa fa-person-hiking');
        yield MenuItem::linkTo(PrestataireCrudController::class, 'Prestataires', 'fa fa-handshake');

        yield MenuItem::section('Administration')->setPermission('ROLE_ADMIN');
        yield MenuItem::linkTo(PageDuSiteCrudController::class, 'Contenus du site', 'fa fa-pen-to-square')->setPermission('ROLE_ADMIN');
        yield MenuItem::linkTo(TraductionCrudController::class, 'Traductions anglaises', 'fa fa-language')->setPermission('ROLE_ADMIN');
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
