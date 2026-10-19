<?php

namespace App\Form;

use App\Repository\DestinationRepository;
use App\Repository\PrestataireRepository;
use App\Repository\PrestationRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Champ texte avec suggestions (liste <datalist> du navigateur), pour écrire comme ailleurs dans le catalogue
 * sans l'imposer : une valeur nouvelle reste possible, et les variantes sont rapprochées à la lecture (Correspondance).
 * Option `suggestions` :
 * - `destinations` : villes des destinations ;
 * - `departs` : villes de départ déjà saisies (temps de trajet, fourchettes de transport) ;
 * - `envies`, `categories`, `equipements` : valeurs déjà utilisées par les logements et activités.
 * Rendu par le bloc `texte_suggere_widget` de templates/admin/form/texte_suggere.html.twig.
 *
 * @extends AbstractType<string>
 */
final class TexteSuggereType extends AbstractType
{
    public function __construct(
        private readonly DestinationRepository $destinations,
        private readonly PrestataireRepository $prestataires,
        private readonly PrestationRepository $prestations,
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('suggestions')
            ->setAllowedValues('suggestions', ['destinations', 'departs', 'envies', 'categories', 'equipements']);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['suggestions'] = $this->trier(match ($options['suggestions']) {
            'destinations' => $this->villesDesDestinations(),
            'departs' => $this->villesDeDepart(),
            default => $this->valeursDesPrestations($options['suggestions']),
        });
        $view->vars['attr']['list'] = $view->vars['id'].'_suggestions';
        $view->vars['attr']['autocomplete'] = 'off';
    }

    public function getParent(): string
    {
        return TextType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'texte_suggere';
    }

    /** @return list<string> */
    private function villesDesDestinations(): array
    {
        $villes = [];
        foreach ($this->destinations->findAll() as $destination) {
            $villes[] = (string) $destination->getVille();
        }

        return $villes;
    }

    /** @return list<string> */
    private function villesDeDepart(): array
    {
        $villes = [];
        foreach ($this->destinations->findAll() as $destination) {
            array_push($villes, ...array_map('strval', array_keys($destination->getTempsDeTrajet())));
        }
        foreach ($this->prestataires->findAll() as $prestataire) {
            array_push($villes, ...array_column($prestataire->getFourchettesTransport(), 'depart'));
        }

        return $villes;
    }

    /** @return list<string> */
    private function valeursDesPrestations(string $champ): array
    {
        $valeurs = [];
        foreach ($this->prestations->findAll() as $prestation) {
            match ($champ) {
                'envies' => array_push($valeurs, ...$prestation->getEnvies()),
                'equipements' => array_push($valeurs, ...$prestation->getEquipements()),
                default => $valeurs[] = (string) $prestation->getCategorie(),
            };
        }

        return $valeurs;
    }

    /**
     * @param list<string> $valeurs
     *
     * @return list<string>
     */
    private function trier(array $valeurs): array
    {
        $valeurs = array_values(array_unique(array_filter($valeurs, static fn (string $v): bool => '' !== $v)));
        sort($valeurs);

        return $valeurs;
    }
}
