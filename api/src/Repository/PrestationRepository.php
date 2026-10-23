<?php

namespace App\Repository;

use App\Entity\Destination;
use App\Entity\Prestation;
use App\Enum\TypePrestation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Prestation>
 */
class PrestationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Prestation::class);
    }

    /**
     * Prestations de ces destinations, par nom, avec leur prestataire et tous leurs tarifs chargés dans la même
     * requête. Les tarifs ne sont pas filtrés en SQL : une collection partielle resterait dans l'unité de travail
     * et fausserait un chiffrage fait ensuite dans la même requête HTTP.
     *
     * @param list<Destination> $destinations
     *
     * @return list<Prestation>
     */
    public function duCatalogue(array $destinations, ?TypePrestation $type = null): array
    {
        if ([] === $destinations) {
            return [];
        }

        $requete = $this->createQueryBuilder('p')
            ->join('p.prestataire', 'prestataire')
            ->addSelect('prestataire')
            ->leftJoin('p.tarifs', 't')
            ->addSelect('t')
            ->andWhere('p.destination IN (:destinations)')
            ->setParameter('destinations', $destinations)
            ->orderBy('p.nom', 'ASC');
        if (null !== $type) {
            $requete->andWhere('p.type = :type')->setParameter('type', $type);
        }

        /* @var list<Prestation> */
        return $requete->getQuery()->getResult();
    }
}
