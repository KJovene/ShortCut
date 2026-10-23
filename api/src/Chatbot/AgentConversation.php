<?php

namespace App\Chatbot;

use App\Chatbot\Outil\ChercherDestinations;
use App\Chatbot\Outil\ChoisirOptions;
use App\Chatbot\Outil\ConsulterCatalogue;
use App\Chatbot\Outil\EnregistrerExigences;
use App\Chatbot\Outil\Outil;
use App\Chatbot\Outil\TransmettreAuConseiller;
use App\Chiffrage\Montant;
use App\Confirmation\PropositionClient;
use App\Demande\FicheDemande;
use App\Entity\Conversation;
use App\Entity\Demande;
use App\Enum\AgentIA;
use App\Enum\NiveauBudget;
use App\Repository\PrestationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\ServerEvent;

/**
 * Agent Conversation : répond au client en streaming et recueille ses exigences (prompts/conversation.md).
 * Quand le client confirme son récapitulatif, l'agent Recherche prend le relais dans la même réponse : il choisit
 * la combinaison et l'explique au client, puis la rédaction en fait son récapitulatif détaillé. Le client répond
 * ensuite aux options proposées (choisir_options) : le voyage reste tel quel ou se met à jour.
 *
 * Avant sa proposition, le client peut demander des logements ou des activités : l'agent les lit dans le catalogue
 * (consulter_catalogue) et ils s'affichent en cartes cliquables sous son message (Suggestions).
 *
 * Les événements envoyés au site sont décrits dans Agent ; la réponse se termine par « fin » {terminee}, vrai quand
 * un conseiller a repris la conversation : le client peut sinon continuer, même après sa proposition, pour la modifier.
 */
final class AgentConversation extends Agent
{
    /** Décision D22 : Haiku 5.5 pour l'agent Conversation (prompts/README.md). */
    public const MODELE = 'claude-haiku-5-5';
    /** Réponse fixe une fois la conversation reprise par un conseiller. */
    public const MESSAGE_REPRISE = 'Votre demande est entre les mains d\'un conseiller de l\'agence : il a bien reçu votre message et vous recontacte sous 2 jours ouvrés.';
    public const MESSAGE_REPRISE_EN = 'Your request is in the hands of an advisor from the agency: they have received your message and will contact you within 2 business days.';

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
        private readonly PrestationRepository $prestations,
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
            $reprise = $demande->getLangue()->choisir(self::MESSAGE_REPRISE, self::MESSAGE_REPRISE_EN);
            $conversation->ajouterMessage('client', $message);
            $conversation->ajouterMessage('assistant', $reprise);
            $this->em->flush();

            yield self::evenement('texte', ['texte' => $reprise]);
            yield self::evenement('fin', ['terminee' => true]);

