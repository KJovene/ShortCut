<?php

namespace App\Controller\Admin;

use App\Entity\Utilisateur;
use App\Enum\Role;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Comptes du site et du backoffice, réservés à l'administrateur : il crée les comptes et change leur rôle.
 * Le mot de passe saisi est haché avant l'enregistrement ; il n'est jamais stocké en clair.
 *
 * @extends AbstractCrudController<Utilisateur>
 */
#[IsGranted('ROLE_ADMIN')]
final class UtilisateurCrudController extends AbstractCrudController
{
    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }

    public static function getEntityFqcn(): string
    {
        return Utilisateur::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Compte')
            ->setEntityLabelInPlural('Comptes')
            ->setDefaultSort(['nom' => 'ASC']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(ChoiceFilter::new('role', 'Rôle')->setChoices(
            array_combine(array_map(static fn (Role $r) => $r->label(), Role::cases()), array_map(static fn (Role $r) => $r->value, Role::cases())),
        ));
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('prenom', 'Prénom');
        yield TextField::new('nom', 'Nom');
        yield EmailField::new('email', 'E-mail');
        yield TextField::new('telephone', 'Téléphone')->hideOnIndex();
        // L'administrateur promeut ici un client inscrit sur le site en conseiller ou en administrateur.
        yield ChoiceField::new('role', 'Rôle')
            ->setChoices(array_combine(array_map(static fn (Role $r) => $r->label(), Role::cases()), Role::cases()))
            ->setFormTypeOption('choice_value', static fn (?Role $r) => $r?->value)
            ->renderAsBadges([Role::Administrateur->value => 'danger', Role::Conseiller->value => 'info', Role::Client->value => 'secondary']);

        $nouveau = Crud::PAGE_NEW === $pageName;
        yield TextField::new('motDePasse', $nouveau ? 'Mot de passe' : 'Nouveau mot de passe')
            ->onlyOnForms()
            ->setFormType(RepeatedType::class)
            ->setFormTypeOptions([
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => $nouveau,
                'first_options' => ['label' => $nouveau ? 'Mot de passe' : 'Nouveau mot de passe'],
                'second_options' => ['label' => 'Confirmation'],
                'invalid_message' => 'Les deux mots de passe ne correspondent pas.',
                'constraints' => array_filter([
                    $nouveau ? new Assert\NotBlank(message: 'Le mot de passe est obligatoire.') : null,
                    new Assert\Length(min: 8, minMessage: 'Le mot de passe doit faire au moins {{ limit }} caractères.'),
                ]),
            ])
            ->setHelp($nouveau ? '' : 'Laisser vide pour conserver le mot de passe actuel.');
    }

    /**
     * @return FormBuilderInterface<mixed>
     */
    public function createNewFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        return $this->ajouterHachage(parent::createNewFormBuilder($entityDto, $formOptions, $context));
    }

    /**
     * @return FormBuilderInterface<mixed>
     */
    public function createEditFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        return $this->ajouterHachage(parent::createEditFormBuilder($entityDto, $formOptions, $context));
    }

    /**
     * Hache le mot de passe saisi une fois le formulaire validé.
     *
     * @param FormBuilderInterface<mixed> $builder
     *
     * @return FormBuilderInterface<mixed>
     */
    private function ajouterHachage(FormBuilderInterface $builder): FormBuilderInterface
    {
        return $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $form = $event->getForm();
            $utilisateur = $form->getData();
            $motDePasse = $form->get('motDePasse')->getData();

            if (!$form->isValid() || !$utilisateur instanceof Utilisateur || !\is_string($motDePasse) || '' === $motDePasse) {
                return;
            }

            $utilisateur->setMotDePasse($this->hasher->hashPassword($utilisateur, $motDePasse));
        });
    }
}
