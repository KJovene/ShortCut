<?php

namespace App\Chatbot;

use App\Chatbot\Outil\ChercherDestinations;
use App\Chatbot\Outil\EnregistrerExigences;
use App\Chatbot\Outil\Outil;
use App\Chatbot\Outil\TransmettreAuConseiller;
use App\Entity\Conversation;
use App\Entity\Demande;
use App\Enum\AgentIA;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\ServerEvent;

/**
 * Agent Conversation : répond au client en streaming et recueille ses exigences (prompts/conversation.md).
 * Quand le client confirme son récapitulatif, l'agent Recherche prend le relais dans la même réponse : il choisit
 * la combinaison et l'explique au client. Les événements envoyés au site sont décrits dans Agent ; la réponse se
 * termine par « fin » {}.
 */
final class AgentConversation extends Agent
{
    /** Décision D22 : Haiku 5.5 pour l'agent Conversation (prompts/README.md). */
    public const MODELE = 'claude-haiku-5-5';
    /** Réponse fixe une fois la conversation reprise par un conseiller. */
    public const MESSAGE_REPRISE = 'Votre demande est entre les mains d\'un conseiller de l\'agence : il a bien reçu votre message et vous recontacte sous 2 jours ouvrés.';

    /**
     * @param iterable<Outil> $outils
     */
    public function __construct(
        ClientClaude $claude,
        EntityManagerInterface $em,
        LoggerInterface $logger,
        #[AutowireIterator(Outil::TAG)]
        iterable $outils,
        #[Autowire('%kernel.project_dir%/prompts/conversation.md')]
        string $fichierPrompt,
        private readonly AgentRecherche $recherche,
        private readonly NouvelleVersion $nouvelleVersion,
    ) {
        parent::__construct($claude, $em, $logger, $outils, $fichierPrompt);
    }

    /**
     * @return \Generator<int, ServerEvent>
     */
    public function repondre(Conversation $conversation, string $message): \Generator
    {
        $demande = $conversation->getDemande() ?? throw new \LogicException('Conversation sans demande.');

        // Un conseiller a repris la conversation (US06) : le message lui est gardé, le chatbot ne répond plus.
        if ($conversation->isRepriseParConseiller()) {
            $conversation->ajouterMessage('client', $message);
            $conversation->ajouterMessage('assistant', self::MESSAGE_REPRISE);
            $this->em->flush();

            yield self::evenement('texte', ['texte' => self::MESSAGE_REPRISE]);
            yield self::evenement('fin', new \stdClass());

            return;
        }

        $contexte = new ContexteAgent($demande, $conversation, AgentIA::Conversation);

        $tour = new Tour($conversation->getHistorique());
        $tour->historique[] = ['role' => 'user', 'content' => [
            ['type' => 'text', 'text' => $message],
            // Ce que la fiche contient vraiment : le modèle voit ce qu'il a oublié d'enregistrer (#193).
            ['type' => 'text', 'text' => self::etatDeLaFiche($demande)],
        ]];
        $conversation->ajouterMessage('client', $message);
        $this->enregistrer($conversation, $tour->historique);

        try {
            yield from $this->boucle($contexte, $tour, fn (array $historique) => $this->enregistrer($conversation, $historique));
        } finally {
            if ('' !== $tour->reponse) {
                $conversation->ajouterMessage('assistant', $tour->reponse);
                $this->em->flush();
            }
        }

        // Le client vient de confirmer sa demande : la recherche commence sans attendre un autre message. S'il a déjà
        // une proposition, c'est une modification : la version suivante se recalcule à partir d'elle (#211).
        if (true === ($tour->sortie('enregistrer_exigences')['recapitulatifConfirme'] ?? false)) {
            $reference = $this->nouvelleVersion->reference($demande);
            yield from null === $reference
                ? $this->recherche->proposer($conversation)
                : $this->nouvelleVersion->recalculer($conversation, $reference);
        }

        yield self::evenement('fin', new \stdClass());
    }

    protected function agent(): AgentIA
    {
        return AgentIA::Conversation;
    }

    protected function modele(): string
    {
        return self::MODELE;
    }

    protected function effort(): string
    {
        return 'low';
    }

    protected function outils(): array
    {
        return [EnregistrerExigences::class, ChercherDestinations::class, TransmettreAuConseiller::class];
    }

    protected function appelsMax(): int
    {
        return 8;
    }

    /**
     * Fiche enregistrée avant ce message et exigences encore inconnues. Les champs vides ou à leur valeur par
     * défaut sont omis : « 1 adulte » ne doit pas passer pour une information donnée par le client.
     */
    private static function etatDeLaFiche(Demande $demande): string
    {
        $parDefaut = EnregistrerExigences::fiche(new Demande());
        $remplis = array_filter(
            EnregistrerExigences::fiche($demande),
            static fn (mixed $valeur, string $champ) => null !== $valeur && [] !== $valeur && $valeur !== $parDefaut[$champ],
            \ARRAY_FILTER_USE_BOTH,
        );
        $manquantes = EnregistrerExigences::manquantes($demande);

        return \sprintf(
            '<etat_de_la_fiche>Enregistré : %s. Encore inconnu : %s.</etat_de_la_fiche>',
            json_encode((object) $remplis, \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR),
            [] === $manquantes ? 'rien' : implode(', ', $manquantes),
        );
    }

    /**
     * @param list<mixed> $historique
     */
    private function enregistrer(Conversation $conversation, array $historique): void
    {
        $conversation->setHistorique($historique);
        $this->em->flush();
    }
}
