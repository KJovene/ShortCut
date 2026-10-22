<?php

namespace App\Form;

use App\Lieu\LieuxDuCatalogue;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Un lieu choisi dans un menu déroulant avec recherche (widget autocomplete d'EasyAdmin), au lieu d'être écrit.
 * Option `lieux` :
 * - `pays`, `arrivee` (villes des destinations), `zone` (lieux et zones du catalogue) : liste du catalogue ;
 * - `ville` (villes du monde), `depart` (villes de départ connues, puis villes de France), `transport` (départs et
 *   aéroports des destinations, puis villes de France) : recherche à la frappe sur /admin/lieux (LieuxController).
 * La valeur reste le nom du lieu, comme avant : la valeur déjà enregistrée est toujours proposée, même absente
 * de la liste.
 *
 * @extends AbstractType<string>
 */
final class LieuType extends AbstractType
{
    public const DISTANTS = ['ville', 'depart', 'transport'];

    public function __construct(
        private readonly LieuxDuCatalogue $catalogue,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired('lieux')
            ->setAllowedValues('lieux', ['pays', 'arrivee', 'zone', ...self::DISTANTS])
            ->setDefaults([
                'placeholder' => 'Choisir…',
                'choice_loader' => fn (Options $options): ChoiceLoaderInterface => $this->chargeur($options['lieux']),
                'choice_translation_domain' => false,
            ]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['attr']['data-ea-widget'] = 'ea-autocomplete';
        if (\in_array($options['lieux'], self::DISTANTS, true)) {
            $view->vars['attr']['data-ea-autocomplete-endpoint-url'] = $this->urls->generate('admin_lieux', ['lieux' => $options['lieux']]);
        }

        // La valeur enregistrée, même hors de la liste (ancienne saisie, ville d'une recherche distante).
        $valeur = (string) $view->vars['value'];
        $proposees = array_map(static fn (ChoiceView $c): string => $c->value, $view->vars['choices']);
        if ('' !== $valeur && !\in_array($valeur, $proposees, true)) {
            $view->vars['choices'][] = new ChoiceView($valeur, $valeur, $valeur);
        }
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }

    /**
     * Les lieux du catalogue à lister ; une valeur envoyée est acceptée telle quelle, puisqu'elle vient du menu.
     */
    private function chargeur(string $lieux): ChoiceLoaderInterface
    {
        $catalogue = $this->catalogue;

        return new class($lieux, $catalogue) implements ChoiceLoaderInterface {
            public function __construct(private readonly string $lieux, private readonly LieuxDuCatalogue $catalogue)
            {
            }

            public function loadChoiceList(?callable $value = null): ChoiceListInterface
            {
                $liste = match ($this->lieux) {
                    'pays' => $this->catalogue->pays(),
                    'arrivee' => $this->catalogue->villesDesDestinations(),
                    'zone' => $this->catalogue->lieuxEtZones(),
                    default => [],
                };

                return new ArrayChoiceList(array_combine($liste, $liste) ?: [], $value);
            }

            public function loadChoicesForValues(array $values, ?callable $value = null): array
            {
                return array_values(array_filter(array_map('strval', $values), static fn (string $v): bool => '' !== $v));
            }

            public function loadValuesForChoices(array $choices, ?callable $value = null): array
            {
                return array_values(array_filter(array_map(static fn (mixed $c): string => \is_scalar($c) ? (string) $c : '', $choices), static fn (string $v): bool => '' !== $v));
            }
        };
    }
}
