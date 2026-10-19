<?php

namespace App\Controller\Admin;

use App\Controller\AiguillageController;
use App\Entity\Demande;
use App\Entity\Utilisateur;
use App\Enum\Role;
use App\Enum\StatutDemande;
use App\Enum\TypeDeVoyage;
use App\Form\TexteSuggereType;
use App\Repository\DemandeRepository;
use App\Security\Voter\DemandeVoter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Demandes des clients, point d'entrée du conseiller chaque matin (US01) :
 * - conseiller : uniquement les demandes qui lui sont attribuées ;
 * - administrateur : toutes les demandes, avec un filtre par conseiller.
 * Les demandes prioritaires apparaissent en premier, puis les plus récentes.
 * La vue « À aiguiller » (?aiguiller=1) liste pour tous les demandes arrivées sans conseiller ;
 * chacune s'attribue en un clic avec « M'attribuer » (US06, AiguillageController).
 * L'accès à une fiche (consultation, modification, suppression) est vérifié par DemandeVoter.
 *
 * @extends AbstractCrudController<Demande>
 */
#[IsGranted('ROLE_CONSEILLER')]
final class DemandeCrudController extends AbstractCrudController
{
    public function __construct(private readonly DemandeRepository $demandes)
    {
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
            ->setPermission('attribuer', DemandeVoter::ATTRIBUER)
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
            ->setTemplatePath('admin/champ/statut_demande.html.twig');
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
