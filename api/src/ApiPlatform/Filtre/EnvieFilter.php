<?php

namespace App\ApiPlatform\Filtre;

use ApiPlatform\Doctrine\Common\Filter\OpenApiFilterTrait;
use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\BackwardCompatibleFilterDescriptionTrait;
use ApiPlatform\Metadata\OpenApiParameterFilterInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Prestation;
use App\Texte\Correspondance;
use Doctrine\ORM\QueryBuilder;

/**
 * `?envie=plage` : prestations dont la liste `envies` contient cette envie. Sur les destinations
 * ($parDestination), garde celles qui ont au moins une prestation avec cette envie.
 * L'envie demandée est rapprochée du vocabulaire du catalogue (Correspondance) : « Detente » trouve « détente »,
 * « plages » trouve « plage ».
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
        $conditions = [];
        foreach (Correspondance::filtrer($envie, $this->vocabulaire($queryBuilder)) as $proche) {
            $parametre = $queryNameGenerator->generateParameterName('envie');
            $queryBuilder->setParameter($parametre, json_encode([$proche], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE));
            $conditions[] = \sprintf('JSON_CONTIENT(%%1$s.envies, :%s) = true', $parametre);
        }
        if ([] === $conditions) {
            $queryBuilder->andWhere('1 = 0');

            return;
        }

        $condition = '('.implode(' OR ', $conditions).')';
        if (!$this->parDestination) {
            $queryBuilder->andWhere(\sprintf($condition, $alias));

            return;
        }

        $prestation = $queryNameGenerator->generateJoinAlias('prestation');
        $queryBuilder->andWhere($queryBuilder->expr()->exists(\sprintf(
            'SELECT 1 FROM %s %s WHERE %s.destination = %s AND %s',
            Prestation::class, $prestation, $prestation, $alias, \sprintf($condition, $prestation),
        )));
    }

    /**
     * Toutes les envies du catalogue (quelques dizaines).
     *
     * @return list<string>
     */
    private function vocabulaire(QueryBuilder $queryBuilder): array
    {
        /** @var list<string> $envies */
        $envies = $queryBuilder->getEntityManager()->getConnection()
            ->executeQuery('SELECT DISTINCT jsonb_array_elements_text(CAST(envies AS jsonb)) FROM prestation')
            ->fetchFirstColumn();

        return $envies;
    }
}
