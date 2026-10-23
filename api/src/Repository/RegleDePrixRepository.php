<?php

namespace App\Repository;

use App\Entity\RegleDePrix;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RegleDePrix>
 */
class RegleDePrixRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RegleDePrix::class);
    }

    /** Les règles de prix fixées par l'administrateur ; les valeurs par défaut (D16, D21) tant qu'il n'y en a pas. */
    public function courante(): RegleDePrix
    {
        return $this->findOneBy([], ['id' => 'ASC']) ?? new RegleDePrix();
    }
}
