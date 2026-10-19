<?php

namespace App\ApiPlatform\Filtre;

use ApiPlatform\Doctrine\Common\Filter\OpenApiFilterTrait;
use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\BackwardCompatibleFilterDescriptionTrait;
use ApiPlatform\Metadata\OpenApiParameterFilterInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Prestation;
use Doctrine\ORM\QueryBuilder;

/**
 * `?envie=plage` : prestations dont la liste `envies` contient cette envie. Sur les destinations
 * ($parDestination), garde celles qui ont au moins une prestation avec cette envie.
 */
final class EnvieFilter implements FilterInterface, OpenApiParameterFilterInterface
{
    use BackwardCompatibleFilterDescriptionTrait;
    use OpenApiFilterTrait;

    public function __construct(private readonly bool $parDestination = false)
    {
    }

    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $envie = $context['parameter']->getValue();
        if (!\is_string($envie) || '' === trim($envie)) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $parametre = $queryNameGenerator->generateParameterName('envie');
        $queryBuilder->setParameter($parametre, json_encode([mb_strtolower(trim($envie))], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE));

        if (!$this->parDestination) {
            $queryBuilder->andWhere(\sprintf('JSON_CONTIENT(%s.envies, :%s) = true', $alias, $parametre));

            return;
        }

        $prestation = $queryNameGenerator->generateJoinAlias('prestation');
        $queryBuilder->andWhere($queryBuilder->expr()->exists(\sprintf(
            'SELECT 1 FROM %1$s %2$s WHERE %2$s.destination = %3$s AND JSON_CONTIENT(%2$s.envies, :%4$s) = true',
            Prestation::class, $prestation, $alias, $parametre,
        )));
    }
}
