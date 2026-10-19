<?php

namespace App\Form;

use App\Entity\PeriodeClimatique;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<PeriodeClimatique>
 */
final class PeriodeClimatiqueType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('debut', DateType::class, ['widget' => 'single_text', 'label' => 'Début'])
            ->add('fin', DateType::class, ['widget' => 'single_text', 'label' => 'Fin'])
            ->add('temperatureMoyenne', NumberType::class, ['label' => 'Température moyenne (°C)'])
            ->add('pluieMm', IntegerType::class, [
                'required' => false,
                'label' => 'Pluie (mm)',
            ])
            ->add('tarifs', CollectionType::class, [
                'entry_type' => TarifParPeriodeType::class,
                'mapped' => false,
                'label' => 'Tarifs des prestations',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => PeriodeClimatique::class]);
    }
}
