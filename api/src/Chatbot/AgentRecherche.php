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
 *
 * Après une modification du client (ticket #211, NouvelleVersion), il part de la combinaison de la version précédente
 * et ne cherche que ce que le changement touche ; la rédaction écrit alors la version suivante.
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
        private readonly AgentRedaction $redaction,
    ) {
        parent::__construct($claude, $em, $logger, $outils, $fichierPrompt);
    }

    /**
     * @return \Generator<int, ServerEvent>
     */
    public function proposer(Conversation $conversation, ?Modification $modification = null): \Generator
    {
        $demande = $conversation->getDemande() ?? throw new \LogicException('Conversation sans demande.');
        $contexte = new ContexteAgent($demande, $conversation, AgentIA::Recherche);
        $tour = new Tour([['role' => 'user', 'content' => [['type' => 'text', 'text' => self::premierMessage($conversation, $modification)]]]]);

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

        // Combinaison présentée au client : l'agent Rédaction en fait la proposition complète (#207).
        $combinaison = $tour->sortie('retenir_combinaison');
        if (null !== $combinaison && null === $tour->sortie('transmettre_au_conseiller')) {
            yield from $this->redaction->rediger($conversation, (string) $combinaison['chiffrageId'], $modification);
        }
    }

    /**
     * La fiche demande, et les messages du client : une activité ou un logement qu'il a nommé (« voir des dauphins »,
     * « dans un riad ») n'a pas de champ dans la fiche.
     */
    private static function premierMessage(Conversation $conversation, ?Modification $modification): string
    {
        $demande = $conversation->getDemande() ?? throw new \LogicException('Conversation sans demande.');

        return (null === $modification ? 'Le client vient de confirmer sa demande.' : $modification->pourLaRecherche())
            ."\n\nFiche demande :\n"
            .json_encode(EnregistrerExigences::fiche($demande), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES)
            ."\n\nMessages du client, dans l'ordre :\n".self::messagesDuClient($conversation);
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
}
