<?php

namespace App\Repository;

use App\Entity\Demande;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Demande>
 */
class DemandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Demande::class);
    }

    /**
     * Historique d'un client pour sa fiche : ses demandes, les plus récentes d'abord, avec leur conversation.
     *
     * @return list<Demande>
     */
    public function findHistoriqueDuClient(Utilisateur $client): array
    {
        /** @var list<Demande> */
        return $this->createQueryBuilder('d')
            ->addSelect('conversation', 'conseiller')
            ->leftJoin('d.conversation', 'conversation')
            ->leftJoin('d.conseiller', 'conseiller')
            ->where('d.client = :client')
            ->setParameter('client', $client)
            ->orderBy('d.creeLe', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
