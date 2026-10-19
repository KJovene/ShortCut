<?php

namespace App\ApiPlatform\Filtre;

use ApiPlatform\Doctrine\Common\Filter\OpenApiFilterTrait;
use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\BackwardCompatibleFilterDescriptionTrait;
use ApiPlatform\Metadata\OpenApiParameterFilterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ParameterNotFound;
use App\Entity\PeriodeClimatique;
use Doctrine\ORM\QueryBuilder;

/**
 * `?mois=AAAA-MM&temperatureMin=25&temperatureMax=30` : destinations dont une même période climatique
 * touche le mois et a une température moyenne dans les bornes. Les trois paramètres forment une seule
 * condition : elle est ajoutée une fois, au passage du premier paramètre renseigné.
 */
final class ClimatFilter implements FilterInterface, OpenApiParameterFilterInterface
{
    use BackwardCompatibleFilterDescriptionTrait;
    use OpenApiFilterTrait;

    private const PARAMETRES = ['mois', 'temperatureMin', 'temperatureMax'];

    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $valeurs = [];
        foreach (self::PARAMETRES as $cle) {
            $valeur = $operation?->getParameters()?->get($cle)?->getValue();
            if (null !== $valeur && '' !== $valeur && !$valeur instanceof ParameterNotFound) {
                $valeurs[$cle] = $valeur;
            }
        }
        if (array_key_first($valeurs) !== $context['parameter']->getKey()) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $periode = $queryNameGenerator->generateJoinAlias('climat');
        $conditions = [\sprintf('%s.destination = %s', $periode, $alias)];
        $parametres = [];

        if (isset($valeurs['mois']) && \is_string($valeurs['mois']) && preg_match(MoisFilter::FORMAT, $valeurs['mois'])) {
            [$debut, $fin] = MoisFilter::bornes($valeurs['mois']);
            $parametres[$queryNameGenerator->generateParameterName('debut')] = $debut;
            $parametres[$queryNameGenerator->generateParameterName('fin')] = $fin;
            [$paramDebut, $paramFin] = array_keys($parametres);
            $conditions[] = \sprintf('%1$s.debut <= :%2$s AND %1$s.fin >= :%3$s', $periode, $paramFin, $paramDebut);
        }
        foreach (['temperatureMin' => '>=', 'temperatureMax' => '<='] as $cle => $operateur) {
            if (isset($valeurs[$cle]) && is_numeric($valeurs[$cle])) {
                $nom = $queryNameGenerator->generateParameterName($cle);
                $parametres[$nom] = (float) $valeurs[$cle];
                $conditions[] = \sprintf('%s.temperatureMoyenne %s :%s', $periode, $operateur, $nom);
            }
        }

        $queryBuilder->andWhere($queryBuilder->expr()->exists(\sprintf(
            'SELECT 1 FROM %s %s WHERE %s',
            PeriodeClimatique::class, $periode, implode(' AND ', $conditions),
        )));
        foreach ($parametres as $nom => $valeur) {
            $queryBuilder->setParameter($nom, $valeur);
        }
    }
}
