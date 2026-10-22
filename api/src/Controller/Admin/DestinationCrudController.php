<?php

namespace App\Controller\Admin;

use App\Admin\FiltreClimat;
use App\Entity\Destination;
use App\Entity\PeriodeClimatique;
use App\Enum\TypeDeVoyage;
use App\Entity\Utilisateur;
use App\Form\ClimatMensuelType;
use App\Form\TrajetType;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\Image;

/**
 * Destinations du catalogue (#186) : la fiche que le site affiche et que le chatbot propose.
 * Le climat mensuel et les tarifs se saisissent depuis la fiche.
 * Un conseiller crée et modifie ; seul l'administrateur supprime (la suppression emporte logements et activités).
 *
 * @extends AbstractCrudController<Destination>
 */
#[IsGranted('ROLE_CONSEILLER')]
final class DestinationCrudController extends AbstractCrudController
{
    /** @var array<string, float|null>|null */
    private ?array $temperaturesSoumises = null;

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
            ->setSearchFields(['ville', 'pays', 'region'])
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
        yield TextField::new('photoAlt', 'Description de la photo')
            ->setHelp('Lue par les lecteurs d\'écran à la place de la photo (ex. « Le Colisée de Rome sous un ciel clair »).')
            ->hideOnIndex();
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
        yield TextField::new('nom', 'Nom affiché')
            ->setHelp('Nom sur le site, ex. « Lisbonne, Portugal » ou « Islande ». Vide : la ville et le pays.')
            ->hideOnIndex();
        yield ChoiceField::new('typesDeVoyage', 'Types de voyage')
            ->setChoices(array_combine(
                array_map(static fn (TypeDeVoyage $type): string => $type->label(), TypeDeVoyage::cases()),
                TypeDeVoyage::valeurs(),
            ))
            ->allowMultipleChoices()
            ->setHelp('Le premier est le type principal : il fixe les frais de dossier du prix « à partir de » affiché sur le site.');
        yield ArrayField::new('zones', 'Zones')
            ->setRequired(false)
            ->setHelp('Zones larges où le chatbot trouve cette destination quand le client ne la nomme pas : continent, mer, sous-région (ex. « Europe », « Méditerranée », « Îles »). Le pays et la région sont déjà cherchés.')
            ->hideOnIndex();
        yield TextField::new('accroche', 'Accroche')
            ->setHelp('Une phrase, affichée sur la carte du monde de l\'accueil.')
            ->hideOnIndex();
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

        yield ChoiceField::new('moisIdeal', 'Meilleur mois')
            ->setChoices(['Janvier' => 1, 'Février' => 2, 'Mars' => 3, 'Avril' => 4, 'Mai' => 5, 'Juin' => 6, 'Juillet' => 7, 'Août' => 8, 'Septembre' => 9, 'Octobre' => 10, 'Novembre' => 11, 'Décembre' => 12])
            ->setRequired(false)
            ->setHelp('Mis en avant sur le site, avec la température de ce mois.')
            ->hideOnIndex();
        yield NumberField::new('latitude', 'Latitude')
            ->setNumDecimals(1)
            ->setRequired(true)
            ->setHelp('Position de l\'épingle sur la carte du monde de l\'accueil, ex. 38,7 pour Lisbonne.')
            ->hideOnIndex();
        yield NumberField::new('longitude', 'Longitude')
            ->setNumDecimals(1)
            ->setRequired(true)
            ->setHelp('Ex. -9,1 pour Lisbonne (ouest négatif).')
            ->hideOnIndex();

        yield Field::new('temperatures', 'Températures mensuelles')
            ->setFormType(ClimatMensuelType::class)
            ->setFormTypeOptions(['mapped' => false])
            ->setHelp('Température moyenne en °C. Les mois laissés vides ne sont pas modifiés.')
            ->onlyOnForms();
        yield FormField::addFieldset('Climat')->onlyOnDetail();
        yield Field::new('periodesClimatiques', 'Climat par période')
            ->setTemplatePath('admin/champ/climat.html.twig')
            ->onlyOnDetail();
    }

    /** @return FormBuilderInterface<mixed> */
    public function createNewFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        return $this->ajouterClimat(parent::createNewFormBuilder($entityDto, $formOptions, $context));
    }

    /** @return FormBuilderInterface<mixed> */
    public function createEditFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        return $this->ajouterClimat(parent::createEditFormBuilder($entityDto, $formOptions, $context));
    }

    public function persistEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        $this->synchroniserClimat($entityInstance, $entityManager);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        $this->synchroniserClimat($entityInstance, $entityManager);
        parent::updateEntity($entityManager, $entityInstance);
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     *
     * @return FormBuilderInterface<mixed>
     */
    private function ajouterClimat(FormBuilderInterface $builder): FormBuilderInterface
    {
        $builder
            ->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
                $destination = $event->getData();
                if (!$destination instanceof Destination || !$event->getForm()->has('temperatures')) {
                    return;
                }

                $event->getForm()->get('temperatures')->setData($this->temperaturesDe($destination));
            })
            ->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
                if (!$event->getForm()->isValid() || !$event->getForm()->has('temperatures')) {
                    return;
                }

                /** @var array<string, float|null> $temperatures */
                $temperatures = $event->getForm()->get('temperatures')->getData();
                $this->temperaturesSoumises = $temperatures;
            });

        return $builder;
    }

    /** @return array<string, float|null> */
    private function temperaturesDe(Destination $destination): array
    {
        $temperatures = [];
        foreach ($destination->getPeriodesClimatiques() as $periode) {
            if (null !== $periode->getDebut() && null !== $periode->getTemperatureMoyenne()) {
                $temperatures['mois'.$periode->getDebut()->format('n')] = $periode->getTemperatureMoyenne();
            }
        }

        return $temperatures;
    }

    private function synchroniserClimat(Destination $destination, EntityManagerInterface $entityManager): void
    {
        if (null === $this->temperaturesSoumises) {
            return;
        }

        $periodes = [];
        foreach ($destination->getPeriodesClimatiques() as $periode) {
            if (null !== $periode->getDebut()) {
                $periodes[(int) $periode->getDebut()->format('n')] ??= $periode;
            }
        }

        $utilisateur = $this->getUser();
        foreach ($this->temperaturesSoumises as $cle => $temperature) {
            if (null === $temperature || !str_starts_with($cle, 'mois')) {
                continue;
            }
            $mois = (int) substr($cle, 4);
            $periode = $periodes[$mois] ?? new PeriodeClimatique();
            if (null === $periode->getDebut()) {
                $debut = new \DateTimeImmutable(\sprintf('%d-%02d-01', (int) date('Y'), $mois));
                $periode->setDebut($debut)->setFin($debut->modify('last day of this month'));
            }
            $periode
                ->setDestination($destination)
                ->setTemperatureMoyenne((float) $temperature)
                ->setSaisiPar($utilisateur instanceof Utilisateur ? $utilisateur : null)
                ->setModifieLe(new \DateTimeImmutable());
            $entityManager->persist($periode);
        }
    }
}
