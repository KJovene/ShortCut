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

    /** Demandes à aiguiller, communes à tous les conseillers (US06). */
    public function compterAAiguiller(): int
    {
        return (int) $this->restreindreAAiguiller($this->createQueryBuilder('d'), 'd')
            ->select('COUNT(d.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Demandes arrivées sans conseiller et encore ouvertes (US06). Une demande ouverte par le chatbot n'arrive
     * qu'une fois le récapitulatif confirmé ou un conseiller demandé : avant, ce n'est qu'une conversation
     * en cours, souvent abandonnée. Une demande saisie au backoffice arrive dès sa création.
     */
    public function restreindreAAiguiller(QueryBuilder $qb, string $alias): QueryBuilder
    {
        return $qb
            ->leftJoin($alias.'.conversation', 'conversationAAiguiller')
            ->andWhere($alias.'.conseiller IS NULL')
            ->andWhere($alias.'.statut NOT IN (:closesAAiguiller)')
            ->andWhere(\sprintf('%s.prioritaire = true OR conversationAAiguiller.id IS NULL OR conversationAAiguiller.finLe IS NOT NULL', $alias))
            ->setParameter('closesAAiguiller', [StatutDemande::Acceptee, StatutDemande::Perdue]);
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
     * Voyages en cours du client (Demande::estEnCours), sans compter $sauf : la demande qu'on veut lui rattacher.
     */
    public function compterEnCoursDuClient(Utilisateur $client, ?Demande $sauf = null): int
    {
        $qb = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.client = :client')
            ->andWhere('d.statut NOT IN (:termines)')
            ->setParameter('client', $client)
            ->setParameter('termines', [StatutDemande::Acceptee, StatutDemande::Perdue]);
        if (null !== $sauf?->getId()) {
            $qb->andWhere('d.id <> :sauf')->setParameter('sauf', $sauf->getId());
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Historique d'un client pour sa fiche : ses demandes, les plus récentes d'abord, avec leur conversation.
     *
     * @return list<Demande>
     */
    public function findHistoriqueDuClient(Utilisateur $client): array
    {
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
