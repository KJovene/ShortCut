<?php

namespace App\Form;

use App\Entity\TarifParPeriode;
use App\Enum\UniteTarif;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<TarifParPeriode>
 */
final class TarifParPeriodeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'mapped' => false,
                'label' => 'Nom de la prestation',
            ])
            ->add('description', TextareaType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Description',
            ])
            ->add('photo', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Image (nom du fichier)',
                'help' => 'Nom d’un fichier présent dans public/uploads/prestations.',
            ])
            ->add('photoUpload', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Importer une nouvelle image',
                'help' => 'Formats image acceptés. Le fichier sera utilisé sur le front.',
            ])
            ->add('debut', DateType::class, ['widget' => 'single_text', 'label' => 'Début'])
            ->add('fin', DateType::class, ['widget' => 'single_text', 'label' => 'Fin'])
            ->add('prixCentimes', IntegerType::class, ['label' => 'Prix (centimes)'])
            ->add('unite', ChoiceType::class, [
                'choices' => UniteTarif::cases(),
                'choice_label' => static fn (UniteTarif $unite): string => $unite->label(),
                'label' => 'Unité',
            ])
            ->add('sejourMinimumNuits', IntegerType::class, [
                'required' => false,
                'label' => 'Séjour minimum (nuits)',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => TarifParPeriode::class]);
    }
}
