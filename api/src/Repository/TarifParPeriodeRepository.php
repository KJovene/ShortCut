<?php

namespace App\Repository;

use App\Entity\TarifParPeriode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TarifParPeriode>
 */
class TarifParPeriodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TarifParPeriode::class);
    }

    public function findOverlap(TarifParPeriode $tarif): ?TarifParPeriode
    {
        $query = $this->createQueryBuilder('tarif')
            ->join('tarif.prestation', 'prestation')
            ->andWhere('tarif.prestation = :prestation')
            ->andWhere('tarif.debut <= :fin')
            ->andWhere('tarif.fin >= :debut')
            ->setParameter('prestation', $tarif->getPrestation())
            ->setParameter('debut', $tarif->getDebut())
            ->setParameter('fin', $tarif->getFin())
            ->setMaxResults(1);

        if (null !== $tarif->getId()) {
            $query->andWhere('tarif.id != :id')->setParameter('id', $tarif->getId());
        }

        return $query->getQuery()->getOneOrNullResult();
    }
}
