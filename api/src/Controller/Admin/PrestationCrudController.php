<?php

namespace App\Controller\Admin;

use App\Admin\TeleversementsMultiples;
use App\Entity\Prestation;
use App\Enum\TypePrestataire;
use App\Enum\TypePrestation;
use App\Enum\UniteTarif;
use App\Form\TarifPrestationType;
use App\Form\TexteSuggereType;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Component\Validator\Constraints\Image;

/**
 * Base des CRUD Logements et Activités (#188) : la même entité Prestation, une liste par type.
 * Chaque fiche porte ses photos (plusieurs, téléversées dans public/uploads/prestations) et ses tarifs par période.
 * Un conseiller crée et modifie ; seul l'administrateur supprime.
 *
 * @extends AbstractCrudController<Prestation>
 */
abstract class PrestationCrudController extends AbstractCrudController
{
    use TeleversementsMultiples;

    abstract protected static function type(): TypePrestation;

    /**
     * Types de prestataires proposés dans la liste « Prestataire ».
     *
     * @return list<TypePrestataire>
     */
    abstract protected static function typesDePrestataire(): array;

    /**
     * Champs propres au type (capacité, chambres… ou durée, âge…), affichés dans « Caractéristiques ».
     *
     * @return iterable<Field|\EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface>
     */
    abstract protected function champsDuType(string $pageName): iterable;

    public static function getEntityFqcn(): string
    {
        return Prestation::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_EDIT, static fn (Prestation $p) => \sprintf('Modifier %s', $p->getNom()))
            ->setPageTitle(Crud::PAGE_DETAIL, static fn (Prestation $p) => (string) $p->getNom())
            ->setDefaultSort(['nom' => 'ASC'])
            ->setSearchFields(['nom', 'categorie', 'destination.ville', 'prestataire.nom'])
            ->addFormTheme('admin/form/texte_suggere.html.twig')
            ->addFormTheme('admin/form/tableau_saisie.html.twig');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->setPermission(Action::DELETE, 'ROLE_ADMIN')
            ->setPermission(Action::BATCH_DELETE, 'ROLE_ADMIN');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('destination', 'Destination'))
            ->add(EntityFilter::new('prestataire', 'Prestataire')->setFormTypeOption(
                'value_type_options.query_builder',
                static fn (EntityRepository $repo) => $repo->createQueryBuilder('p')
                    ->where('p.type IN (:types)')
                    ->setParameter('types', static::typesDePrestataire())
                    ->orderBy('p.nom', 'ASC'),
            ))
            ->add(BooleanFilter::new('accessible', 'Accessible'));
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->andWhere('entity.type = :typePrestation')
            ->setParameter('typePrestation', static::type());
    }

    public function createEntity(string $entityFqcn): Prestation
    {
        return new Prestation(static::type());
    }

    /** Une activité ouverte depuis la liste des logements (ou l'inverse) n'existe pas pour ce CRUD. */
    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        $entity = $responseParameters->get('entity');
        if ($entity instanceof EntityDto && ($prestation = $entity->getInstance()) instanceof Prestation
            && null !== $prestation->getId() && static::type() !== $prestation->getType()) {
            throw $this->createNotFoundException();
        }

        return $responseParameters;
    }

    public function configureFields(string $pageName): iterable
    {
        $logement = TypePrestation::Logement === static::type();

        yield FormField::addFieldset('Fiche');
        yield Field::new('photoPrincipale', 'Photo')
            ->setTemplatePath('admin/champ/photo_principale.html.twig')
            ->onlyOnIndex();
        yield TextField::new('nom', 'Nom');
        // Sans choix par défaut : une fiche ne doit pas partir dans la première destination de la liste par mégarde.
        yield AssociationField::new('destination', 'Destination')
            ->setQueryBuilder(static fn (QueryBuilder $qb) => $qb->orderBy('entity.ville', 'ASC'))
            ->setFormTypeOption('placeholder', 'Choisir la destination');
        yield AssociationField::new('prestataire', 'Prestataire')
            ->setQueryBuilder(static fn (QueryBuilder $qb) => $qb
                ->andWhere('entity.type IN (:types)')
                ->setParameter('types', static::typesDePrestataire())
                ->orderBy('entity.nom', 'ASC'))
            ->setFormTypeOption('placeholder', 'Choisir le prestataire');
        yield TextField::new('categorie', 'Catégorie')
            ->setFormType(TexteSuggereType::class)
            ->setFormTypeOption('suggestions', 'categories')
            ->setHelp($logement ? 'Ex. Hôtel 3 étoiles, Appartement, Villa.' : 'Ex. Excursion, Atelier, Sport nautique.');
        yield TextareaField::new('description', 'Description')->hideOnIndex();
        yield Field::new('tarifs', 'Tarif')
            ->setTemplatePath('admin/champ/resume_tarifs.html.twig')
            ->setSortable(false)
            ->onlyOnIndex();

        yield FormField::addFieldset('Caractéristiques');
        yield from $this->champsDuType($pageName);
        yield ArrayField::new('envies', 'Envies associées')
            ->setFormTypeOption('entry_type', TexteSuggereType::class)
            ->setFormTypeOption('entry_options', ['suggestions' => 'envies'])
            ->setRequired(false)
            ->setHelp('Une envie par ligne (plage, culture, détente…) : le chatbot s\'en sert pour proposer cette fiche.')
            ->hideOnIndex();

        yield FormField::addFieldset('Photos');
        yield ImageField::new('photos', 'Photos')
            ->setBasePath('/uploads/prestations')
            ->setUploadDir('public/uploads/prestations')
            ->setUploadedFileNamePattern('[slug]-[contenthash].[extension]')
            ->setFormTypeOption('multiple', true)
            ->setFileConstraints(new Image(maxSize: '5M'))
            ->setRequired(false)
            ->setHelp('Plusieurs photos possibles (JPEG ou PNG, 5 Mo chacune). La première sert de vignette.')
            ->hideOnIndex();

        yield FormField::addFieldset('Tarifs');
        // Saisie en tableau, une ligne par saison (admin/form/tableau_saisie.html.twig).
        yield CollectionField::new('tarifs', 'Tarifs par période')
            ->setEntryType(TarifPrestationType::class)
            ->setFormTypeOption('entry_options', [
                'unite' => $logement ? UniteTarif::ParNuit : UniteTarif::ParPersonne,
                'block_prefix' => 'ligne_saisie',
            ])
            ->setFormTypeOption('block_prefix', 'tableau_saisie')
            ->setFormTypeOption('attr', ['data-vide' => 'Aucun tarif pour l’instant.', 'data-ajouter' => 'Ajouter une saison'])
            ->setFormTypeOption('by_reference', false)
            ->addCssClass('field-array')
            ->setRequired(false)
            ->setHelp(($logement ? 'Prix d\'achat par nuit, pour le logement entier, avant marge.' : 'Prix d\'achat par personne, avant marge.')
                .' Une ligne par saison : sans tarif pour ses dates, une fiche n\'est pas proposée par le chatbot.')
            ->onlyOnForms();
        yield Field::new('tarifs', 'Tarifs par période')
            ->setTemplatePath('admin/champ/tarifs.html.twig')
            ->onlyOnDetail();
    }

    /**
     * @return list<TypePrestataire>
     */
    protected static function prestataires(TypePrestataire ...$types): array
    {
        return array_values($types);
    }
}
