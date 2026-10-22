<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Range;

/** @extends AbstractType<array<string, float|null>> */
final class ClimatMensuelType extends AbstractType
{
    private const MOIS = [
        1 => 'Janvier',
        2 => 'Février',
        3 => 'Mars',
        4 => 'Avril',
        5 => 'Mai',
        6 => 'Juin',
        7 => 'Juillet',
        8 => 'Août',
        9 => 'Septembre',
        10 => 'Octobre',
        11 => 'Novembre',
        12 => 'Décembre',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (self::MOIS as $numero => $nom) {
            $builder->add('mois'.$numero, NumberType::class, [
                'label' => $nom,
                'required' => false,
                'html5' => true,
                'scale' => 1,
                'constraints' => [new Range(min: -50, max: 60)],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
