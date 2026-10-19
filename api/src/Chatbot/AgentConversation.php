<?php

namespace App\Chatbot;

use App\Chatbot\Outil\ChercherDestinations;
use App\Chatbot\Outil\EnregistrerExigences;
use App\Chatbot\Outil\Outil;
use App\Chatbot\Outil\OutilException;
use App\Chatbot\Outil\TransmettreAuConseiller;
use App\Entity\Conversation;
use App\Entity\JournalIA;
use App\Enum\AgentIA;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\ServerEvent;

/**
 * Agent Conversation : répond au client en streaming et exécute ses outils côté serveur, jusqu'à ce que
 * Claude ait fini de répondre. Les événements envoyés au site :
 * - « texte » {texte} : morceau de la réponse, à afficher à la suite ;
 * - « outil » {nom} : un outil s'exécute ;
 * - « demande » {fiche, recapitulatifConfirme} : la fiche demande vient d'être enregistrée ;
 * - « erreur » {erreur} : message à afficher au client ;
 * - « fin » {} : la réponse est complète.
 */
final class AgentConversation
{
    /** Décision D22 : Haiku 5.5 pour l'agent Conversation (prompts/README.md). */
    public const MODELE = 'claude-haiku-5-5';
    private const EFFORT = 'low';
    private const MAX_TOKENS = 16000;
    /** Appels à Claude pour un même message du client (chaque appel d'outil en ajoute un). */
    private const APPELS_MAX = 8;
    /** Outils de cet agent, dans un ordre fixe : le préfixe de la requête reste identique (cache). */
    private const OUTILS = [EnregistrerExigences::class, ChercherDestinations::class, TransmettreAuConseiller::class];

    /** @var array<string, Outil> */
    private array $outils = [];

    /**
     * @param iterable<Outil> $outils
     */
    public function __construct(
        private readonly ClientClaude $claude,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        #[AutowireIterator(Outil::TAG)]
        iterable $outils,
        #[Autowire('%kernel.project_dir%/prompts/conversation.md')]
        private readonly string $fichierPrompt,
    ) {
        $parClasse = [];
        foreach ($outils as $outil) {
            $parClasse[$outil::class] = $outil;
        }
        foreach (self::OUTILS as $classe) {
            $outil = $parClasse[$classe] ?? throw new \LogicException(\sprintf('Outil %s introuvable.', $classe));
            $this->outils[$outil->nom()] = $outil;
        }
    }

    /**
     * @return \Generator<int, ServerEvent>
     */
    public function repondre(Conversation $conversation, string $message): \Generator
    {
        $demande = $conversation->getDemande() ?? throw new \LogicException('Conversation sans demande.');
        $contexte = new ContexteAgent($demande, $conversation, AgentIA::Conversation);

        $historique = $conversation->getHistorique();
        $historique[] = ['role' => 'user', 'content' => [['type' => 'text', 'text' => $message]]];
        $conversation->ajouterMessage('client', $message);
        $this->enregistrer($conversation, $historique);

        $reponse = '';
        try {
            for ($appel = 1; $appel <= self::APPELS_MAX; ++$appel) {
                $debutDuTour = \count($historique) - 1;
                $flux = $this->claude->diffuser($this->parametres($historique));
                foreach ($flux as $texte) {
                    $reponse .= $texte;
                    yield self::evenement('texte', ['texte' => $texte]);
                }
                $resultat = $flux->getReturn();

                if (\in_array($resultat->stopReason, ['refusal', 'max_tokens'], true)) {
                    // Réponse incomplète : elle n'entre pas dans l'historique, ses outils ne sont pas exécutés.
                    $this->journaliser($contexte, $historique, $debutDuTour, $resultat, []);
                    $this->logger->warning('Agent Conversation : réponse interrompue ({raison}).', ['raison' => $resultat->stopReason]);
                    yield self::evenement('erreur', ['erreur' => 'Je n\'ai pas pu terminer ma réponse. Pouvez-vous reformuler votre message ?']);
                    break;
                }

                $historique[] = ['role' => 'assistant', 'content' => $resultat->contenu()];
                if ('tool_use' !== $resultat->stopReason) {
                    $this->journaliser($contexte, $historique, $debutDuTour, $resultat, []);
                    $this->enregistrer($conversation, $historique);
                    break;
                }

                $resultats = [];
                foreach ($resultat->appelsOutils() as $appelOutil) {
                    yield self::evenement('outil', ['nom' => $appelOutil->name]);
                    $resultats[] = $resultatOutil = $this->executer($contexte, $appelOutil, $resultat);

                    // La fiche vient d'être enregistrée : le site met à jour son récapitulatif.
                    if ('enregistrer_exigences' === $appelOutil->name && !$resultatOutil['is_error']) {
                        $sortie = json_decode((string) $resultatOutil['content'], true, flags: \JSON_THROW_ON_ERROR);
                        yield self::evenement('demande', [
                            'fiche' => $sortie['fiche'] ?? null,
                            'recapitulatifConfirme' => $sortie['recapitulatifConfirme'] ?? false,
                        ]);
                    }
                }
                // Tous les résultats dans un seul message, juste après l'appel.
                $historique[] = ['role' => 'user', 'content' => $resultats];
                $this->journaliser($contexte, $historique, $debutDuTour, $resultat, $resultats);
                $this->enregistrer($conversation, $historique);

                if (self::APPELS_MAX === $appel) {
                    $this->logger->warning('Agent Conversation : {max} appels sans réponse finale.', ['max' => self::APPELS_MAX]);
                    yield self::evenement('erreur', ['erreur' => 'Je n\'ai pas réussi à terminer. Pouvez-vous reformuler votre message ?']);
                }
            }
        } catch (ErreurClaude $e) {
            $this->logger->error('Agent Conversation : {erreur}', ['erreur' => $e->getMessage(), 'exception' => $e]);
            yield self::evenement('erreur', ['erreur' => $e->messagePourLeClient()]);
        } finally {
            if ('' !== $reponse) {
                $conversation->ajouterMessage('assistant', $reponse);
                $this->em->flush();
            }
        }

        yield self::evenement('fin', new \stdClass());
    }

