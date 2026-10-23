<?php

namespace App\ApiPlatform\Filtre;

use ApiPlatform\Doctrine\Common\Filter\OpenApiFilterTrait;
use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\BackwardCompatibleFilterDescriptionTrait;
use ApiPlatform\Metadata\OpenApiParameterFilterInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

/**
 * `?mois=AAAA-MM` : garde les fiches qui ont une période (début, fin) qui touche ce mois.
 * Sans $classePeriode, la ressource porte elle-même les dates (tarifs). Avec, il faut au moins une
 * période liée par $lien (ex. un logement disponible = au moins un tarif dans le mois).
 */
final class MoisFilter implements FilterInterface, OpenApiParameterFilterInterface
{
    use BackwardCompatibleFilterDescriptionTrait;
    use OpenApiFilterTrait;

    public const FORMAT = '/^\d{4}-(0[1-9]|1[0-2])$/';

    /**
     * @param class-string|null $classePeriode
     */
    public function __construct(
        private readonly ?string $classePeriode = null,
        private readonly ?string $lien = null,
    ) {
    }

    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $mois = $context['parameter']->getValue();
        if (!\is_string($mois) || !preg_match(self::FORMAT, $mois)) {
            return;
        }

        [$debut, $fin] = self::bornes($mois);
        $alias = $queryBuilder->getRootAliases()[0];
        $paramDebut = $queryNameGenerator->generateParameterName('debut');
        $paramFin = $queryNameGenerator->generateParameterName('fin');

        if (null === $this->classePeriode) {
            $queryBuilder->andWhere(\sprintf('%1$s.debut <= :%2$s AND %1$s.fin >= :%3$s', $alias, $paramFin, $paramDebut));
        } else {
            $periode = $queryNameGenerator->generateJoinAlias('periode');
            $sousRequete = \sprintf(
                'SELECT 1 FROM %1$s %2$s WHERE %2$s.%3$s = %4$s AND %2$s.debut <= :%5$s AND %2$s.fin >= :%6$s',
                $this->classePeriode, $periode, $this->lien, $alias, $paramFin, $paramDebut,
            );
            $queryBuilder->andWhere($queryBuilder->expr()->exists($sousRequete));
        }

        $queryBuilder->setParameter($paramDebut, $debut)->setParameter($paramFin, $fin);
    }

    /**
     * Premier et dernier jour du mois « AAAA-MM ».
     *
     * @return array{\DateTimeImmutable, \DateTimeImmutable}
     */
    private static function bornes(string $mois): array
    {
        $debut = new \DateTimeImmutable($mois.'-01');

        return [$debut, $debut->modify('last day of this month')];
    }
}
