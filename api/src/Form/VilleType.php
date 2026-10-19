<?php

namespace App\Form;

use App\Repository\DestinationRepository;
use App\Repository\PrestataireRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Champ texte de ville avec suggestions (liste <datalist> du navigateur), pour écrire les villes comme ailleurs
 * dans le catalogue sans l'imposer : une ville nouvelle reste possible, et les variantes sont rapprochées
 * à la lecture (Correspondance).
 * - `suggestions: 'destinations'` : villes des destinations ;
 * - `suggestions: 'departs'` : villes de départ déjà saisies (temps de trajet, fourchettes de transport).
 * Rendu par le bloc `ville_widget` de templates/admin/form/ville.html.twig.
 *
 * @extends AbstractType<string>
 */
final class VilleType extends AbstractType
{
    public function __construct(
        private readonly DestinationRepository $destinations,
        private readonly PrestataireRepository $prestataires,
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('suggestions')->setAllowedValues('suggestions', ['destinations', 'departs']);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['suggestions'] = 'destinations' === $options['suggestions']
            ? $this->villesDesDestinations()
            : $this->villesDeDepart();
        $view->vars['attr']['list'] = $view->vars['id'].'_suggestions';
        $view->vars['attr']['autocomplete'] = 'off';
    }

    public function getParent(): string
    {
        return TextType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'ville';
    }

    /** @return list<string> */
    private function villesDesDestinations(): array
    {
        $villes = [];
        foreach ($this->destinations->findAll() as $destination) {
            $villes[] = (string) $destination->getVille();
        }

        return $this->trier($villes);
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

        return $this->trier($villes);
    }

    /**
     * @param list<string> $villes
     *
     * @return list<string>
     */
    private function trier(array $villes): array
    {
        $villes = array_values(array_unique(array_filter($villes, static fn (string $v): bool => '' !== $v)));
        sort($villes);

        return $villes;
    }
}
