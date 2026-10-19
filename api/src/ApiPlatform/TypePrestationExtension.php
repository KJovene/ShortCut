<?php

namespace App\ApiPlatform;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Prestation;
use App\Enum\TypePrestation;
use Doctrine\ORM\QueryBuilder;

/**
 * Les logements et les activités sont la même entité (Prestation) exposée sous deux adresses :
 * /api/logements ne renvoie que des logements, /api/activites que des activités, en liste comme en fiche.
 */
final class TypePrestationExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->filtrer($queryBuilder, $queryNameGenerator, $resourceClass, $operation);
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void
    {
        $this->filtrer($queryBuilder, $queryNameGenerator, $resourceClass, $operation);
    }

    private function filtrer(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation): void
    {
        $type = $operation?->getExtraProperties()['typePrestation'] ?? null;
        if (Prestation::class !== $resourceClass || !$type instanceof TypePrestation) {
            return;
        }

        $parametre = $queryNameGenerator->generateParameterName('type');
        $queryBuilder
            ->andWhere(\sprintf('%s.type = :%s', $queryBuilder->getRootAliases()[0], $parametre))
            ->setParameter($parametre, $type);
    }
}