    /**
     * @param list<mixed> $historique
     *
     * @return array<string, mixed>
     */
    private function parametres(array $historique): array
    {
        return [
            'model' => self::MODELE,
            'max_tokens' => self::MAX_TOKENS,
            // Réflexion adaptative par défaut sur Haiku 5.5 : l'effort règle sa profondeur.
            'output_config' => ['effort' => self::EFFORT],
            'system' => [['type' => 'text', 'text' => $this->prompt()]],
            'tools' => array_values(array_map(static fn (Outil $o) => $o->definition(), $this->outils)),
            'messages' => $historique,
            // Met en cache le préfixe commun (outils, prompt, début de la conversation).
            'cache_control' => ['type' => 'ephemeral'],
        ];
    }

    /**
     * Exécute un appel d'outil ; une entrée refusée devient une erreur que le modèle peut corriger.
     *
     * @return array<string, mixed> bloc tool_result
     */
    private function executer(ContexteAgent $contexte, \stdClass $appel, MessageClaude $resultat): array
    {
        $id = (string) $appel->id;
        $nom = (string) $appel->name;

        try {
            if (isset($resultat->entreesInvalides[$id])) {
                $sortie = ['INVALID_JSON' => $resultat->entreesInvalides[$id]];
                $erreur = true;
            } elseif (!isset($this->outils[$nom])) {
                $sortie = ['erreur' => \sprintf('L\'outil « %s » n\'existe pas.', $nom)];
                $erreur = true;
            } else {
                $entree = json_decode(json_encode($appel->input, \JSON_THROW_ON_ERROR), true, flags: \JSON_THROW_ON_ERROR);
                $sortie = $this->outils[$nom]->executer($contexte, \is_array($entree) ? $entree : []);
                $erreur = false;
            }
        } catch (OutilException $e) {
            $sortie = ['erreur' => $e->getMessage()];
            $erreur = true;
        } catch (\Throwable $e) {
            $this->logger->error('Outil {outil} : {erreur}', ['outil' => $nom, 'erreur' => $e->getMessage(), 'exception' => $e]);
            $sortie = ['erreur' => 'Erreur interne de l\'outil. Ne réessaie pas ; propose au client de transmettre sa demande à un conseiller.'];
            $erreur = true;
        }

        return [
            'type' => 'tool_result',
            'tool_use_id' => $id,
            'content' => json_encode($sortie, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION),
            'is_error' => $erreur,
        ];
    }

    /**
     * Une ligne de journal par appel à Claude : ce qui lui a été envoyé de nouveau, sa réponse, les outils.
     *
     * @param list<mixed>                $historique
     * @param list<array<string, mixed>> $resultatsOutils
     */
    private function journaliser(ContexteAgent $contexte, array $historique, int $debutDuTour, MessageClaude $resultat, array $resultatsOutils): void
    {
        $journal = (new JournalIA())
            ->setAgent($contexte->agent)
            ->setDemande($contexte->demande)
            ->setModele('' !== $resultat->modele ? $resultat->modele : self::MODELE)
            ->setEntree(['messages' => \array_slice($historique, $debutDuTour, 1)])
            ->setSortie(['contenu' => $resultat->contenu(), 'stopReason' => $resultat->stopReason, 'resultatsOutils' => $resultatsOutils])
            ->setTokensEntree($resultat->tokensEntree)
            ->setTokensSortie($resultat->tokensSortie);

        $this->em->persist($journal);
        $this->em->flush();
    }

    /**
     * @param list<mixed> $historique
     */
    private function enregistrer(Conversation $conversation, array $historique): void
    {
        $conversation->setHistorique($historique);
        $this->em->flush();
    }

    /** Prompt système sans son commentaire de tête, avec la date du jour. */
    private function prompt(): string
    {
        $contenu = (string) file_get_contents($this->fichierPrompt);
        $contenu = (string) preg_replace('/^\s*<!--.*?-->\s*/s', '', $contenu);
        $date = (new \IntlDateFormatter('fr_FR', \IntlDateFormatter::FULL, \IntlDateFormatter::NONE, 'Europe/Paris'))
            ->format(new \DateTimeImmutable());

        return str_replace('{{date_du_jour}}', (string) $date, trim($contenu));
    }

    private static function evenement(string $type, mixed $donnees): ServerEvent
    {
        return new ServerEvent(json_encode($donnees, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE), $type);
    }
}
