<?php

namespace App\Repository;

use App\Entity\Traduction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Traduction>
 */
class TraductionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Traduction::class);
    }

    /**
     * Traductions déjà connues, par empreinte du texte français.
     *
     * @param list<string> $empreintes
     *
     * @return array<string, string> texte anglais par empreinte
     */
    public function anglaisParEmpreinte(array $empreintes): array
    {
        if ([] === $empreintes) {
            return [];
        }

        /** @var list<array{empreinte: string, anglais: string}> $lignes */
        $lignes = $this->createQueryBuilder('t')
            ->select('t.empreinte', 't.anglais')
            ->where('t.empreinte IN (:empreintes)')
            ->setParameter('empreintes', $empreintes)
            ->getQuery()
            ->getArrayResult();

        return array_column($lignes, 'anglais', 'empreinte');
    }
}