            return;
        }

        $contexte = new ContexteAgent($demande, $conversation, AgentIA::Conversation);

        $tour = new Tour($conversation->getHistorique());
        $tour->historique[] = ['role' => 'user', 'content' => [
            ['type' => 'text', 'text' => $message],
            // Ce que la fiche contient vraiment : le modèle voit ce qu'il a oublié d'enregistrer (#193).
            ['type' => 'text', 'text' => self::etatDeLaFiche($demande)],
            ...self::dossierSuivi($demande),
            ...self::propositionPreparee($demande),
        ]];
        $conversation->ajouterMessage('client', $message);
        $this->enregistrer($conversation, $tour->historique);

        $suggestions = null;
        try {
            yield from $this->boucle($contexte, $tour, fn (array $historique) => $this->enregistrer($conversation, $historique));
            // Les logements et activités recommandés, en cartes sous le message qui les présente.
            $suggestions = Suggestions::depuis($tour, $this->prestations);
            if (null !== $suggestions) {
                yield self::evenement('suggestions', $suggestions);
            }
        } finally {
            if ('' !== $tour->reponse) {
                $conversation->ajouterMessage('assistant', $tour->reponse, $suggestions);
                $this->em->flush();
            }
        }

        // Le client répond aux options de sa proposition : elle reste telle quelle ou se met à jour.
        $transmise = null !== $tour->sortie(TransmettreAuConseiller::NOM);
        $options = $tour->sortie(ChoisirOptions::NOM);
        $reference = NouvelleVersion::reference($demande);
        if (null !== $options && null !== $reference) {
            yield from $this->nouvelleVersion->appliquerLesOptions(
                $conversation,
                $reference,
                $options['activitesAjoutees'],
                NiveauBudget::tryFrom((string) $options['niveau']),
            );
        } elseif (!$transmise && true === ($tour->sortie(EnregistrerExigences::NOM)['recapitulatifConfirme'] ?? false)) {
            // Le client vient de confirmer sa demande : la recherche commence sans attendre un autre message. S'il a
            // déjà une proposition, c'est une modification : la version suivante se recalcule à partir d'elle (#211).
            // Un dossier déjà suivi sans proposition n'est que mis à jour : son conseiller prépare la première. Une
            // demande transmise dans le même tour (destination absente du catalogue) attend son conseiller.
            if (null !== $reference) {
                yield from $this->nouvelleVersion->recalculer($conversation, $reference);
            } elseif (null === $demande->getConseiller()) {
                yield from $this->recherche->proposer($conversation);
            }
        }

        // Le client peut toujours revenir modifier sa demande, même après sa proposition : la conversation ne se
        // termine que quand un conseiller l'a reprise (transmission demandée, ou automatique après un échec).
        yield self::evenement('fin', ['terminee' => $conversation->isRepriseParConseiller()]);
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
        return [EnregistrerExigences::class, ChercherDestinations::class, TransmettreAuConseiller::class, ChoisirOptions::class, ConsulterCatalogue::class];
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
        $parDefaut = FicheDemande::depuis(new Demande());
        $remplis = array_filter(
            FicheDemande::depuis($demande),
            static fn (mixed $valeur, string $champ) => null !== $valeur && [] !== $valeur && $valeur !== $parDefaut[$champ],
            \ARRAY_FILTER_USE_BOTH,
        );
        $manquantes = FicheDemande::manquantes($demande);
        // Un client connecté est déjà rattaché à la demande : le modèle ne lui redemande ni prénom ni e-mail.
        $client = $demande->getClient();

        return \sprintf(
            '<etat_de_la_fiche>Enregistré : %s. Encore inconnu : %s. Client : %s. Langue du client : %s.</etat_de_la_fiche>',
            json_encode((object) $remplis, \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR),
            [] === $manquantes ? 'rien' : implode(', ', $manquantes),
            null === $client ? 'inconnu (prénom et e-mail à demander avec la confirmation)' : \sprintf('connu, %s, ne rien demander', $client->getPrenom() ?? $client->getEmail()),
            $demande->getLangue()->value,
        );
    }

    /**
     * Le client revient depuis son espace sur un dossier qu'un conseiller suit déjà (« Modifier le voyage ») :
     * le modèle ajuste la fiche sans relancer de proposition (prompts/conversation.md, « Dossier suivi »).
     *
     * @return list<array{type: string, text: string}>
     */
    private static function dossierSuivi(Demande $demande): array
    {
        $conseiller = $demande->getConseiller();
        if (null === $conseiller) {
            return [];
        }

        return [['type' => 'text', 'text' => \sprintf(
            '<dossier_suivi>Ce dossier est suivi par %s, conseiller de l\'agence.</dossier_suivi>',
            trim(($conseiller->getPrenom() ?? '').' '.$conseiller->getNom()),
        )]];
    }

    /**
     * La proposition que le client a sous les yeux et ses options (prompts/conversation.md, « Les options ») : il
     * peut les ajouter, choisir un autre niveau ou garder le voyage tel quel.
     *
     * @return list<array{type: string, text: string}>
     */
    private static function propositionPreparee(Demande $demande): array
    {
        $proposition = NouvelleVersion::reference($demande);
        if (null === $proposition) {
            return [];
        }

        $niveaux = [];
        foreach (NiveauBudget::cases() as $niveau) {
            $budget = $proposition->getBudgetsParNiveau()[$niveau->value] ?? null;
            if (null !== $budget) {
                $niveaux[] = \sprintf('%s %s', $niveau->value, Montant::euros($budget['totalCentimes']));
            }
        }
        $options = null !== $proposition->getOptionsChoisiesLe()
            ? 'le client a déjà répondu aux options : ne les repropose pas'
            : ([] === $proposition->getOptions() ? 'aucune' : implode(' ; ', array_map(
                static fn (array $o): string => \sprintf('%d = %s, +%s pour le groupe', $o['id'], $o['nom'], Montant::euros($o['prixCentimes'])),
                $proposition->getOptions(),
            )));

        return [['type' => 'text', 'text' => \sprintf(
            '<proposition_preparee>Version %d, %s. Niveau détaillé : %s (%s). Totaux : %s. Options : %s.</proposition_preparee>',
            $proposition->getVersion(),
            $proposition === PropositionClient::apercu($demande) ? 'récapitulatif pas encore relu par le conseiller' : 'envoyée par le conseiller',
            $proposition->getNiveauBudget()->value,
            Montant::euros($proposition->getTotalCentimes()),
            implode(', ', $niveaux),
            $options,
        )]];
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
