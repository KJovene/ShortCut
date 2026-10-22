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
            // Ville de départ d'un vol, ou aéroport d'une destination pour un transfert sur place.
            ->add('depart', LieuType::class, [
                'label' => 'Départ',
                'lieux' => 'transport',
                'constraints' => [new Assert\NotBlank(message: 'Indiquez la ville de départ.'), new Assert\Length(max: 100)],
            ])
            ->add('arrivee', LieuType::class, [
                'label' => 'Arrivée',
                'lieux' => 'arrivee',
                'constraints' => [new Assert\NotBlank(message: 'Indiquez la ville d\'arrivée.'), new Assert\Length(max: 150)],
            ])
            ->add('minCentimes', MoneyType::class, ['label' => 'Prix minimum (€)'] + $prix)
            ->add('maxCentimes', MoneyType::class, ['label' => 'Prix maximum (€)'] + $prix);
    }
}
