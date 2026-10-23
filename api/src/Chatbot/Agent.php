<?php

namespace App\Chatbot;

use App\Chatbot\Outil\EnregistrerExigences;
use App\Chatbot\Outil\Outil;
use App\Chatbot\Outil\OutilException;
use App\Chiffrage\Periode;
use App\Entity\Conversation;
use App\Entity\JournalIA;
use App\Enum\AgentIA;
use App\Enum\Langue;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\ServerEvent;

/**
 * Boucle commune aux agents : appelle Claude en streaming, exécute côté serveur les outils qu'il demande, lui
 * renvoie leurs résultats et recommence jusqu'à sa réponse finale. Chaque appel est journalisé (JournalIA).
 *
 * Les événements envoyés au site :
 * - « texte » {texte} : morceau de la réponse, à afficher à la suite ;
 * - « outil » {nom} : un outil s'exécute ;
 * - « demande » {fiche, recapitulatifConfirme} : la fiche demande vient d'être enregistrée ;
 * - « piste » {…} : la combinaison retenue, à afficher en carte après le message de l'agent Recherche (Piste) ;
 * - « suggestions » {elements} : logements et activités recommandés, en cartes après le message de l'agent
 *   Conversation (Suggestions) ;
 * - « recapitulatif » {jeton, …} : le récapitulatif détaillé est enregistré, consultable par son lien ;
 * - « erreur » {erreur} : message à afficher au client ;
 * - « fin » {terminee} : la réponse est complète ; terminee si la conversation n'attend plus rien du client.
 */
abstract class Agent
{
    /** @var array<string, Outil> outils de l'agent, par nom, dans l'ordre de outils() */
    private array $outils = [];

    /**
     * @param iterable<Outil> $outils tous les outils déclarés ; l'agent ne garde que les siens
     */
    public function __construct(
        private readonly ClientClaude $claude,
        protected readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        iterable $outils,
        private readonly string $fichierPrompt,
    ) {
        $parClasse = [];
        foreach ($outils as $outil) {
            $parClasse[$outil::class] = $outil;
        }
        foreach ($this->outils() as $classe) {
            $outil = $parClasse[$classe] ?? throw new \LogicException(\sprintf('Outil %s introuvable.', $classe));
            $this->outils[$outil->nom()] = $outil;
        }
    }

    abstract protected function agent(): AgentIA;

    abstract protected function modele(): string;

    abstract protected function effort(): string;

    /**
     * Les outils de l'agent, dans un ordre fixe : le préfixe de la requête reste identique (cache).
     *
     * @return list<class-string<Outil>>
     */
    abstract protected function outils(): array;

    /** Appels à Claude pour un même tour (chaque appel d'outil en ajoute un). */
    abstract protected function appelsMax(): int;

    protected function maxTokens(): int
    {
        return 16000;
    }

    /**
     * Outil dont le succès termine le tour, sans attendre de réponse finale du modèle (un appel à Claude de moins).
     * Null : le tour se termine sur la réponse finale.
     */
    protected function outilFinal(): ?string
    {
        return null;
    }

    /**
     * Diffuser le texte au fil de l'eau, y compris celui qui précède un appel d'outil. Un agent qui n'écrit au client
     * qu'à la fin (Recherche) renvoie false : seul le texte de sa réponse finale est envoyé, d'un bloc.
     */
    protected function diffuserAuFilDeLEau(): bool
    {
        return true;
    }

