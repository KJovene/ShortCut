<?php

namespace App\Controller;

use App\Controller\Admin\DemandeCrudController;
use App\Entity\Proposition;
use App\Proposition\PropositionService;
use App\Security\Voter\DemandeVoter;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Le conseiller envoie au client le brouillon de proposition préparé par l'agent Rédaction (#207), depuis la fiche
 * demande du backoffice. Seul un conseiller qui peut modifier la demande le peut.
 */
#[Route('/admin/propositions')]
final class PropositionAdminController extends AbstractController
{
    #[Route('/{id}/envoyer', name: 'admin_proposition_envoyer', methods: ['POST'])]
    public function envoyer(Proposition $proposition, Request $request, PropositionService $propositions, AdminUrlGenerator $urls): Response
    {
        $demande = $proposition->getDemande() ?? throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted(DemandeVoter::MODIFIER, $demande);
        if (!$this->isCsrfTokenValid('envoyer-proposition-'.$proposition->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        try {
            $propositions->envoyer($proposition);
            $this->addFlash('success', \sprintf('La version %d de la proposition a été envoyée au client.', $proposition->getVersion()));
        } catch (\DomainException $e) {
            $this->addFlash('warning', $e->getMessage());
        }

        return $this->redirect($urls->setController(DemandeCrudController::class)->setAction(Action::DETAIL)->setEntityId($demande->getId())->generateUrl());
    }
}
