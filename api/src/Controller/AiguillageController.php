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
 * Aiguillage des demandes (US06) : un conseiller s'attribue en un clic une demande arrivée sans conseiller.
 * Il n'y a pas de messagerie entre conseiller et client : la conversation reste celle du chatbot.
 */
#[Route('/admin/demandes')]
final class AiguillageController extends AbstractController
{
    /** Identifiant du jeton CSRF de l'action « M'attribuer » (DemandeCrudController). */
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

        // Le chatbot reste ouvert au client pour des modifications légères de sa fiche (« Modifier le voyage »).
        $demande->setConseiller($utilisateur)->setModifieLe(new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('success', \sprintf('La demande n° %d vous est attribuée.', $demande->getId()));

        return $this->redirectToRoute('admin_demande_detail', ['entityId' => $demande->getId()]);
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
