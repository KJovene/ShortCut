<?php

namespace App\Controller\Admin;

use App\Admin\FiltreClimat;
use App\Entity\Destination;
use App\Form\TrajetType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\Image;

/**
 * Destinations du catalogue (#186) : la fiche que le site affiche et que le chatbot propose.
 * Le climat et les tarifs se saisissent par période (PeriodeClimatiqueController), depuis la fiche.
 * Un conseiller crée et modifie ; seul l'administrateur supprime (la suppression emporte logements et activités).
 *
 * @extends AbstractCrudController<Destination>
 */
#[IsGranted('ROLE_CONSEILLER')]
final class DestinationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Destination::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Destination')
            ->setEntityLabelInPlural('Destinations')
            ->setPageTitle(Crud::PAGE_NEW, 'Nouvelle destination')
            ->setPageTitle(Crud::PAGE_EDIT, static fn (Destination $d) => \sprintf('Modifier %s', $d->getVille()))
            ->setPageTitle(Crud::PAGE_DETAIL, static fn (Destination $d) => \sprintf('%s, %s', $d->getVille(), $d->getPays()))
            ->setDefaultSort(['ville' => 'ASC'])
            ->setSearchFields(['ville', 'pays', 'region']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $saisirPeriode = Action::new('saisirPeriode', 'Saisir une période', 'fa fa-temperature-half')
            ->linkToUrl(fn (Destination $d): string => $this->generateUrl('admin_destination_periode', ['id' => $d->getId()]));

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_DETAIL, $saisirPeriode)
            ->setPermission(Action::DELETE, 'ROLE_ADMIN')
            ->setPermission(Action::BATCH_DELETE, 'ROLE_ADMIN');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(FiltreClimat::new())
            ->add(BooleanFilter::new('publiee', 'Publiée'))
            ->add(BooleanFilter::new('miseEnAvant', 'Mise en avant'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addFieldset('Fiche');
        yield ImageField::new('photo', 'Photo')
            ->setBasePath('/uploads/destinations')
            ->setUploadDir('public/uploads/destinations')
            ->setUploadedFileNamePattern('[slug]-[contenthash].[extension]')
            ->setFileConstraints(new Image(maxSize: '5M'))
            ->setRequired(false)
            ->setHelp('JPEG ou PNG, 5 Mo maximum. Paysage de préférence : elle sert de bandeau sur le site.');
        yield TextField::new('ville', 'Ville');
        yield TextField::new('pays', 'Pays');
        yield TextField::new('region', 'Région');
        yield SlugField::new('slug', 'Adresse sur le site')
            ->setTargetFieldName('ville')
            ->setHelp('Rempli à partir de la ville. La fiche sera sur /destinations/… : minuscules, chiffres et tirets.')
            ->hideOnIndex();
        yield BooleanField::new('publiee', 'Publiée')
            ->setHelp('Une destination non publiée n\'apparaît ni sur le site ni dans les propositions du chatbot.');
        yield BooleanField::new('miseEnAvant', 'Mise en avant')
            ->setHelp('Affichée sur la page d\'accueil du site.');

        yield FormField::addFieldset('Présentation');
        yield TextareaField::new('description', 'Description')->hideOnIndex();
        yield ArrayField::new('pointsForts', 'Points forts')
            ->setRequired(false)
            ->setHelp('Quatre points forts au plus, courts : ils s\'affichent en liste sur la fiche.')
            ->hideOnIndex();
        yield CollectionField::new('trajets', 'Temps de trajet')
            ->setEntryType(TrajetType::class)
            ->setEntryIsComplex(false)
            ->setHelp('Durée du vol depuis chaque ville de départ proposée (ex. Paris, 150 minutes).')
            ->onlyOnForms();
        yield Field::new('tempsDeTrajet', 'Temps de trajet')
            ->setTemplatePath('admin/champ/temps_de_trajet.html.twig')
            ->onlyOnDetail();

        yield FormField::addFieldset('Climat')->onlyOnDetail();
        yield Field::new('periodesClimatiques', 'Climat par période')
            ->setTemplatePath('admin/champ/climat.html.twig')
            ->onlyOnDetail();
    }
}
