<?php

namespace App\Controller\Admin;

use App\Enum\TypePrestataire;
use App\Enum\TypePrestation;
use App\Form\TexteSuggereType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Logements du catalogue (#188) : capacité, chambres, équipements, accessibilité, photos, tarifs par nuit. */
#[IsGranted('ROLE_CONSEILLER')]
final class LogementCrudController extends PrestationCrudController
{
    protected static function type(): TypePrestation
    {
        return TypePrestation::Logement;
    }

    protected static function typesDePrestataire(): array
    {
        return self::prestataires(TypePrestataire::Hebergeur);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Logement')
            ->setEntityLabelInPlural('Logements')
            ->setPageTitle(Crud::PAGE_NEW, 'Nouveau logement');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return parent::configureFilters($filters)
            ->add(NumericFilter::new('capacite', 'Capacité'))
            ->add(NumericFilter::new('chambres', 'Chambres'));
    }

    protected function champsDuType(string $pageName): iterable
    {
        yield IntegerField::new('capacite', 'Capacité')
            ->setHelp('Nombre maximum de personnes pour le logement entier.');
        yield IntegerField::new('chambres', 'Chambres');
        yield BooleanField::new('accessible', 'Accessible')
            ->renderAsSwitch(false)
            ->setHelp('Adapté aux personnes à mobilité réduite.');
        yield ArrayField::new('equipements', 'Équipements')
            ->setFormTypeOption('entry_type', TexteSuggereType::class)
            ->setFormTypeOption('entry_options', ['suggestions' => 'equipements'])
            ->setRequired(false)
            ->hideOnIndex();
    }
}