    /**
     * Fait parler l'agent jusqu'à sa réponse finale. $tour->historique se complète au fil des appels ; $enregistrer
     * est appelé après chaque réponse de Claude gardée dans l'historique.
     *
     * @param \Closure(list<mixed>): void $enregistrer
     *
     * @return \Generator<int, ServerEvent>
     */
    protected function boucle(ContexteAgent $contexte, Tour $tour, \Closure $enregistrer): \Generator
    {
        $nom = $this->agent()->label();
        $debutDuTour = 0;
        $chrono = 0;
        try {
            for ($appel = 1; $appel <= $this->appelsMax(); ++$appel) {
                $debutDuTour = \count($tour->historique) - 1;
                $chrono = hrtime(true);
                $flux = $this->claude->diffuser($this->parametres($tour->historique));
                $enAttente = '';
                foreach ($flux as $texte) {
                    if (!$this->diffuserAuFilDeLEau()) {
                        $enAttente .= $texte;
                        continue;
                    }
                    $tour->reponse .= $texte;
                    yield self::evenement('texte', ['texte' => $texte]);
                }
                $resultat = $flux->getReturn();
                $dureeMs = self::millisecondes($chrono);
                if ('' !== $enAttente && 'tool_use' !== $resultat->stopReason) {
                    // Le widget affiche du texte brut : le gras markdown y resterait en astérisques.
                    $enAttente = str_replace('**', '', $enAttente);
                    $tour->reponse .= $enAttente;
                    yield self::evenement('texte', ['texte' => $enAttente]);
                }

                if (\in_array($resultat->stopReason, ['refusal', 'max_tokens'], true)) {
                    // Réponse incomplète : elle n'entre pas dans l'historique, ses outils ne sont pas exécutés.
                    $this->journaliser($contexte, $tour->historique, $debutDuTour, $resultat, [], $dureeMs);
                    $this->logger->warning('Agent {agent} : réponse interrompue ({raison}).', ['agent' => $nom, 'raison' => $resultat->stopReason]);
                    yield self::evenement('erreur', ['erreur' => $contexte->demande->getLangue()->choisir(
                        'Je n\'ai pas pu terminer ma réponse. Pouvez-vous reformuler votre message ?',
                        'I could not finish my answer. Could you rephrase your message?',
                    )]);
                    break;
                }

                $tour->historique[] = ['role' => 'assistant', 'content' => $resultat->contenu()];
                if ('tool_use' !== $resultat->stopReason) {
                    $this->journaliser($contexte, $tour->historique, $debutDuTour, $resultat, [], $dureeMs);
                    $enregistrer($tour->historique);
                    break;
                }

                $resultats = [];
                foreach ($resultat->appelsOutils() as $appelOutil) {
                    yield self::evenement('outil', ['nom' => $appelOutil->name]);
                    $resultats[] = $resultatOutil = $this->executer($contexte, $appelOutil, $resultat);
                    if ($resultatOutil['is_error']) {
                        continue;
                    }

                    $sortie = json_decode((string) $resultatOutil['content'], true, flags: \JSON_THROW_ON_ERROR);
                    $tour->sorties[] = ['nom' => (string) $appelOutil->name, 'sortie' => $sortie];
                    // La fiche vient d'être enregistrée : le site met à jour son récapitulatif.
                    if (EnregistrerExigences::NOM === $appelOutil->name) {
                        yield self::evenement('demande', [
                            'fiche' => $sortie['fiche'] ?? null,
                            'recapitulatifConfirme' => $sortie['recapitulatifConfirme'] ?? false,
                            // Le site affiche ses propres textes dans la langue du client (US15).
                            'langue' => $sortie['langue'] ?? Langue::Francais->value,
                        ]);
                    }
                }
                // Tous les résultats dans un seul message, juste après l'appel.
                $tour->historique[] = ['role' => 'user', 'content' => $resultats];
                $this->journaliser($contexte, $tour->historique, $debutDuTour, $resultat, $resultats, $dureeMs);
                $enregistrer($tour->historique);

                if (null !== $this->outilFinal() && null !== $tour->sortie($this->outilFinal())) {
                    break;
                }

                if ($this->appelsMax() === $appel) {
                    $this->logger->warning('Agent {agent} : {max} appels sans réponse finale.', ['agent' => $nom, 'max' => $this->appelsMax()]);
                    yield self::evenement('erreur', ['erreur' => $contexte->demande->getLangue()->choisir(
                        'Je n\'ai pas réussi à terminer. Pouvez-vous reformuler votre message ?',
                        'I did not manage to finish. Could you rephrase your message?',
                    )]);
                }
            }
        } catch (ErreurClaude $e) {
            // Un appel en échec figure aussi au journal : le responsable voit tout ce qui a été tenté.
            $this->journaliserEchec($contexte, $tour->historique, $debutDuTour, $e, self::millisecondes($chrono));
            $this->logger->error('Agent {agent} : {erreur}', ['agent' => $nom, 'erreur' => $e->getMessage(), 'exception' => $e]);
            yield self::evenement('erreur', ['erreur' => $e->messagePourLeClient($contexte->demande->getLangue())]);
        }
    }

    /** Un outil de l'agent, pour que le serveur l'appelle lui-même. */
    protected function outil(string $nom): Outil
    {
        return $this->outils[$nom] ?? throw new \LogicException(\sprintf('L\'agent %s n\'a pas l\'outil %s.', $this->agent()->label(), $nom));
    }

