<?php

namespace App\Repository;

use App\Entity\Destination;
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

    /**
     * @return list<array{debut: \DateTimeImmutable, fin: \DateTimeImmutable}>
     */
    public function findUncoveredMonths(Destination $destination, \DateTimeImmutable $from, int $months = 12): array
    {
        $cursor = $from->modify('first day of this month');
        $end = $cursor->modify(sprintf('+%d months', $months - 1));
        $to = $end->modify('last day of this month');
        /** @var list<PeriodeClimatique> $covered */
        $covered = $this->createQueryBuilder('periode')
            ->andWhere('periode.destination = :destination')
            ->andWhere('periode.debut <= :to')
            ->andWhere('periode.fin >= :from')
            ->setParameter('destination', $destination)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('periode.debut', 'ASC')
            ->getQuery()
            ->getResult();

        $missing = [];
        while ($cursor <= $end) {
            $monthEnd = $cursor->modify('last day of this month');
            $coveredByPeriod = false;
            foreach ($covered as $periode) {
                if ($periode->getDebut() <= $monthEnd && $periode->getFin() >= $cursor) {
                    $coveredByPeriod = true;
                    break;
                }
            }
            if (!$coveredByPeriod) {
                $missing[] = ['debut' => $cursor, 'fin' => $monthEnd];
            }
            $cursor = $cursor->modify('+1 month');
        }

        return $missing;
    }
}
