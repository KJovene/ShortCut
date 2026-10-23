<?php

namespace App\EventListener;

use App\Entity\SaisieTracee;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Trace qui a saisi ou modifié un tarif ou un climat, et quand, quel que soit le chemin (API, backoffice).
 * Seules les créations et les modifications réelles comptent : un tarif inchangé garde son auteur.
 * Sans utilisateur connecté (fixtures), l'auteur déjà renseigné est conservé.
 */
#[AsDoctrineListener(event: Events::onFlush)]
final class TraceDeSaisieListener
{
    public function __construct(
        private readonly Security $security,
        private readonly ClockInterface $horloge,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();
        $utilisateur = $this->security->getUser();

        foreach ([...$uow->getScheduledEntityInsertions(), ...$uow->getScheduledEntityUpdates()] as $entite) {
            if (!$entite instanceof SaisieTracee) {
                continue;
            }
            $entite->setModifieLe($this->horloge->now());
            if ($utilisateur instanceof Utilisateur) {
                $entite->setSaisiPar($utilisateur);
            }
            $uow->recomputeSingleEntityChangeSet($em->getClassMetadata($entite::class), $entite);
        }
    }
}
