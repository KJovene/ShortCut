<?php

namespace App\ApiPlatform;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

/**
 * Opérations publiques du site (extraProperties `publiees`) : seules les fiches publiées sont lues,
 * en liste comme en fiche. Une fiche masquée répond donc 404, comme une fiche inconnue.
 */
final class PublieeExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public const PUBLIEES = 'publiees';

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->filtrer($queryBuilder, $queryNameGenerator, $operation);
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void
    {
        $this->filtrer($queryBuilder, $queryNameGenerator, $operation);
    }

    private function filtrer(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, ?Operation $operation): void
    {
        if (true !== ($operation?->getExtraProperties()[self::PUBLIEES] ?? false)) {
            return;
        }

        $parametre = $queryNameGenerator->generateParameterName('publiee');
        $queryBuilder
            ->andWhere(\sprintf('%s.publiee = :%s', $queryBuilder->getRootAliases()[0], $parametre))
            ->setParameter($parametre, true);
    }
}
