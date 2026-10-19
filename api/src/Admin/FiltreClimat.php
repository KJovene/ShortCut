<?php

namespace App\Admin;

use App\Entity\PeriodeClimatique;
use App\Form\FiltreClimatType;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;

/**
 * Filtre « Climat » des destinations (#186) : « mai, au moins 25 °C » garde les destinations dont une même
 * période climatique couvre mai et a une température moyenne d'au moins 25 °C. Le mois vaut pour toutes les années.
 */
final class FiltreClimat implements FilterInterface
{
    use FilterTrait;

    public static function new(string $propertyName = 'climat', ?string $label = 'Climat'): self
    {
        return (new self())
            ->setFilterFqcn(self::class)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(FiltreClimatType::class);
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $mois = $filterDataDto->getValue();
        $temperature = $filterDataDto->getValue2();
        if (null === $mois && null === $temperature) {
            return;
        }

        $periode = 'climat_'.$filterDataDto->getParameterName();
        $conditions = [\sprintf('%s.destination = %s', $periode, $filterDataDto->getEntityAlias())];
        if (null !== $mois) {
            $conditions[] = \sprintf('MOIS_COUVERT(%1$s.debut, %1$s.fin, :%1$s_mois) = 1', $periode);
            $queryBuilder->setParameter($periode.'_mois', (int) $mois);
        }
        if (null !== $temperature) {
            $conditions[] = \sprintf('%1$s.temperatureMoyenne >= :%1$s_temperature', $periode);
            $queryBuilder->setParameter($periode.'_temperature', (float) $temperature);
        }

        $queryBuilder->andWhere($queryBuilder->expr()->exists(\sprintf(
            'SELECT 1 FROM %s %s WHERE %s',
            PeriodeClimatique::class, $periode, implode(' AND ', $conditions),
        )));
    }
}
