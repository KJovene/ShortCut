<?php

namespace App\Controller\Admin;

use App\Entity\RegleDePrix;
use App\Form\FraisParTypeType;
use App\Repository\RegleDePrixRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Règles de prix de l'agence (D16, D21), modifiables par l'administrateur seul : marge et frais de dossier par type
 * de voyage. Il n'y en a qu'une : le menu mène directement à sa modification. Elle s'applique aux chiffrages suivants ;
 * les propositions déjà enregistrées gardent leurs montants.
 *
 * @extends AbstractCrudController<RegleDePrix>
 */
#[IsGranted('ROLE_ADMIN')]
final class RegleDePrixCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly RegleDePrixRepository $regles,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return RegleDePrix::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Règles de prix')
            ->setEntityLabelInPlural('Règles de prix')
            ->setPageTitle(Crud::PAGE_EDIT, 'Règles de prix');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->disable(Action::NEW, Action::DELETE, Action::BATCH_DELETE, Action::DETAIL);
    }

    /** Pas de liste : la règle unique s'ouvre en modification (créée avec les valeurs par défaut au premier accès). */
    public function index(AdminContext $context): Response
    {
        $regle = $this->regles->courante();
        if (null === $regle->getId()) {
            $this->em->persist($regle);
            $this->em->flush();
        }

        return $this->redirect($this->container->get(AdminUrlGeneratorInterface::class)
            ->setController(self::class)
            ->setAction(Action::EDIT)
            ->setEntityId($regle->getId())
            ->generateUrl());
    }

    public function updateEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        $entityInstance->setModifieLe(new \DateTimeImmutable());
        parent::updateEntity($entityManager, $entityInstance);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IntegerField::new('margePourcentage', 'Marge (%)')
            ->setHelp('Ajoutée au coût des prestations, sans commission (D16).');
        yield MoneyField::new('fraisDossierMinCentimes', 'Frais de dossier minimum')->setCurrency('EUR')->setStoredAsCents();
        yield MoneyField::new('fraisDossierMaxCentimes', 'Frais de dossier maximum')->setCurrency('EUR')->setStoredAsCents();
        // Nom virtuel : sur la colonne JSON elle-même, EasyAdmin imposerait une liste (ArrayField).
        yield Field::new('baremeFraisDossier', 'Frais de dossier par type de voyage')
            ->setFormType(FraisParTypeType::class)
            ->setFormTypeOption('property_path', 'fraisDossierParTypeCentimes')
            ->setHelp('Ajoutés une fois par dossier, entre le minimum et le maximum (D21). Le site les affiche sur la page « Types de voyages ».');
    }
}
