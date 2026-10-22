<?php

namespace App\Controller;

use App\Chatbot\Outil\EnregistrerExigences;
use App\Confirmation\PropositionClient;
use App\Entity\Demande;
use App\Entity\Proposition;
use App\Entity\Utilisateur;
use App\Enum\StatutProposition;
use App\Enum\TypeProposition;
use App\Repository\DemandeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * « Mon espace » sur le site : le client connecté suit ses demandes, leur proposition et ses conversations.
 * Il ne voit que ses propres demandes. Avant la première version envoyée par son conseiller, il retrouve le
 * récapitulatif préparé avec l'assistant ; ensuite, les brouillons restent côté conseiller.
 */
#[Route('/api/moi')]
final class EspaceClientController extends AbstractController
{
    #[Route('/demandes', name: 'api_moi_demandes', methods: ['GET'])]
    public function demandes(#[CurrentUser] Utilisateur $utilisateur, DemandeRepository $demandes): JsonResponse
    {
        return $this->json(array_map($this->demande(...), $demandes->findHistoriqueDuClient($utilisateur)));
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
                'etat' => match (true) {
                    TypeProposition::Confirmee === $proposition->getType() => 'confirmee',
                    StatutProposition::Brouillon === $proposition->getStatut() => 'preparee',
                    default => 'estimee',
                },
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

    /** Dernière version montrée au client (ni brouillon, ni remplacée), sinon son récapitulatif préparé. */
    private static function propositionVisible(Demande $demande): ?Proposition
    {
        $apercu = PropositionClient::apercu($demande);
        if (null !== $apercu) {
            return $apercu;
        }

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
