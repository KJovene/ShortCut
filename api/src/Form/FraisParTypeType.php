<?php

namespace App\Form;

use App\Enum\TypeDeVoyage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Barème des frais de dossier (D21) : un montant en euros par type de voyage, enregistré en centimes et indexé par
 * valeur de TypeDeVoyage (RegleDePrix::$fraisDossierParTypeCentimes).
 *
 * @extends AbstractType<array<string, int>>
 */
final class FraisParTypeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (TypeDeVoyage::cases() as $type) {
            $builder->add($type->value, MoneyType::class, [
                'label' => $type->label(),
                'divisor' => 100,
                'input' => 'integer',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['label' => false]);
    }
}
