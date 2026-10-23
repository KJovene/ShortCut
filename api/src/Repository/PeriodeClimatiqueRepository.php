<?php

namespace App\Repository;

use App\Entity\PeriodeClimatique;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PeriodeClimatique>
 */
class PeriodeClimatiqueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PeriodeClimatique::class);
    }

    public function findOverlap(PeriodeClimatique $periode): ?PeriodeClimatique
    {
        $query = $this->createQueryBuilder('periode')
            ->andWhere('periode.destination = :destination')
            ->andWhere('periode.debut <= :fin')
            ->andWhere('periode.fin >= :debut')
            ->setParameter('destination', $periode->getDestination())
            ->setParameter('debut', $periode->getDebut())
            ->setParameter('fin', $periode->getFin())
            ->setMaxResults(1);

        if (null !== $periode->getId()) {
            $query->andWhere('periode.id != :id')->setParameter('id', $periode->getId());
        }

        return $query->getQuery()->getOneOrNullResult();
    }
}
