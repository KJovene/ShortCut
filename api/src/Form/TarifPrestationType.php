<?php

namespace App\Form;

use App\Entity\TarifParPeriode;
use App\Enum\UniteTarif;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Un tarif saisi dans la fiche d'un logement ou d'une activité : période et prix en euros (stocké en centimes).
 * L'unité découle de la fiche (logement : par nuit ; activité : par personne), elle n'est pas demandée.
 *
 * @extends AbstractType<TarifParPeriode>
 */
final class TarifPrestationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $parNuit = UniteTarif::ParNuit === $options['unite'];

        $builder
            ->add('debut', DateType::class, ['widget' => 'single_text', 'label' => 'Du'])
            ->add('fin', DateType::class, ['widget' => 'single_text', 'label' => 'Au'])
            ->add('prixCentimes', MoneyType::class, [
                'label' => $parNuit ? 'Prix par nuit (€)' : 'Prix par personne (€)',
                'currency' => 'EUR',
                'divisor' => 100,
                'input' => 'integer',
            ]);

        if ($parNuit) {
            $builder->add('sejourMinimumNuits', IntegerType::class, [
                'label' => 'Nuits minimum',
                'required' => false,
                'attr' => ['min' => 1],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'data_class' => TarifParPeriode::class,
                'unite' => UniteTarif::ParNuit,
                'empty_data' => static fn (Options $options) => static fn () => (new TarifParPeriode())->setUnite($options['unite']),
            ])
            ->setAllowedTypes('unite', UniteTarif::class);
    }
}
