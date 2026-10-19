<?php

namespace App\Admin;

use App\Entity\Destination;
use App\Entity\Prestataire;
use App\Entity\Prestation;
use App\Texte\Correspondance;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

/**
 * Filtre « Destination » des prestataires (#187) : ceux qui ont un logement ou une activité dans la destination,
 * et les transporteurs qui ont une fourchette de prix vers sa ville. La ville d'arrivée d'une fourchette est
 * saisie à la main : elle est rapprochée de la ville de la destination avec Correspondance (casse, accents, faute).
 */
final class FiltreDestinationPrestataire implements FilterInterface
{
    use FilterTrait;

    public static function new(string $propertyName = 'destination', ?string $label = 'Destination'): self
    {
        return (new self())
            ->setFilterFqcn(self::class)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(EntityType::class)
            ->setFormTypeOptions([
                'class' => Destination::class,
                'choice_label' => static fn (Destination $d): string => \sprintf('%s (%s)', $d->getVille(), $d->getPays()),
                'query_builder' => static fn (EntityRepository $repo) => $repo->createQueryBuilder('d')->orderBy('d.ville', 'ASC'),
                'placeholder' => 'Choisir une destination',
            ]);
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $destination = $filterDataDto->getValue();
        if (!$destination instanceof Destination) {
            return;
        }

        $alias = $filterDataDto->getEntityAlias();
        $parametre = $filterDataDto->getParameterName();
        $sousRequete = \sprintf(
            'SELECT 1 FROM %1$s p_%2$s WHERE p_%2$s.prestataire = %3$s AND p_%2$s.destination = :%2$s',
            Prestation::class, $parametre, $alias,
        );

        $queryBuilder
            ->andWhere($queryBuilder->expr()->orX(
                $queryBuilder->expr()->exists($sousRequete),
                \sprintf('%s.id IN (:%s_transporteurs)', $alias, $parametre),
            ))
            ->setParameter($parametre, $destination)
            ->setParameter($parametre.'_transporteurs', $this->transporteursVers($queryBuilder, (string) $destination->getVille()));
    }

    /**
     * Identifiants des prestataires qui ont une fourchette vers cette ville. Le rapprochement se fait en PHP :
     * une agence a quelques dizaines de transporteurs.
     *
     * @return list<int>
     */
    private function transporteursVers(QueryBuilder $queryBuilder, string $ville): array
    {
        $ids = [];
        $prestataires = $queryBuilder->getEntityManager()->getRepository(Prestataire::class)->findAll();
        foreach ($prestataires as $prestataire) {
            $arrivees = array_column($prestataire->getFourchettesTransport(), 'arrivee');
            if ([] !== Correspondance::filtrer($ville, $arrivees) && null !== $prestataire->getId()) {
                $ids[] = $prestataire->getId();
            }
        }

        // IN () vide est invalide en SQL : un identifiant impossible garde la condition fausse.
        return [] === $ids ? [0] : $ids;
    }
}
