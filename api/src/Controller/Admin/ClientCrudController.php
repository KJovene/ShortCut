<?php

namespace App\Controller\Admin;

use App\Entity\Utilisateur;
use App\Enum\Role;
use App\Repository\DemandeRepository;
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
 * Les clients naissent dans le chatbot ou à l'inscription : pas de création ni de modification ici.
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
            ->setDefaultSort(['nom' => 'ASC'])
            ->setSearchFields(['nom', 'prenom', 'email'])
            ->overrideTemplate('crud/detail', 'admin/client/detail.html.twig');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE);
    }

    /** Seuls les clients sont listés : les comptes du backoffice se gèrent dans « Comptes ». */
    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->andWhere('entity.role = :client')
            ->setParameter('client', Role::Client);
    }

    /** Ajoute l'historique du client à sa fiche ; un compte qui n'est pas client n'a pas de fiche. */
    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        if (Crud::PAGE_DETAIL !== $responseParameters->get('pageName')) {
            return $responseParameters;
        }

        /** @var EntityDto<Utilisateur> $entity */
        $entity = $responseParameters->get('entity');
        $client = $entity->getInstance();
        if (!$client instanceof Utilisateur || Role::Client !== $client->getRole()) {
            throw $this->createNotFoundException('Ce compte n\'est pas un client.');
        }

        $responseParameters->set('demandes', $this->demandes->findHistoriqueDuClient($client));

        return $responseParameters;
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('prenom', 'Prénom');
        yield TextField::new('nom', 'Nom');
        yield EmailField::new('email', 'E-mail');
        yield TextField::new('telephone', 'Téléphone');
        yield ArrayField::new('preferences', 'Préférences')->onlyOnDetail();
    }
}