    /**
     * La réponse rejoint les messages affichés et, dans l'historique de l'agent Conversation, la fin de sa dernière
     * réponse : au message suivant du client, il sait ce qui a été proposé.
     *
     * @param array<string, mixed>|null $carte
     */
    protected function ajouterALaConversation(Conversation $conversation, string $reponse, ?array $carte = null): void
    {
        self::ajouterALaReponse($conversation, $reponse, $carte);
        $this->em->flush();
    }

    /**
     * Ajoute la réponse aux messages et à l'historique, sans enregistrer (NouvelleVersion s'en sert aussi). Avec une
     * carte, le message la porte et son texte rejoint l'historique : l'agent Conversation sait ce que le client voit.
     *
     * @param array<string, mixed>|null $carte
     */
    public static function ajouterALaReponse(Conversation $conversation, string $reponse, ?array $carte = null): void
    {
        if ('' === trim($reponse)) {
            return;
        }

        $conversation->ajouterMessage('assistant', trim($reponse), $carte);
        // L'historique est fait d'objets (Conversation::getHistorique()) : le dernier message est modifié en place.
        $historique = $conversation->getHistorique();
        $dernier = end($historique);
        if ($dernier instanceof \stdClass && 'assistant' === ($dernier->role ?? null) && \is_array($dernier->content ?? null)) {
            $dernier->content[] = (object) ['type' => 'text', 'text' => "\n\n".trim($reponse)];
            $conversation->setHistorique($historique);
        }
    }

    /** Les messages du client, un par ligne : ce qu'il a nommé (« voir des dauphins ») n'a pas toujours de champ dans la fiche. */
    protected static function messagesDuClient(Conversation $conversation): string
    {
        return implode("\n", array_map(
            static fn (array $m): string => '- '.str_replace("\n", ' ', $m['contenu']),
            array_values(array_filter($conversation->getMessages(), static fn (array $m): bool => 'client' === $m['role'])),
        ));
    }

    protected static function evenement(string $type, mixed $donnees): ServerEvent
    {
        return new ServerEvent(json_encode($donnees, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE), $type);
    }

    /**
     * @param list<mixed> $historique
     *
     * @return array<string, mixed>
     */
    private function parametres(array $historique): array
    {
        return [
            'model' => $this->modele(),
            'max_tokens' => $this->maxTokens(),
            // Réflexion adaptative par défaut : l'effort règle sa profondeur.
            'output_config' => ['effort' => $this->effort()],
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
    private function journaliser(ContexteAgent $contexte, array $historique, int $debutDuTour, MessageClaude $resultat, array $resultatsOutils, int $dureeMs): void
    {
        $journal = (new JournalIA())
            ->setAgent($contexte->agent)
            ->setDemande($contexte->demande)
            ->setModele('' !== $resultat->modele ? $resultat->modele : $this->modele())
            ->setEntree(['messages' => \array_slice($historique, $debutDuTour, 1)])
            ->setSortie(['contenu' => $resultat->contenu(), 'stopReason' => $resultat->stopReason, 'resultatsOutils' => $resultatsOutils])
            ->setTokensEntree($resultat->tokensEntree)
            ->setTokensSortie($resultat->tokensSortie)
            ->setDureeMs($dureeMs);

        $this->em->persist($journal);
        $this->em->flush();
    }

    /**
     * L'appel n'a pas abouti (API indisponible, délai dépassé…) : la ligne garde ce qui a été envoyé et l'erreur.
     *
     * @param list<mixed> $historique
     */
    private function journaliserEchec(ContexteAgent $contexte, array $historique, int $debutDuTour, ErreurClaude $erreur, int $dureeMs): void
    {
        $journal = (new JournalIA())
            ->setAgent($contexte->agent)
            ->setDemande($contexte->demande)
            ->setModele($this->modele())
            ->setEntree(['messages' => \array_slice($historique, $debutDuTour, 1)])
            ->setSortie(['erreur' => $erreur->getMessage()])
            ->setDureeMs($dureeMs);

        $this->em->persist($journal);
        $this->em->flush();
    }

    /** Millisecondes écoulées depuis un relevé de hrtime(true) ; 0 si l'appel n'a pas commencé. */
    private static function millisecondes(int $depuis): int
    {
        return 0 === $depuis ? 0 : intdiv(hrtime(true) - $depuis, 1_000_000);
    }

    /** Prompt système sans son commentaire de tête, avec la date du jour. */
    private function prompt(): string
    {
        $contenu = (string) file_get_contents($this->fichierPrompt);
        $contenu = (string) preg_replace('/^\s*<!--.*?-->\s*/s', '', $contenu);
        $date = Periode::jour(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')));

        return str_replace('{{date_du_jour}}', $date, trim($contenu));
    }
}
