<?php

namespace App\Controller\Admin;

use App\Entity\Utilisateur;
use App\Enum\Role;
use App\Repository\DemandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Fiche client (#183, #184) : le conseiller retrouve un client par son nom ou son e-mail, puis consulte
 * ses demandes et ses conversations pour reprendre un dossier sans tout redemander.
 * Les clients naissent dans le chatbot ou à l'inscription : pas de création ici. Le conseiller ne modifie que les
 * préférences du client (étiquettes, D27) ; ses coordonnées restent les siennes, modifiées depuis son profil.
 * Une demande suivie par un autre conseiller apparaît dans l'historique sans son détail (DemandeVoter).
 *
 * @extends AbstractCrudController<Utilisateur>
 */
#[IsGranted('ROLE_CONSEILLER')]
final class ClientCrudController extends AbstractCrudController
{
    public function __construct(private readonly DemandeRepository $demandes)
    {
    }

    public static function getEntityFqcn(): string
    {
        return Utilisateur::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Client')
            ->setEntityLabelInPlural('Clients')
            ->setPageTitle(Crud::PAGE_DETAIL, static fn (Utilisateur $client) => (string) $client)
            ->setPageTitle(Crud::PAGE_EDIT, static fn (Utilisateur $client) => \sprintf('Préférences de %s', $client))
            ->setDefaultSort(['nom' => 'ASC'])
            ->setSearchFields(['nom', 'prenom', 'email'])
            ->overrideTemplate('crud/detail', 'admin/client/detail.html.twig');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::EDIT, static fn (Action $action) => $action->setLabel('Préférences'))
            ->update(Crud::PAGE_DETAIL, Action::EDIT, static fn (Action $action) => $action->setLabel('Modifier les préférences'))
            ->disable(Action::NEW, Action::DELETE, Action::BATCH_DELETE);
    }

    /** Seuls les clients sont listés : les comptes du backoffice se gèrent dans « Comptes ». */
    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->andWhere('entity.role = :client')
            ->setParameter('client', Role::Client);
    }

    /** Ajoute l'historique du client à sa fiche ; un compte qui n'est pas client n'a ni fiche ni préférences. */
    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        if (!\in_array($responseParameters->get('pageName'), [Crud::PAGE_DETAIL, Crud::PAGE_EDIT], true)) {
            return $responseParameters;
        }

        /** @var EntityDto<Utilisateur> $entity */
        $entity = $responseParameters->get('entity');
        $client = $entity->getInstance();
        if (!$client instanceof Utilisateur || Role::Client !== $client->getRole()) {
            throw $this->createNotFoundException('Ce compte n\'est pas un client.');
        }

        if (Crud::PAGE_DETAIL === $responseParameters->get('pageName')) {
            $responseParameters->set('demandes', $this->demandes->findHistoriqueDuClient($client));
        }

        return $responseParameters;
    }

    public function updateEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        if (Role::Client !== $entityInstance->getRole()) {
            throw $this->createNotFoundException('Ce compte n\'est pas un client.');
        }
        parent::updateEntity($entityManager, $entityInstance);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('prenom', 'Prénom')->hideOnForm();
        yield TextField::new('nom', 'Nom')->hideOnForm();
        yield EmailField::new('email', 'E-mail')->hideOnForm();
        yield TextField::new('telephone', 'Téléphone')->hideOnForm();
        yield ArrayField::new('preferences', 'Préférences')
            ->hideOnIndex()
            ->setHelp('Une étiquette par ligne : « plage », « famille », « gastronomie »… Elles aident à reprendre un dossier.');
    }
}
