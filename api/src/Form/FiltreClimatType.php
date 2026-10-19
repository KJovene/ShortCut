<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formulaire du filtre « Climat » de la liste des destinations : un mois et une température minimale.
 * EasyAdmin attend `comparison` et `value` : le mois va dans `value`, la température dans `value2`.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class FiltreClimatType extends AbstractType
{
    public const MOIS = [
        'Janvier' => 1, 'Février' => 2, 'Mars' => 3, 'Avril' => 4, 'Mai' => 5, 'Juin' => 6,
        'Juillet' => 7, 'Août' => 8, 'Septembre' => 9, 'Octobre' => 10, 'Novembre' => 11, 'Décembre' => 12,
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('mois', ChoiceType::class, [
                'label' => 'Mois',
                'choices' => self::MOIS,
                'placeholder' => 'Quel que soit le mois',
                'required' => false,
            ])
            ->add('temperatureMin', NumberType::class, [
                'label' => 'Au moins (°C)',
                'required' => false,
                'html5' => true,
                // Le thème des filtres d'EasyAdmin n'affiche pas les libellés des sous-champs.
                'attr' => ['placeholder' => 'Température moyenne minimale (°C)'],
                'constraints' => [new Assert\Range(min: -50, max: 60)],
            ])
            ->addModelTransformer(new CallbackTransformer(
                static fn (mixed $donnees): array => [
                    'mois' => \is_array($donnees) ? ($donnees['value'] ?? null) : null,
                    'temperatureMin' => \is_array($donnees) ? ($donnees['value2'] ?? null) : null,
                ],
                static fn (mixed $saisie): array => [
                    'comparison' => '=',
                    'value' => \is_array($saisie) ? ($saisie['mois'] ?? null) : null,
                    'value2' => \is_array($saisie) ? ($saisie['temperatureMin'] ?? null) : null,
                ],
            ));
    }
}
