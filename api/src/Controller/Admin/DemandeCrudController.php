<?php

namespace App\Controller\Admin;

use App\Confirmation\ConfirmationImpossible;
use App\Confirmation\ConfirmationService;
use App\Controller\AiguillageController;
use App\Entity\Demande;
use App\Entity\Prestation;
use App\Entity\Proposition;
use App\Entity\Utilisateur;
use App\Enum\NiveauBudget;
use App\Enum\Role;
use App\Enum\StatutDemande;
use App\Enum\StatutProposition;
use App\Enum\TypeDeVoyage;
use App\Form\TexteSuggereType;
use App\Repository\DemandeRepository;
use App\Security\Voter\DemandeVoter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Form\ClickableInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\PositiveOrZero;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Demandes des clients, point d'entrée du conseiller chaque matin (US01) :
 * - conseiller : uniquement les demandes qui lui sont attribuées ;
 * - administrateur : toutes les demandes, avec un filtre par conseiller.
 * Les demandes prioritaires apparaissent en premier, puis les plus récentes.
 * La vue « À aiguiller » (?aiguiller=1) liste pour tous les demandes arrivées sans conseiller ;
 * chacune s'attribue en un clic avec « M'attribuer » (US06, AiguillageController). La fiche montre pourquoi le
 * chatbot a transmis la demande et la conversation ; le conseiller recontacte le client hors du site.
 * L'accès à une fiche (consultation, modification, suppression) est vérifié par DemandeVoter.
 * Depuis la fiche, « Confirmer la proposition » ouvre l'écran de confirmation (#209, ConfirmationService).
 *
 * @extends AbstractCrudController<Demande>
 */
