<?php

namespace App\Controller;

use App\Chatbot\Outil\EnregistrerExigences;
use App\Entity\Demande;
use App\Entity\Proposition;
use App\Entity\Utilisateur;
use App\Enum\StatutDemande;
use App\Enum\StatutProposition;
use App\Enum\TypeProposition;
use App\Repository\DemandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * « Mon espace » sur le site : le client connecté suit ses demandes, leur proposition et ses conversations.
 * Il ne voit que ses propres demandes ; les brouillons de proposition restent côté conseiller.
 */
#[Route('/api/moi')]
final class EspaceClientController extends AbstractController
{
    #[Route('/demandes', name: 'api_moi_demandes', methods: ['GET'])]
    public function demandes(#[CurrentUser] Utilisateur $utilisateur, DemandeRepository $demandes): JsonResponse
    {
        return $this->json(array_map($this->demande(...), $demandes->findHistoriqueDuClient($utilisateur)));
    }

    /**
     * « Supprimer le voyage » : la demande disparaît, avec sa conversation et ses propositions, et libère une place
     * parmi les voyages en cours. Un voyage accepté est réservé : il ne se supprime plus (409).
     */
    #[Route('/demandes/{id}', name: 'api_moi_demande_suppression', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function supprimer(int $id, #[CurrentUser] Utilisateur $utilisateur, DemandeRepository $demandes, EntityManagerInterface $em): Response
    {
        $demande = $demandes->find($id);
        // La demande d'un autre client répond comme une demande inexistante.
        if (null === $demande || $demande->getClient() !== $utilisateur) {
            throw $this->createNotFoundException('Demande introuvable.');
        }
        if (StatutDemande::Acceptee === $demande->getStatut()) {
            return $this->json(['erreur' => 'Ce voyage est réservé : contactez votre conseiller pour l\'annuler.'], Response::HTTP_CONFLICT);
        }

        $em->remove($demande);
        $em->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /** @return array<string, mixed> */
    private function demande(Demande $demande): array
    {
        $conseiller = $demande->getConseiller();
        $conversation = $demande->getConversation();
        $proposition = self::propositionVisible($demande);

        return [
            'id' => $demande->getId(),
            'statut' => $demande->getStatut()->value,
            'typeDeVoyage' => $demande->getTypeDeVoyage()?->value,
            'lieuOuZone' => $demande->getLieuOuZone(),
            'dateDebut' => $demande->getDateDebut()?->format('Y-m-d'),
            'nombreDeJours' => $demande->getNombreDeJours(),
            'adultes' => $demande->getAdultes(),
            'enfants' => $demande->getEnfants(),
            'creeLe' => $demande->getCreeLe()->format(\DATE_ATOM),
            'conseiller' => null === $conseiller ? null : ['prenom' => $conseiller->getPrenom(), 'nom' => $conseiller->getNom()],
            // Le chatbot a passé la main à un conseiller : la demande ne se modifie plus avec l'assistant.
            'transmiseLe' => $demande->getTransmiseLe()?->format(\DATE_ATOM),
            'proposition' => null === $proposition ? null : [
                // Lien de la page « Ma proposition ».
                'jeton' => $proposition->getJetonPublic(),
                'version' => $proposition->getVersion(),
                'etat' => TypeProposition::Confirmee === $proposition->getType() ? 'confirmee' : 'estimee',
                'statut' => $proposition->getStatut()->value,
                'niveauBudget' => $proposition->getNiveauBudget()->label(),
                'totalCentimes' => $proposition->getTotalCentimes(),
                'totalParPersonneCentimes' => $proposition->getTotalParPersonneCentimes(),
            ],
            'conversation' => null === $conversation ? null : [
                // « Modifier le voyage » : le client reprend sa conversation dans le chat, sur n'importe quel
                // navigateur. Le jeton n'est remis qu'au client de la demande.
                'jeton' => $conversation->getJeton(),
                // Un conseiller a repris la main : le chatbot ne répond plus, le client passe par un rendez-vous.
                'repriseParConseiller' => $conversation->isRepriseParConseiller(),
                'debutLe' => $conversation->getDebutLe()->format(\DATE_ATOM),
                'messages' => $conversation->getMessages(),
                // Fiche au format du chat (événement « demande »), pour son récapitulatif.
                'fiche' => EnregistrerExigences::fiche($demande),
            ],
        ];
    }

    /** Dernière version montrée au client : ni brouillon, ni remplacée. */
    private static function propositionVisible(Demande $demande): ?Proposition
    {
        $visible = null;
        foreach ($demande->getPropositions() as $proposition) {
            if (\in_array($proposition->getStatut(), [StatutProposition::Brouillon, StatutProposition::Remplacee], true)) {
                continue;
            }
            if (null === $visible || $proposition->getVersion() > $visible->getVersion()) {
                $visible = $proposition;
            }
        }

        return $visible;
    }
}
