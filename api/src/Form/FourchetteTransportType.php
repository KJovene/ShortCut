<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Une fourchette de prix d'un transporteur : trajet et prix par personne, saisis en euros, stockés en centimes.
 *
 * @extends AbstractType<array{depart: ?string, arrivee: ?string, minCentimes: ?int, maxCentimes: ?int}>
 */
final class FourchetteTransportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $prix = [
            'currency' => 'EUR',
            'divisor' => 100,
            'input' => 'integer',
            'constraints' => [new Assert\NotBlank(message: 'Indiquez le prix.'), new Assert\PositiveOrZero()],
        ];

        $builder
            ->add('depart', VilleType::class, [
                'label' => 'Départ',
                'suggestions' => 'departs',
                'attr' => ['placeholder' => 'Paris'],
                'constraints' => [new Assert\NotBlank(message: 'Indiquez la ville de départ.'), new Assert\Length(max: 100)],
            ])
            ->add('arrivee', VilleType::class, [
                'label' => 'Arrivée',
                'suggestions' => 'destinations',
                'help' => 'La ville de la destination, à choisir dans la liste. Une variante (majuscules, accents, petite faute) est aussi reconnue.',
                'attr' => ['placeholder' => 'Lisbonne'],
                'constraints' => [new Assert\NotBlank(message: 'Indiquez la ville d\'arrivée.'), new Assert\Length(max: 150)],
            ])
            ->add('minCentimes', MoneyType::class, ['label' => 'Prix minimum par personne'] + $prix)
            ->add('maxCentimes', MoneyType::class, ['label' => 'Prix maximum par personne'] + $prix);
    }
}
