<?php

namespace App\Chatbot;

use App\Chatbot\Outil\ChercherActivites;
use App\Chatbot\Outil\ChercherDestinations;
use App\Chatbot\Outil\ChercherLogements;
use App\Chatbot\Outil\Outil;
use App\Chatbot\Outil\RetenirCombinaison;
use App\Chatbot\Outil\TransmettreAuConseiller;
use App\Demande\FicheDemande;
use App\Entity\Conversation;
use App\Entity\Demande;
use App\Enum\AgentIA;
use App\Enum\Langue;
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
 *
 * Le message de l'agent est suivi de la carte de la piste (Piste) : destination, niveaux, totaux, options. Le texte
 * explique et conseille ; la carte donne les chiffres.
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
     * @param bool $optionsChoisies le client a déjà répondu aux options d'une version précédente
     *
     * @return \Generator<int, ServerEvent, mixed, bool> vrai si la conversation attend une réponse du client (options,
     *                                                   ou ajustements quand rien ne convenait)
     */
    public function proposer(Conversation $conversation, ?Modification $modification = null, bool $optionsChoisies = false): \Generator
    {
        $demande = $conversation->getDemande() ?? throw new \LogicException('Conversation sans demande.');
        $contexte = new ContexteAgent($demande, $conversation, AgentIA::Recherche);
        $tour = new Tour([['role' => 'user', 'content' => [['type' => 'text', 'text' => self::premierMessage($conversation, $modification)]]]]);
        $transmise = false;

        // Un paragraphe à part, à la suite de la réponse de l'agent Conversation.
        yield self::evenement('texte', ['texte' => "\n\n"]);
        try {
            yield from $this->boucle($contexte, $tour, static function (): void {});

            // Ni combinaison, ni transmission, ni réponse (erreur de l'API, trop d'appels) : le client n'est jamais
            // laissé sans suite, la demande part chez un conseiller (ticket #204).
            if (null === $tour->sortie(RetenirCombinaison::NOM) && null === $tour->sortie(TransmettreAuConseiller::NOM) && '' === trim($tour->reponse)) {
                $transmission = $this->outil(TransmettreAuConseiller::NOM)->executer($contexte, [
                    'motif' => 'autre',
                    'resume' => 'La recherche automatique n\'a pas abouti (erreur ou réponse incomplète de l\'agent Recherche) : la proposition est à construire à partir de la fiche demande.',
                ]);
                $transmise = true;
                $tour->reponse = $demande->getLangue()->choisir('Je n\'ai pas pu terminer votre proposition. ', 'I could not finish your proposal. ')
                    .$transmission['messagePourLeClient'];
                yield self::evenement('texte', ['texte' => $tour->reponse]);
            }
        } finally {
            $this->ajouterALaConversation($conversation, $tour->reponse);
        }

        // Combinaison présentée au client : sa carte suit le message, puis l'agent Rédaction en fait le récapitulatif
        // complet (#207).
        $combinaison = $tour->sortie(RetenirCombinaison::NOM);
        if (null !== $combinaison && null === $tour->sortie(TransmettreAuConseiller::NOM)) {
            $carte = ['type' => 'piste'] + Piste::depuis($combinaison, $demande);
            $this->ajouterALaConversation($conversation, Piste::texte($carte, $demande->getLangue()), $carte);
            yield self::evenement('piste', $carte);

            return yield from $this->redaction->rediger($conversation, (string) $combinaison['chiffrageId'], $modification, [], $optionsChoisies);
        }

        // Rien ne convenait : le client répond aux ajustements proposés, sauf si la demande est passée à un conseiller.
        return !$transmise && null === $tour->sortie(TransmettreAuConseiller::NOM);
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
            .json_encode(FicheDemande::depuis($demande), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES)
            ."\n\nMessages du client, dans l'ordre :\n".self::messagesDuClient($conversation)
            .self::langue($demande);
    }

    /** Le client écrit en anglais (US15) : le message qu'il lit aussi, les critères des outils restent en français. */
    public static function langue(Demande $demande): string
    {
        return Langue::Anglais === $demande->getLangue()
            ? "\n\nLangue du client : anglais. Tout ce que le client lit s'écrit en anglais ; les critères passés aux outils restent en français, comme dans la fiche."
            : '';
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
