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

    /**
     * Garde une traduction. Deux requêtes du site (toutes les destinations, celles mises en avant) peuvent traduire
     * le même texte en même temps : la seconde ne fait rien, au lieu d'échouer sur l'empreinte unique.
     */
    public function enregistrer(string $francais, string $anglais): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            'INSERT INTO traduction (empreinte, francais, anglais, cree_le) VALUES (:empreinte, :francais, :anglais, :creeLe)
             ON CONFLICT (empreinte) DO NOTHING',
            [
                'empreinte' => Traduction::empreinte($francais),
                'francais' => $francais,
                'anglais' => $anglais,
                'creeLe' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ],
        );
    }
}
