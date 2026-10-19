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

/** Aiguillage des demandes (US06) : un conseiller s'attribue en un clic une demande arrivée sans conseiller. */
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
        if (!$this->isCsrfTokenValid(self::JETON, $request->query->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        // Deux conseillers ont cliqué en même temps : le premier garde la demande.
        if (null !== ($conseiller = $demande->getConseiller()) && $conseiller !== $utilisateur) {
            $this->addFlash('warning', \sprintf('La demande n° %d vient d\'être attribuée à %s.', $demande->getId(), $conseiller->getNom()));

            return $this->redirectToRoute('admin_demande_index', ['aiguiller' => 1]);
        }

        $this->denyAccessUnlessGranted(DemandeVoter::ATTRIBUER, $demande);

        $demande->setConseiller($utilisateur)->setModifieLe(new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('success', \sprintf('La demande n° %d vous est attribuée.', $demande->getId()));

        return $this->redirectToRoute('admin_demande_detail', ['entityId' => $demande->getId()]);
    }
}
