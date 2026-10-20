<?php

namespace App\Chatbot;

use App\Chatbot\Outil\ChercherActivites;
use App\Chatbot\Outil\ChercherDestinations;
use App\Chatbot\Outil\ChercherLogements;
use App\Chatbot\Outil\EnregistrerExigences;
use App\Chatbot\Outil\Outil;
use App\Chatbot\Outil\RetenirCombinaison;
use App\Chatbot\Outil\TransmettreAuConseiller;
use App\Entity\Conversation;
use App\Enum\AgentIA;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\ServerEvent;

/**
 * Agent Recherche (ticket #203, prompts/recherche.md) : à partir de la fiche demande confirmée, il cherche la
 * destination, les logements et les activités compatibles, retient la combinaison (retenir_combinaison, qui
 * revérifie tout côté serveur et chiffre les trois niveaux) et explique son choix au client.
 *
 * Il a son propre échange avec Claude, qui commence par la fiche demande ; seule sa réponse finale rejoint la
 * conversation, à la suite de celle de l'agent Conversation, pour que celui-ci sache ce qui a été proposé.
 *
 * Sans combinaison possible (ticket #204), il propose les ajustements calculés par chercher_destinations (autres
 * dates, durée, autres destinations) et transmet au conseiller une période sans données ; s'il échoue sans rien
 * dire au client, le serveur transmet lui-même la demande.
 */
final class AgentRecherche extends Agent
{
    /** Décision D22 : Sonnet 5.5 pour la recherche, le chiffrage et la rédaction. */
    public const MODELE = 'claude-sonnet-5-5';

    /**
     * @param iterable<Outil> $outils
     */
    public function __construct(
        ClientClaude $claude,
        EntityManagerInterface $em,
        LoggerInterface $logger,
        #[AutowireIterator(Outil::TAG)]
        iterable $outils,
        #[Autowire('%kernel.project_dir%/prompts/recherche.md')]
        string $fichierPrompt,
    ) {
        parent::__construct($claude, $em, $logger, $outils, $fichierPrompt);
    }

    /**
     * @return \Generator<int, ServerEvent>
     */
    public function proposer(Conversation $conversation): \Generator
    {
        $demande = $conversation->getDemande() ?? throw new \LogicException('Conversation sans demande.');
        $contexte = new ContexteAgent($demande, $conversation, AgentIA::Recherche);
        $tour = new Tour([['role' => 'user', 'content' => [['type' => 'text', 'text' => self::premierMessage($conversation)]]]]);

        // Un paragraphe à part, à la suite de la réponse de l'agent Conversation.
        yield self::evenement('texte', ['texte' => "\n\n"]);
        try {
            yield from $this->boucle($contexte, $tour, static function (): void {});

            // Ni combinaison, ni transmission, ni réponse (erreur de l'API, trop d'appels) : le client n'est jamais
            // laissé sans suite, la demande part chez un conseiller (ticket #204).
            if (null === $tour->sortie('retenir_combinaison') && null === $tour->sortie('transmettre_au_conseiller') && '' === trim($tour->reponse)) {
                $transmission = $this->outil('transmettre_au_conseiller')->executer($contexte, [
                    'motif' => 'autre',
                    'resume' => 'La recherche automatique n\'a pas abouti (erreur ou réponse incomplète de l\'agent Recherche) : la proposition est à construire à partir de la fiche demande.',
                ]);
                $tour->reponse = 'Je n\'ai pas pu terminer votre proposition. '.$transmission['messagePourLeClient'];
                yield self::evenement('texte', ['texte' => $tour->reponse]);
            }
        } finally {
            $this->ajouterALaConversation($conversation, $tour->reponse);
        }
    }

    /**
     * La fiche demande, et les messages du client : une activité ou un logement qu'il a nommé (« voir des dauphins »,
     * « dans un riad ») n'a pas de champ dans la fiche.
     */
    private static function premierMessage(Conversation $conversation): string
    {
        $demande = $conversation->getDemande() ?? throw new \LogicException('Conversation sans demande.');
        $messages = array_map(
            static fn (array $m): string => '- '.str_replace("\n", ' ', $m['contenu']),
            array_values(array_filter($conversation->getMessages(), static fn (array $m): bool => 'client' === $m['role'])),
        );

        return "Le client vient de confirmer sa demande.\n\nFiche demande :\n"
            .json_encode(EnregistrerExigences::fiche($demande), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES)
            ."\n\nMessages du client, dans l'ordre :\n".implode("\n", $messages);
    }

    protected function diffuserAuFilDeLEau(): bool
    {
        return false;
    }

    protected function agent(): AgentIA
    {
        return AgentIA::Recherche;
    }

    protected function modele(): string
    {
        return self::MODELE;
    }

    protected function effort(): string
    {
        return 'medium';
    }

    protected function outils(): array
    {
        return [ChercherDestinations::class, ChercherLogements::class, ChercherActivites::class, RetenirCombinaison::class, TransmettreAuConseiller::class];
    }

    /** Une destination, un appel de logements par étape, les activités, la combinaison (parfois deux fois), la réponse. */
    protected function appelsMax(): int
    {
        return 12;
    }

    /**
     * La réponse rejoint les messages affichés et, dans l'historique de l'agent Conversation, la fin de sa dernière
     * réponse : au message suivant du client, il sait ce qui a été proposé.
     */
    private function ajouterALaConversation(Conversation $conversation, string $reponse): void
    {
        if ('' === trim($reponse)) {
            return;
        }

        $conversation->ajouterMessage('assistant', trim($reponse));
        // L'historique est fait d'objets (Conversation::getHistorique()) : le dernier message est modifié en place.
        $historique = $conversation->getHistorique();
        $dernier = end($historique);
        if ($dernier instanceof \stdClass && 'assistant' === ($dernier->role ?? null) && \is_array($dernier->content ?? null)) {
            $dernier->content[] = (object) ['type' => 'text', 'text' => "\n\n".trim($reponse)];
            $conversation->setHistorique($historique);
        }
        $this->em->flush();
    }
}
