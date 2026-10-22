<?php

namespace App\Controller\Admin;

use App\Entity\PageDuSite;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\FileUploadType;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\Model\FileUploadState;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\Image;

/**
 * Contenus du site (#217) : textes, photos et référencement des pages, réservés à l'administrateur.
 * Le site relit la page à chaque chargement (GET /api/pages/{slug}) : une modification enregistrée
 * est visible au rechargement. L'adresse d'une page existante est figée, le site la demande par son slug.
 *
 * @extends AbstractCrudController<PageDuSite>
 */
#[IsGranted('ROLE_ADMIN')]
final class PageDuSiteCrudController extends AbstractCrudController
{
    /**
     * Le site découpe le texte en paragraphes, intertitres et listes (features/pages/pageDuSite.ts) :
     * l'éditeur produit des <p> et des <h2> plutôt que les <div> et <h1> de Trix.
     */
    private const EDITEUR = [
        'blockAttributes' => [
            'default' => ['tagName' => 'p'],
            'heading1' => ['tagName' => 'h2'],
        ],
    ];

    public static function getEntityFqcn(): string
    {
        return PageDuSite::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Page')
            ->setEntityLabelInPlural('Contenus du site')
            ->setPageTitle(Crud::PAGE_NEW, 'Nouvelle page')
            ->setPageTitle(Crud::PAGE_EDIT, static fn (PageDuSite $p) => \sprintf('Modifier « %s »', str_replace('*', '', (string) $p->getTitre())))
            ->setPageTitle(Crud::PAGE_DETAIL, static fn (PageDuSite $p) => str_replace('*', '', (string) $p->getTitre()))
            ->setDefaultSort(['ordreMenu' => 'ASC', 'titre' => 'ASC'])
            ->setSearchFields(['titre', 'slug', 'contenu']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    /**
     * Contournement d'EasyAdmin 5.7 : supprimer une photo et en ajouter une dans le même enregistrement
     * plantait (« Undefined array key »). Les nouvelles photos sont numérotées à la suite des photos
     * d'avant la suppression ; on retire donc les photos supprimées avant l'enregistrement des fichiers.
     *
     * @param FormInterface<mixed> $form
     */
    protected function processUploadedFiles(FormInterface $form): void
    {
        foreach ($form as $champ) {
            $state = $champ->getConfig()->getAttribute('state');
            if (!$champ->getConfig()->getType()->getInnerType() instanceof FileUploadType || !$state instanceof FileUploadState || [] === $state->getDeletedFiles()) {
                continue;
            }
            $state->setCurrentFiles(array_values(array_filter(
                $state->getCurrentFiles(),
                static fn (File $photo): bool => !\in_array($photo->getFilename(), $state->getDeletedFiles(), true),
            )));
        }

        parent::processUploadedFiles($form);
    }

    /** La page modifiée ou consultée est l'accueil (le formulaire de création ne connaît pas encore son adresse). */
    private function estAccueil(): bool
    {
        $page = $this->getContext()?->getEntity()->getInstance();

        return $page instanceof PageDuSite && 'accueil' === $page->getSlug();
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addFieldset('Page');
        yield TextField::new('titre', 'Titre')
            ->setHelp('Grand titre en haut de la page. Les mots entre astérisques sont mis en valeur (italique orange) : « Où irez-vous *chercher le soleil* ? ». Sur l\'accueil, c\'est le titre des voyages chauds.');
        // L'accueil a deux thèmes de voyages : un titre chacun.
        if ($this->estAccueil()) {
            yield TextField::new('titreFroid', 'Titre des voyages froids')
                ->setRequired(false)
                ->setHelp('Titre de l\'accueil quand le visiteur choisit « Voyages froids », avec les mêmes astérisques : « Où irez-vous *chercher la fraîcheur* ? ».');
        }
        yield SlugField::new('slug', 'Adresse sur le site')
            ->setTargetFieldName('titre')
            // Le site appelle chaque page par son adresse (accueil, agence, cgv…) : la changer la ferait disparaître.
            ->setDisabled(Crud::PAGE_EDIT === $pageName)
            ->setHelp('Minuscules, chiffres et tirets. Ne se modifie plus après la création : le site lit la page à cette adresse.');
        yield BooleanField::new('publiee', 'Publiée')
            ->setHelp('Une page non publiée n\'est plus servie au site, qui affiche alors son texte par défaut.');
        yield IntegerField::new('ordreMenu', 'Ordre dans le menu')
            ->setRequired(false)
            ->setHelp('Position dans le menu du site (1, 2, 3…). Vide : la page n\'apparaît pas dans le menu.');

        yield FormField::addFieldset('Contenu');
        yield TextEditorField::new('contenu', 'Texte')
            ->setTrixEditorConfig(self::EDITEUR)
            ->setNumOfRows(15)
            ->setHelp('Le bouton « Titre » crée un intertitre. Page Agence : seul l\'intertitre « Nos engagements » est repris, avec sa liste. Page Types de voyages : un intertitre par type (Séjours, Circuits, Groupes, Voyages de noces, Entreprise). Le gras, l\'italique et les liens ne s\'affichent pas sur le site.')
            ->hideOnIndex();
        yield ImageField::new('photos', 'Photos')
            ->setBasePath('/uploads/pages')
            ->setUploadDir('public/uploads/pages')
            ->setUploadedFileNamePattern('[slug]-[contenthash].[extension]')
            ->setFormTypeOptions(['multiple' => true])
            ->setFileConstraints(new Image(maxSize: '5M'))
            ->setRequired(false)
            ->setHelp('JPEG ou PNG, 5 Mo maximum. La première sert de bandeau. Page Types de voyages : nommez chaque fichier comme le type (sejour.jpg, circuit.jpg, groupe.jpg, noces.jpg, entreprise.jpg).')
            ->hideOnIndex();

        yield FormField::addFieldset('Référencement');
        yield TextField::new('seoTitre', 'Titre pour les moteurs de recherche')
            ->setRequired(false)
            ->setFormTypeOption('attr', ['maxlength' => 70])
            ->setHelp('70 caractères au plus, affiché dans l\'onglet du navigateur et sur Google.')
            ->hideOnIndex();
        yield TextareaField::new('seoDescription', 'Description pour les moteurs de recherche')
            ->setRequired(false)
            ->setFormTypeOption('attr', ['maxlength' => 160])
            ->setHelp('160 caractères au plus, affichée sous le titre dans les résultats de Google.')
            ->hideOnIndex();
    }
}
