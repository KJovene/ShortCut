<?php

namespace App\Repository;

use App\Entity\Demande;
use App\Entity\Utilisateur;
use App\Enum\StatutDemande;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Le paramètre $conseiller restreint aux demandes qui lui sont attribuées ; null pour toutes (administrateur).
 *
 * @extends ServiceEntityRepository<Demande>
 */
class DemandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Demande::class);
    }

    /**
     * Nombre de demandes par statut, pour les onglets de la liste et le tableau de bord.
     *
     * @return array<string, int> indexé par la valeur du statut, chaque statut présent (0 par défaut)
     */
    public function compterParStatut(?Utilisateur $conseiller): array
    {
        $lignes = $this->duConseiller($conseiller)
            ->select('d.statut AS statut, COUNT(d.id) AS nombre')
            ->groupBy('d.statut')
            ->getQuery()
            ->getArrayResult();

        $compteurs = array_fill_keys(array_map(static fn (StatutDemande $s) => $s->value, StatutDemande::cases()), 0);
        foreach ($lignes as $ligne) {
            $statut = $ligne['statut'] instanceof StatutDemande ? $ligne['statut']->value : (string) $ligne['statut'];
            $compteurs[$statut] = (int) $ligne['nombre'];
        }

        return $compteurs;
    }

    public function compterPrioritaires(?Utilisateur $conseiller): int
    {
        return (int) $this->duConseiller($conseiller)
            ->select('COUNT(d.id)')
            ->andWhere('d.prioritaire = true')
            ->andWhere('d.statut NOT IN (:closes)')
            ->setParameter('closes', [StatutDemande::Acceptee, StatutDemande::Perdue])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Demandes qui attendent une action du conseiller : prioritaires, nouvelles ou à confirmer.
     * Les prioritaires d'abord, puis les plus anciennes (elles attendent depuis le plus longtemps).
     *
     * @return list<Demande>
     */
    public function aTraiter(?Utilisateur $conseiller, int $limite = 6): array
    {
        return $this->duConseiller($conseiller)
            ->andWhere('d.prioritaire = true OR d.statut IN (:aTraiter)')
            ->andWhere('d.statut NOT IN (:closes)')
            ->setParameter('aTraiter', [StatutDemande::Nouvelle, StatutDemande::AConfirmer])
            ->setParameter('closes', [StatutDemande::Acceptee, StatutDemande::Perdue])
            ->orderBy('d.prioritaire', 'DESC')
            ->addOrderBy('d.creeLe', 'ASC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
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

    private function duConseiller(?Utilisateur $conseiller): QueryBuilder
    {
        $qb = $this->createQueryBuilder('d');
        if (null !== $conseiller) {
            $qb->andWhere('d.conseiller = :conseiller')->setParameter('conseiller', $conseiller);
        }

        return $qb;
    }
}
