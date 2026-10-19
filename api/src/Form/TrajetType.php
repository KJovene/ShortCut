<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Une ligne « temps de trajet » d'une destination : ville de départ et durée en minutes.
 *
 * @extends AbstractType<array{ville: ?string, minutes: ?int}>
 */
final class TrajetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('ville', TexteSuggereType::class, [
                'label' => 'Ville de départ',
                'suggestions' => 'departs',
                'attr' => ['placeholder' => 'Paris'],
                'constraints' => [new Assert\NotBlank(message: 'Indiquez la ville de départ.'), new Assert\Length(max: 100)],
            ])
            ->add('minutes', IntegerType::class, [
                'label' => 'Durée (minutes)',
                'attr' => ['placeholder' => '150', 'min' => 1],
                'constraints' => [new Assert\NotBlank(message: 'Indiquez la durée.'), new Assert\Range(min: 1, max: 2880)],
            ]);
    }
}
