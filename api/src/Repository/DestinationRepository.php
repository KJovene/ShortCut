<?php

namespace App\Repository;

use App\Entity\Destination;
use App\Texte\Correspondance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Destination>
 */
class DestinationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Destination::class);
    }

    /**
     * Destinations publiées, par ville, avec leur climat chargé dans la même requête.
     *
     * @return list<Destination>
     */
    public function publiees(): array
    {
        /* @var list<Destination> */
        return $this->createQueryBuilder('d')
            ->leftJoin('d.periodesClimatiques', 'c')
            ->addSelect('c')
            ->andWhere('d.publiee = true')
            ->orderBy('d.ville', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * La destination publiée désignée par un slug ou une ville, sans tenir compte des majuscules ni des accents
     * (« Santorin » → santorin), ni d'une petite faute de frappe.
     */
    public function publieeCorrespondant(string $slugOuVille): ?Destination
    {
        foreach ($this->findBy(['publiee' => true]) as $destination) {
            if (Correspondance::normaliser($slugOuVille) === Correspondance::normaliser((string) $destination->getSlug())
                || Correspondance::correspondent($slugOuVille, (string) $destination->getVille())) {
                return $destination;
            }
        }

        return null;
    }
}
