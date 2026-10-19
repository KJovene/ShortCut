<?php

namespace App\Controller\Admin;

use App\Admin\FiltreDestinationPrestataire;
use App\Entity\Prestataire;
use App\Enum\TypePrestataire;
use App\Form\FourchetteTransportType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Prestataires du catalogue (#187) : une seule entité pour les hébergeurs, transporteurs, guides et
 * prestataires d'activités (décision D15). Les fourchettes de prix ne concernent que les transporteurs.
 * Un conseiller crée et modifie ; seul l'administrateur supprime (la suppression emporte les prestations).
 *
 * @extends AbstractCrudController<Prestataire>
 */
#[IsGranted('ROLE_CONSEILLER')]
final class PrestataireCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Prestataire::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Prestataire')
            ->setEntityLabelInPlural('Prestataires')
            ->setPageTitle(Crud::PAGE_NEW, 'Nouveau prestataire')
            ->setPageTitle(Crud::PAGE_EDIT, static fn (Prestataire $p) => \sprintf('Modifier %s', $p->getNom()))
            ->setPageTitle(Crud::PAGE_DETAIL, static fn (Prestataire $p) => (string) $p->getNom())
            ->setDefaultSort(['nom' => 'ASC'])
            ->setSearchFields(['nom', 'contactNom', 'email'])
            ->addFormTheme('admin/form/texte_suggere.html.twig');
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
        $types = [];
        foreach (TypePrestataire::cases() as $type) {
            $types[$type->label()] = $type->value;
        }

        return $filters
            ->add(ChoiceFilter::new('type', 'Type')->setChoices($types))
            ->add(FiltreDestinationPrestataire::new());
    }

    public function configureFields(string $pageName): iterable
    {
        $types = [];
        foreach (TypePrestataire::cases() as $type) {
            $types[$type->label()] = $type;
        }

        yield FormField::addFieldset('Prestataire');
        yield TextField::new('nom', 'Nom');
        yield ChoiceField::new('type', 'Type')
            ->setChoices($types)
            ->setFormTypeOption('choice_value', static fn (?TypePrestataire $t) => $t?->value)
            ->setFormTypeOption('placeholder', 'Choisir le type')
            ->setRequired(true);

        yield FormField::addFieldset('Contact');
        yield TextField::new('contactNom', 'Contact');
        yield EmailField::new('email', 'E-mail');
        yield TelephoneField::new('telephone', 'Téléphone');
        yield TextareaField::new('conditionsAnnulation', 'Conditions d\'annulation')->hideOnIndex();

        yield FormField::addFieldset('Prix des transports')->hideOnIndex();
        yield CollectionField::new('fourchettesTransport', 'Fourchettes de prix')
            ->setEntryType(FourchetteTransportType::class)
            ->setRequired(false)
            ->setHelp('Transporteurs seulement : prix d\'un aller-retour ou d\'un transfert, par personne, du moins cher au plus cher. Le chatbot s\'en sert pour estimer le transport.')
            ->onlyOnForms();
        yield Field::new('fourchettesTransport', 'Fourchettes de prix')
            ->setTemplatePath('admin/champ/fourchettes_transport.html.twig')
            ->onlyOnDetail();

        yield FormField::addFieldset('Prestations')->onlyOnDetail();
        yield Field::new('prestations', 'Logements et activités')
            ->setTemplatePath('admin/champ/prestations_du_prestataire.html.twig')
            ->onlyOnDetail();
    }
}
