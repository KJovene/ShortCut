<?php

namespace App\Controller\Admin;

use App\Entity\Traduction;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Traductions anglaises du site public, faites par Claude (Traducteur) : l'administrateur les relit et corrige
 * l'anglais. Le français ne se modifie pas ici, mais dans « Contenus du site » ou « Destinations » ; une
 * traduction supprimée est refaite à la prochaine visite en anglais.
 *
 * @extends AbstractCrudController<Traduction>
 */
#[IsGranted('ROLE_ADMIN')]
final class TraductionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Traduction::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Traduction')
            ->setEntityLabelInPlural('Traductions anglaises')
            ->setDefaultSort(['creeLe' => 'DESC'])
            ->setSearchFields(['francais', 'anglais'])
            ->setPaginatorPageSize(30);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextareaField::new('francais', 'Français')->setDisabled()->setMaxLength(Crud::PAGE_INDEX === $pageName ? 80 : -1)->renderAsHtml(false);
        yield TextareaField::new('anglais', 'Anglais')->setMaxLength(Crud::PAGE_INDEX === $pageName ? 80 : -1)->setNumOfRows(8);
        yield DateTimeField::new('creeLe', 'Traduit le')->hideOnForm();
    }
}