#[IsGranted('ROLE_CONSEILLER')]
final class DemandeCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly DemandeRepository $demandes,
        private readonly ConfirmationService $confirmations,
        private readonly FormFactoryInterface $formulaires,
        #[Autowire('%env(SITE_URL)%')] private readonly string $siteUrl,
        #[Target('demande')] private readonly WorkflowInterface $workflowDemande,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Demande::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Demande')
            ->setEntityLabelInPlural('Demandes')
            ->setPageTitle(Crud::PAGE_INDEX, match (true) {
                $this->enVueAAiguiller() => 'Demandes à aiguiller',
                $this->estAdministrateur() => 'Toutes les demandes',
                default => 'Mes demandes',
            })
            ->setDefaultSort(['prioritaire' => 'DESC', 'creeLe' => 'DESC'])
            ->setSearchFields(['lieuOuZone', 'villeDepart', 'client.nom', 'client.prenom', 'client.email'])
            // Onglets par statut au-dessus de la liste.
            ->overrideTemplate('crud/index', 'admin/demande/index.html.twig')
            ->addFormTheme('admin/form/texte_suggere.html.twig');
    }

    /** Compteurs des onglets, sur le même périmètre que la liste (ses demandes pour un conseiller). */
    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        if (Crud::PAGE_INDEX === $responseParameters->get('pageName')) {
            $user = $this->getUser();
            $responseParameters->set('compteurs', $this->demandes->compterParStatut(
                !$this->estAdministrateur() && $user instanceof Utilisateur ? $user : null,
            ));
            $responseParameters->set('statuts', StatutDemande::cases());
            $responseParameters->set('a_aiguiller', $this->demandes->compterAAiguiller());
            $responseParameters->set('vue_a_aiguiller', $this->enVueAAiguiller());
        }

        return $responseParameters;
    }

    public function configureActions(Actions $actions): Actions
    {
        // Seulement quand le client a demandé la confirmation de sa proposition (#210), ou pour la consulter.
        $confirmer = Action::new('confirmerProposition', 'Confirmer la proposition', 'fa fa-file-signature')
            ->linkToCrudAction('confirmation')
            ->displayIf(fn (Demande $demande): bool => $this->confirmations->confirmationOuverte($demande))
            ->asPrimaryAction();
        // Le statut ne se choisit pas dans le formulaire : il suit le workflow « demande ». Seule la perte se décide.
        $perdre = Action::new('perdre', 'Marquer comme perdue', 'fa fa-ban')
            ->linkToUrl(fn (Demande $d): string => $this->generateUrl('admin_demande_perdre', [
                'id' => $d->getId(),
                '_token' => $this->container->get('security.csrf.token_manager')->getToken('perdre_'.$d->getId())->getValue(),
            ]))
            ->renderAsForm()
            ->askConfirmation('Marquer cette demande comme perdue ? Le client ne pourra plus accepter sa proposition.', 'Marquer comme perdue')
            ->displayIf(fn (Demande $d): bool => $this->workflowDemande->can($d, 'perdre'));
        // US13 : le responsable ouvre en un clic le journal des appels IA de la demande.
        $journal = Action::new('journalIA', 'Journal IA', 'fa fa-list')
            ->linkToUrl(fn (Demande $d): string => $this->container->get(AdminUrlGeneratorInterface::class)
                ->setController(JournalIACrudController::class)
                ->setAction(Action::INDEX)
                ->set('filters', ['demande' => ['comparison' => '=', 'value' => $d->getId()]])
                ->generateUrl());
        // En POST, jeton CSRF dans l'URL : le formulaire généré par EasyAdmin n'a pas de corps.
        $attribuer = Action::new('attribuer', 'M\'attribuer', 'fa fa-hand')
            ->linkToUrl(fn (Demande $d): string => $this->generateUrl('admin_demande_attribuer', [
                'id' => $d->getId(),
                '_token' => $this->container->get('security.csrf.token_manager')->getToken(AiguillageController::JETON)->getValue(),
            ]))
            ->renderAsForm()
            ->displayIf(static fn (Demande $d): bool => null === $d->getConseiller());

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $attribuer)
            ->add(Crud::PAGE_DETAIL, $attribuer)
            ->add(Crud::PAGE_DETAIL, $confirmer)
            ->add(Crud::PAGE_DETAIL, $journal)
            ->add(Crud::PAGE_DETAIL, $perdre)
            ->setPermission('perdre', DemandeVoter::MODIFIER)
            ->setPermission('journalIA', 'ROLE_ADMIN')
            ->setPermission('attribuer', DemandeVoter::ATTRIBUER)
            ->setPermission('confirmerProposition', DemandeVoter::MODIFIER)
            // Vérifié sur chaque fiche : un conseiller qui saisit l'URL d'une demande d'un autre reçoit un 403.
            ->setPermission(Action::DETAIL, DemandeVoter::VOIR)
            ->setPermission(Action::EDIT, DemandeVoter::MODIFIER)
            ->setPermission(Action::DELETE, DemandeVoter::MODIFIER)
            ->setPermission(Action::BATCH_DELETE, 'ROLE_ADMIN');
    }

    public function configureFilters(Filters $filters): Filters
    {
        if ($this->estAdministrateur()) {
            $filters->add(EntityFilter::new('conseiller', 'Conseiller')->setFormTypeOption(
                'value_type_options.query_builder',
                static fn (EntityRepository $repo) => $repo->createQueryBuilder('u')
                    ->where('u.role IN (:roles)')
                    ->setParameter('roles', [Role::Conseiller, Role::Administrateur])
                    ->orderBy('u.nom', 'ASC'),
            ));
        }

        return $filters
            ->add(BooleanFilter::new('prioritaire', 'Prioritaire'))
            ->add(ChoiceFilter::new('statut', 'Statut')->setChoices(self::choix(StatutDemande::cases(), valeurs: true)))
            ->add(ChoiceFilter::new('typeDeVoyage', 'Type de voyage')->setChoices(self::choix(TypeDeVoyage::cases(), valeurs: true)));
    }

    /**
     * Restreint la liste aux demandes du conseiller connecté ; l'administrateur voit tout.
     * La vue « À aiguiller » est la même pour tous : les demandes arrivées sans conseiller.
     */
    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        if ($this->enVueAAiguiller()) {
            $this->demandes->restreindreAAiguiller($qb, 'entity');
        } elseif (!$this->estAdministrateur()) {
            $qb->andWhere('entity.conseiller = :conseillerConnecte')
                ->setParameter('conseillerConnecte', $this->getUser());
        }

        return $qb;
    }

    /** Une demande créée par un conseiller lui est attribuée d'office. */
    public function createEntity(string $entityFqcn): Demande
    {
        $demande = new Demande();
        if (!$this->estAdministrateur() && ($user = $this->getUser()) instanceof Utilisateur) {
            $demande->setConseiller($user);
        }

        return $demande;
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $entityInstance->setModifieLe(new \DateTimeImmutable());

        parent::updateEntity($entityManager, $entityInstance);
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addFieldset('Suivi');
        yield IdField::new('id', 'N°')->hideOnForm();
        yield BooleanField::new('prioritaire', 'Prioritaire')
            ->renderAsSwitch(false)
            ->setTemplatePath('admin/champ/prioritaire.html.twig')
            ->setHelp('« Parler à un conseiller » ou période sans données dans le catalogue.');
        yield ChoiceField::new('statut', 'Statut')
            ->setChoices(self::choix(StatutDemande::cases()))
            ->setFormTypeOption('choice_value', static fn (?StatutDemande $s) => $s?->value)
            ->setTemplatePath('admin/champ/statut_demande.html.twig')
            // Les transitions du workflow « demande » le font avancer (envoi, demande du client, confirmation…).
            ->hideOnForm();
        yield AssociationField::new('client', 'Client')
            ->setQueryBuilder(static fn (QueryBuilder $qb) => $qb
                ->andWhere('entity.role = :client')
                ->setParameter('client', Role::Client)
                ->orderBy('entity.nom', 'ASC'));
        // Seul l'administrateur choisit le conseiller dans ce formulaire ; un conseiller voit qui suit la demande.
        $conseiller = AssociationField::new('conseiller', 'Conseiller')
            ->setQueryBuilder(static fn (QueryBuilder $qb) => $qb
                ->andWhere('entity.role IN (:roles)')
                ->setParameter('roles', [Role::Conseiller, Role::Administrateur])
                ->orderBy('entity.nom', 'ASC'));
        yield $this->estAdministrateur() ? $conseiller : $conseiller->hideOnForm();
        yield DateTimeField::new('creeLe', 'Reçue le')->hideOnForm();
        yield DateTimeField::new('modifieLe', 'Modifiée le')->onlyOnDetail();

        yield FormField::addFieldset('Voyage');
        yield ChoiceField::new('typeDeVoyage', 'Type de voyage')
            ->setChoices(self::choix(TypeDeVoyage::cases()))
            ->setFormTypeOption('choice_value', static fn (?TypeDeVoyage $t) => $t?->value);
        yield TextField::new('lieuOuZone', 'Lieu ou zone');
        // Suggestions : les villes de départ du catalogue, pour que le chiffrage retrouve les vols.
        yield TextField::new('villeDepart', 'Ville de départ')
            ->setFormType(TexteSuggereType::class)
            ->setFormTypeOption('suggestions', 'departs')
            ->hideOnIndex();
        yield DateField::new('dateDebut', 'Départ');
        yield DateField::new('dateFin', 'Retour')->hideOnIndex();
        yield BooleanField::new('datesFlexibles', 'Dates flexibles')->renderAsSwitch(false)->hideOnIndex();
        yield IntegerField::new('nombreDeJours', 'Nombre de jours')->hideOnIndex();
        yield IntegerField::new('temperatureMin', 'Température min. (°C)')->hideOnIndex();
        yield IntegerField::new('temperatureMax', 'Température max. (°C)')->hideOnIndex();

        yield FormField::addFieldset('Voyageurs et budget');
        yield IntegerField::new('adultes', 'Adultes')->hideOnIndex();
        yield IntegerField::new('enfants', 'Enfants')->hideOnIndex();
        yield ArrayField::new('agesEnfants', 'Âges des enfants')->hideOnIndex();
        yield MoneyField::new('budgetCentimes', 'Budget')->setCurrency('EUR')->setStoredAsCents();
        yield BooleanField::new('budgetParPersonne', 'Budget par personne')->renderAsSwitch(false)->hideOnIndex();
        yield BooleanField::new('transportInclus', 'Transport inclus')->renderAsSwitch(false)->hideOnIndex();
        yield ArrayField::new('envies', 'Envies')->hideOnIndex();
        yield ArrayField::new('contraintes', 'Contraintes')->hideOnIndex();

        // US06 : pourquoi le chatbot a passé la main, et l'échange à reprendre.
        yield FormField::addFieldset('Conversation avec le chatbot')->onlyOnDetail();
        yield Field::new('motifTransmission', 'Transmise au conseiller')
            ->onlyOnDetail()
            ->setTemplatePath('admin/champ/transmission.html.twig');
        yield Field::new('conversation', 'Échanges')
            ->onlyOnDetail()
            ->setTemplatePath('admin/champ/conversation.html.twig');
        // Brouillon préparé par le chatbot : le conseiller le relit puis l'envoie au client (#207).
        yield FormField::addFieldset('Proposition')->onlyOnDetail();
        yield Field::new('propositions', 'Dernière version')
            ->setTemplatePath('admin/champ/proposition.html.twig')
            ->onlyOnDetail();
    }

    /**
     * Écran de confirmation (#209) : prix confirmés de chaque ligne et frais de dossier, écarts avec l'estimation,
     * puis confirmation. Une proposition confirmée s'affiche en lecture seule.
     */
    #[AdminRoute('/{id}/confirmation', name: 'confirmation', options: ['methods' => ['GET', 'POST']])]
    public function confirmation(#[MapEntity(id: 'id')] Demande $demande, Request $request): Response
    {
        $this->denyAccessUnlessGranted(DemandeVoter::MODIFIER, $demande);
        $estimation = $this->confirmations->estimation($demande)
            ?? throw $this->createNotFoundException('Cette demande n\'a pas encore de proposition envoyée au client.');
        try {
            $version = $this->confirmations->versionDeConfirmation($demande, $estimation);
        } catch (ConfirmationImpossible $e) {
            $this->addFlash('warning', $e->getMessage());

            return $this->redirectVersLaDemande($demande);
        }

        $formulaire = null;
        if (StatutProposition::Brouillon === $version->getStatut()) {
            $formulaire = $this->formulaireDesPrix($version);
            $formulaire->handleRequest($request);
            if ($formulaire->isSubmitted() && $formulaire->isValid()) {
                $prix = [];
                foreach ($version->getLignes() as $ligne) {
                    $prix[(int) $ligne->getId()] = $formulaire->get('ligne_'.$ligne->getId())->getData();
                }
                $this->confirmations->saisir($version, $prix, (int) $formulaire->get('fraisDeDossier')->getData());

                $bouton = $formulaire->get('confirmer');
                if (!$bouton instanceof ClickableInterface || !$bouton->isClicked()) {
                    $this->addFlash('success', 'Prix enregistrés.');
                } elseif (($conseiller = $this->getUser()) instanceof Utilisateur) {
                    try {
                        $this->confirmations->confirmer($version, $estimation, $conseiller);
                        $this->addFlash('success', \sprintf('Proposition confirmée : la version %d fait foi.', $version->getVersion()));
                    } catch (ConfirmationImpossible $e) {
                        $this->addFlash('danger', $e->getMessage());
                    }
                }

                return $this->redirectVersLaConfirmation($demande);
            }
        }

        $remplacements = [];
        foreach ($version->getLignes() as $ligne) {
            foreach ($this->confirmations->logementsDeRemplacement($ligne) as $logement) {
                try {
                    $estime = $this->confirmations->estimerLogement($logement, $ligne, $demande);
                    $remplacements[(int) $ligne->getId()][] = ['logement' => $logement, 'centimes' => $estime['centimes'], 'raison' => null];
                } catch (ConfirmationImpossible $e) {
                    $remplacements[(int) $ligne->getId()][] = ['logement' => $logement, 'centimes' => null, 'raison' => $e->getMessage()];
                }
            }
        }

        return $this->render('admin/demande/confirmation.html.twig', [
            'demande' => $demande,
            'estimation' => $estimation,
            'version' => $version,
            'lignes' => ConfirmationService::lignesTriees($version),
            'comparaison' => $this->confirmations->comparer($version, $estimation),
            'marge' => $this->confirmations->margePourcentage(),
            'niveaux' => array_combine(
                array_map(static fn (NiveauBudget $n): string => $n->value, NiveauBudget::cases()),
                array_map(static fn (NiveauBudget $n): string => $n->label(), NiveauBudget::cases()),
            ),
            'formulaire' => $formulaire?->createView(),
            'remplacements' => $remplacements,
            // Page « Ma proposition » : le lien de l'estimation mène aussi à la version confirmée.
            'lienClient' => \sprintf('%s/proposition/%s', rtrim($this->siteUrl, '/'), $version->getJetonPublic()),
        ]);
    }

    /** Retire une activité de la version en préparation. */
    #[AdminRoute('/{id}/confirmation/retirer', name: 'confirmation_retirer', options: ['methods' => ['POST']])]
    public function retirerUneActivite(#[MapEntity(id: 'id')] Demande $demande, Request $request): Response
    {
        $version = $this->brouillonAModifier($demande, $request, 'retirer');
        try {
            $this->confirmations->retirer($version, $request->request->getInt('ligne'));
            $this->addFlash('success', 'Activité retirée de la proposition.');
        } catch (ConfirmationImpossible $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectVersLaConfirmation($demande);
    }

    /** Remplace un logement de la version en préparation par un autre de la même destination. */
    #[AdminRoute('/{id}/confirmation/remplacer', name: 'confirmation_remplacer', options: ['methods' => ['POST']])]
    public function remplacerUnLogement(#[MapEntity(id: 'id')] Demande $demande, Request $request, EntityManagerInterface $em): Response
    {
        $version = $this->brouillonAModifier($demande, $request, 'remplacer');
        $logement = $em->find(Prestation::class, $request->request->getInt('logement'));
        try {
            if (null === $logement) {
                throw new ConfirmationImpossible('Choisissez le logement de remplacement.');
            }
            $ligne = $this->confirmations->remplacer($version, $request->request->getInt('ligne'), $logement);
            $this->addFlash('success', \sprintf('Logement remplacé par %s : saisissez son prix confirmé.', $ligne->getLibelle()));
        } catch (ConfirmationImpossible $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectVersLaConfirmation($demande);
    }

    /**
     * Un champ par ligne (prix confirmé, total de la ligne) et les frais de dossier. Les prix sont obligatoires
     * pour confirmer, pas pour enregistrer : le bouton « Enregistrer » ne déclenche pas la validation du navigateur.
     *
     * @return FormInterface<mixed>
     */
    private function formulaireDesPrix(Proposition $version): FormInterface
    {
        [$min, $max] = $this->confirmations->bornesDesFrais();
        $prix = ['currency' => 'EUR', 'divisor' => 100, 'input' => 'integer', 'required' => true, 'label' => false];

        $constructeur = $this->formulaires->createNamedBuilder('confirmation', FormType::class);
        foreach ($version->getLignes() as $ligne) {
            $constructeur->add('ligne_'.$ligne->getId(), MoneyType::class, $prix + [
                'data' => $ligne->getPrixConfirmeCentimes(),
                'constraints' => [new PositiveOrZero()],
                'attr' => ['aria-label' => 'Prix confirmé : '.$ligne->getLibelle()],
            ]);
        }

        return $constructeur
            ->add('fraisDeDossier', MoneyType::class, $prix + [
                'data' => $version->getFraisDeDossierCentimes(),
                'constraints' => [new NotNull(), new Range(min: $min, max: $max, notInRangeMessage: \sprintf(
                    'Les frais de dossier vont de %d à %d €.', intdiv($min, 100), intdiv($max, 100),
                ))],
                'attr' => ['aria-label' => 'Frais de dossier confirmés'],
            ])
            ->add('enregistrer', SubmitType::class, ['label' => 'Enregistrer', 'attr' => ['formnovalidate' => true, 'class' => 'btn btn-secondary']])
            ->add('confirmer', SubmitType::class, ['label' => 'Confirmer la proposition', 'attr' => ['class' => 'btn btn-primary']])
            ->getForm();
    }

    /** Le brouillon de la demande, après vérification du droit de modifier et du jeton CSRF. */
    private function brouillonAModifier(Demande $demande, Request $request, string $operation): Proposition
    {
        $this->denyAccessUnlessGranted(DemandeVoter::MODIFIER, $demande);
        if (!$this->isCsrfTokenValid('confirmation_'.$operation.'_'.$demande->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide : rechargez la page.');
        }
        $estimation = $this->confirmations->estimation($demande) ?? throw $this->createNotFoundException();
        if (!$this->confirmations->confirmationOuverte($demande)) {
            throw $this->createAccessDeniedException('Le client n\'a pas demandé la confirmation de sa proposition.');
        }

        return $this->confirmations->versionDeConfirmation($demande, $estimation);
    }

    /** La demande est perdue (le client renonce, ne répond plus) : transition « perdre » du workflow. */
    #[AdminRoute('/{id}/perdre', name: 'perdre', options: ['methods' => ['POST']])]
    public function perdre(#[MapEntity(id: 'id')] Demande $demande, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(DemandeVoter::MODIFIER, $demande);
        if (!$this->isCsrfTokenValid('perdre_'.$demande->getId(), $request->query->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide : rechargez la page.');
        }
        if ($this->workflowDemande->can($demande, 'perdre')) {
            $this->workflowDemande->apply($demande, 'perdre');
            $demande->setModifieLe(new \DateTimeImmutable());
            $em->flush();
            $this->addFlash('success', 'Demande marquée comme perdue.');
        } else {
            $this->addFlash('warning', \sprintf('La demande est « %s » : elle ne peut plus être perdue.', $demande->getStatut()->label()));
        }

        return $this->redirectVersLaDemande($demande);
    }

    private function redirectVersLaDemande(Demande $demande): Response
    {
        return $this->redirect($this->container->get(AdminUrlGeneratorInterface::class)
            ->setController(self::class)->setAction(Action::DETAIL)->setEntityId($demande->getId())->generateUrl());
    }

    private function redirectVersLaConfirmation(Demande $demande): Response
    {
        return $this->redirectToRoute('admin_demande_confirmation', ['id' => $demande->getId()]);
    }

    private function estAdministrateur(): bool
    {
        return $this->isGranted('ROLE_ADMIN');
    }

    private function enVueAAiguiller(): bool
    {
        return (bool) $this->container->get('request_stack')->getCurrentRequest()?->query->getBoolean('aiguiller');
    }

    /**
     * Libellés des cas d'une énumération, pour les listes déroulantes et les filtres.
     *
     * @param list<StatutDemande|TypeDeVoyage> $cas
     *
     * @return array<string, StatutDemande|TypeDeVoyage|string>
     */
    private static function choix(array $cas, bool $valeurs = false): array
    {
        $choix = [];
        foreach ($cas as $c) {
            $choix[$c->label()] = $valeurs ? $c->value : $c;
        }

        return $choix;
    }
}
