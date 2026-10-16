<?php

namespace App\Security\Voter;

use App\Entity\Conversation;
use App\Entity\Demande;
use App\Entity\Proposition;
use App\Entity\Utilisateur;
use App\Enum\Role;
use App\Security\VisiteurChatbot;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droits sur une demande et sur ce qui en dépend (conversation, propositions) :
 * - administrateur : toutes les demandes ;
 * - conseiller : les demandes qui lui sont attribuées ; il voit aussi les demandes non attribuées
 *   pour les aiguiller (se les attribuer ou les confier à un autre conseiller) ;
 * - chatbot : uniquement la demande de sa propre conversation, en lecture et en écriture.
 *
 * @extends Voter<string, Demande|Conversation|Proposition>
 */
final class DemandeVoter extends Voter
{
    public const VOIR = 'DEMANDE_VOIR';
    public const MODIFIER = 'DEMANDE_MODIFIER';
    /** Choisir le conseiller d'une demande (aiguillage). */
    public const ATTRIBUER = 'DEMANDE_ATTRIBUER';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VOIR, self::MODIFIER, self::ATTRIBUER], true)
            && ($subject instanceof Demande || $subject instanceof Conversation || $subject instanceof Proposition);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $demande = $subject instanceof Demande ? $subject : $subject->getDemande();
        if (null === $demande) {
            return false;
        }

        $user = $token->getUser();

        if ($user instanceof VisiteurChatbot) {
            return self::ATTRIBUER !== $attribute
                && null !== $demande->getId()
                && $demande->getId() === $user->getDemandeId();
        }

        if (!$user instanceof Utilisateur) {
            return false;
        }

        if (Role::Administrateur === $user->getRole()) {
            return true;
        }

        $conseiller = $demande->getConseiller();
        if (null === $conseiller) {
            // Demande à aiguiller : tout conseiller peut la voir et l'attribuer, pas la traiter.
            $vote?->addReason('Une demande non attribuée doit d\'abord être attribuée à un conseiller.');

            return self::MODIFIER !== $attribute;
        }

        $vote?->addReason('Un conseiller n\'accède qu\'aux demandes qui lui sont attribuées.');

        return $conseiller->getUserIdentifier() === $user->getUserIdentifier();
    }
}
