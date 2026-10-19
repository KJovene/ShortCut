<?php

namespace App\Controller\Admin;

use App\Enum\TypePrestataire;
use App\Enum\TypePrestation;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Activités du catalogue (#188) : durée, âge minimum, envies, photos, tarifs par personne. */
#[IsGranted('ROLE_CONSEILLER')]
final class ActiviteCrudController extends PrestationCrudController
{
    protected static function type(): TypePrestation
    {
        return TypePrestation::Activite;
    }

    protected static function typesDePrestataire(): array
    {
        return self::prestataires(TypePrestataire::Activite, TypePrestataire::Guide);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Activité')
            ->setEntityLabelInPlural('Activités')
            ->setPageTitle(Crud::PAGE_NEW, 'Nouvelle activité');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return parent::configureFilters($filters)
            ->add(NumericFilter::new('ageMinimum', 'Âge minimum'))
            ->add(NumericFilter::new('dureeMinutes', 'Durée (minutes)'));
    }

    protected function champsDuType(string $pageName): iterable
    {
        yield IntegerField::new('dureeMinutes', 'Durée (minutes)');
        yield IntegerField::new('ageMinimum', 'Âge minimum')
            ->setHelp('Laisser vide si l\'activité est ouverte à tous.');
        yield IntegerField::new('capacite', 'Participants par session')
            ->setHelp('Au-delà, l\'activité est organisée en plusieurs sessions.')
            ->hideOnIndex();
        yield BooleanField::new('accessible', 'Accessible')
            ->renderAsSwitch(false)
            ->setHelp('Adaptée aux personnes à mobilité réduite.')
            ->hideOnIndex();
    }
}
