<?php

namespace App\Controller;

use App\Entity\Demande;
use App\Entity\Utilisateur;
use App\Security\Voter\DemandeVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Aiguillage des demandes (US06) : un conseiller s'attribue en un clic une demande arrivée sans conseiller,
 * et reprend la conversation du chatbot, qui cesse alors de répondre au client.
 */
#[Route('/admin/demandes')]
final class AiguillageController extends AbstractController
{
    /** Identifiant du jeton CSRF des actions « M'attribuer » et « Reprendre la conversation » (DemandeCrudController). */
    public const JETON = 'attribuer-demande';

    #[Route('/{id}/attribuer', name: 'admin_demande_attribuer', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function attribuer(
        Demande $demande,
        Request $request,
        #[CurrentUser] Utilisateur $utilisateur,
        EntityManagerInterface $em,
    ): Response {
        $this->verifierJeton($request);

        if (null !== ($refus = $this->dejaAttribuee($demande, $utilisateur))) {
            return $refus;
        }
        $this->denyAccessUnlessGranted(DemandeVoter::ATTRIBUER, $demande);

        $demande->setConseiller($utilisateur)->setModifieLe(new \DateTimeImmutable());
        // Prendre le dossier en charge, c'est aussi reprendre la conversation : le chatbot cesse de répondre.
        $reprise = $this->reprendreConversation($demande);
        $em->flush();

        $this->addFlash('success', $reprise
            ? \sprintf('La demande n° %d vous est attribuée : vous reprenez la conversation, le chatbot ne répond plus au client.', $demande->getId())
            : \sprintf('La demande n° %d vous est attribuée.', $demande->getId()));

        return $this->redirectToRoute('admin_demande_detail', ['entityId' => $demande->getId()]);
    }

    /** Une demande encore sans conseiller est attribuée au passage à celui qui reprend la conversation. */
    #[Route('/{id}/reprendre', name: 'admin_demande_reprendre', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function reprendre(
        Demande $demande,
        Request $request,
        #[CurrentUser] Utilisateur $utilisateur,
        EntityManagerInterface $em,
    ): Response {
        $this->verifierJeton($request);

        if (null === $demande->getConversation()) {
            throw $this->createNotFoundException('Cette demande n\'a pas de conversation.');
        }
        // L'administrateur peut reprendre la conversation d'une demande suivie par un autre conseiller.
        if (!$this->isGranted('ROLE_ADMIN') && null !== ($refus = $this->dejaAttribuee($demande, $utilisateur))) {
            return $refus;
        }
        if (null === $demande->getConseiller()) {
            $this->denyAccessUnlessGranted(DemandeVoter::ATTRIBUER, $demande);
            $demande->setConseiller($utilisateur);
        }
        $this->denyAccessUnlessGranted(DemandeVoter::MODIFIER, $demande);

        $this->reprendreConversation($demande);
        $demande->setModifieLe(new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('success', \sprintf('Vous reprenez la conversation de la demande n° %d : le chatbot ne répond plus au client.', $demande->getId()));

        return $this->redirectToRoute('admin_demande_detail', ['entityId' => $demande->getId()]);
    }

    /** Le conseiller prend la main sur la conversation ; vrai si elle n'était pas déjà reprise. */
    private function reprendreConversation(Demande $demande): bool
    {
        $conversation = $demande->getConversation();
        if (null === $conversation || $conversation->isRepriseParConseiller()) {
            return false;
        }

        $conversation->setRepriseParConseiller(true);
        if (null === $conversation->getFinLe()) {
            $conversation->setFinLe(new \DateTimeImmutable());
        }

        return true;
    }

    private function verifierJeton(Request $request): void
    {
        if (!$this->isCsrfTokenValid(self::JETON, $request->query->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }

    /** Deux conseillers ont cliqué en même temps : le premier garde la demande. */
    private function dejaAttribuee(Demande $demande, Utilisateur $utilisateur): ?Response
    {
        $conseiller = $demande->getConseiller();
        if (null === $conseiller || $conseiller === $utilisateur) {
            return null;
        }

        $this->addFlash('warning', \sprintf('La demande n° %d vient d\'être attribuée à %s.', $demande->getId(), $conseiller->getNom()));

        return $this->redirectToRoute('admin_demande_index', ['aiguiller' => 1]);
    }
}
