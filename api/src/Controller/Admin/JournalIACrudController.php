<?php

namespace App\Controller\Admin;

use App\Entity\JournalIA;
use App\Enum\AgentIA;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Journal de l'IA (US13, ticket #214) : le responsable contrôle chaque appel des agents, ce qui leur a été envoyé,
 * leur réponse et les outils appelés. Lecture seule ; s'ouvre filtré sur une demande depuis sa fiche.
 *
 * @extends AbstractCrudController<JournalIA>
 */
#[AdminRoute(path: '/journal-ia', name: 'journal_ia')]
#[IsGranted('ROLE_ADMIN')]
final class JournalIACrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return JournalIA::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Appel IA')
            ->setEntityLabelInPlural('Journal IA')
            ->setPageTitle(Crud::PAGE_DETAIL, static fn (JournalIA $j) => \sprintf('Appel IA n° %d', $j->getId()))
            ->setDefaultSort(['creeLe' => 'DESC'])
            ->setSearchFields(null)
            ->setPaginatorPageSize(30);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('demande', 'Demande'))
            ->add(DateTimeFilter::new('creeLe', 'Date'))
            ->add(ChoiceFilter::new('agent', 'Agent')->setChoices(self::agents()));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'N°');
        yield DateTimeField::new('creeLe', 'Date')->setFormat('dd/MM/yyyy HH:mm:ss');
        yield ChoiceField::new('agent', 'Agent')
            ->setChoices(array_combine(array_keys(self::agents()), AgentIA::cases()))
            ->setFormTypeOption('choice_value', static fn (?AgentIA $a) => $a?->value);
        yield AssociationField::new('demande', 'Demande')
            ->formatValue(static fn ($valeur, JournalIA $j) => null === $j->getDemande() ? '—' : \sprintf('n° %d', $j->getDemande()->getId()));
        yield TextField::new('client', 'Client')
            ->formatValue(static fn ($client) => null === $client ? '—' : (string) $client);
        yield ArrayField::new('outils', 'Outils appelés');
        yield IntegerField::new('dureeMs', 'Durée')
            ->formatValue(static fn (?int $ms) => null === $ms ? '—' : number_format($ms / 1000, 1, ',', ' ').' s');
        yield TextField::new('erreur', 'Erreur')
            ->formatValue(static fn (?string $erreur) => $erreur ?? '—');

        yield TextField::new('modele', 'Modèle')->onlyOnDetail();
        yield IntegerField::new('tokensEntree', 'Tokens en entrée')->onlyOnDetail();
        yield IntegerField::new('tokensSortie', 'Tokens en sortie')->onlyOnDetail();
        yield Field::new('entree', 'Entrée')
            ->onlyOnDetail()
            ->setTemplatePath('admin/champ/json.html.twig');
        yield Field::new('sortie', 'Sortie')
            ->onlyOnDetail()
            ->setTemplatePath('admin/champ/json.html.twig');
    }

    /** @return array<string, string> libellé => valeur, pour la liste déroulante du filtre */
    private static function agents(): array
    {
        $choix = [];
        foreach (AgentIA::cases() as $agent) {
            $choix[$agent->label()] = $agent->value;
        }

        return $choix;
    }
}
