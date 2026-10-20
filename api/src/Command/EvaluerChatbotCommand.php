<?php

namespace App\Command;

use App\Chatbot\AgentConversation;
use App\Chatbot\ErreurClaude;
use App\Chatbot\Evaluation\ClientSimule;
use App\Chatbot\Evaluation\ComparateurFiche;
use App\Chatbot\Evaluation\Juge;
use App\Chatbot\Evaluation\RequeteJson;
use App\Chatbot\Outil\EnregistrerExigences;
use App\Entity\Conversation;
use App\Entity\Demande;
use App\Entity\JournalIA;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Évaluation du chatbot (#195) : rejoue les demandes test de tests/demandes avec l'agent Conversation réel,
 * le client étant joué par un autre modèle, puis compare la fiche obtenue à la fiche attendue, exigence par
 * exigence. Appelle la vraie API Claude : chaque lancement consomme des crédits.
 */
#[AsCommand(name: 'app:chatbot:evaluer', description: 'Rejoue les demandes test et mesure le taux d\'exigences captées par le chatbot')]
final class EvaluerChatbotCommand extends Command
{
    public function __construct(
        private readonly AgentConversation $agent,
        private readonly ClientSimule $client,
        private readonly Juge $juge,
        private readonly RequeteJson $requete,
        private readonly EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%/tests/demandes')]
        private readonly string $dossierDemandes,
        #[Autowire('%kernel.project_dir%/evaluations')]
        private readonly string $dossierRapports,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('demandes', InputArgument::IS_ARRAY, 'Numéros des demandes test à rejouer (ex. 01 04) ; toutes par défaut')
            ->addOption('messages-max', null, InputOption::VALUE_REQUIRED, 'Messages du client au maximum par conversation', '12')
            ->addOption('repetitions', 'r', InputOption::VALUE_REQUIRED, 'Conversations par demande test (le chatbot ne répond pas deux fois pareil)', '1')
            ->addOption('garder', null, InputOption::VALUE_NONE, 'Garde les demandes et conversations en base (sinon tout est annulé à la fin)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $messagesMax = max(1, (int) $input->getOption('messages-max'));
        $garder = (bool) $input->getOption('garder');
        $repetitions = max(1, (int) $input->getOption('repetitions'));

        $fichiers = $this->fichiers($input->getArgument('demandes'));
        if ([] === $fichiers) {
            $io->error('Aucune demande test trouvée.');

            return Command::FAILURE;
        }

        $io->title(\sprintf('Évaluation du chatbot : %d demande(s) test × %d', \count($fichiers), $repetitions));
        $io->text(\sprintf('Chatbot %s, client simulé %s, juge %s.', AgentConversation::MODELE, ClientSimule::MODELE, Juge::MODELE));

        $resultats = [];
        foreach ($fichiers as $fichier) {
            /** @var array<string, mixed> $demandeTest */
            $demandeTest = json_decode((string) file_get_contents($fichier), true, flags: \JSON_THROW_ON_ERROR);

            for ($essai = 1; $essai <= $repetitions; ++$essai) {
                $io->section(\sprintf('%s · %s%s', $demandeTest['id'], $demandeTest['titre'], $repetitions > 1 ? \sprintf(' (essai %d/%d)', $essai, $repetitions) : ''));

                try {
                    $resultat = $this->evaluer($demandeTest, $messagesMax, $garder, $io);
                } catch (ErreurClaude $e) {
                    $io->error($e->getMessage());
                    continue;
                }
                $resultat['essai'] = $essai;
                $resultats[] = $resultat;

                $io->text(\sprintf(
                    '%d/%d exigences captées en %d messages du client%s.',
                    $resultat['captees'],
                    $resultat['exigences'],
                    $resultat['messagesClient'],
                    $resultat['finie'] ? '' : ' (conversation non terminée)',
                ));
                $io->text('  Outils : '.([] === $resultat['outilsAppeles'] ? 'aucun' : implode(', ', $resultat['outilsAppeles'])));
                foreach ([...$resultat['ecarts'], ...self::defautsDeConversation($resultat)] as $ligne) {
                    $io->text('  - '.$ligne);
                }
            }
        }

        if ([] === $resultats) {
            return Command::FAILURE;
        }

        $rapport = $this->rapport($resultats);
        $chemin = \sprintf('%s/evaluation-%s.md', $this->dossierRapports, date('Ymd-His'));
        // Dossier partagé avec la machine : tout le monde doit pouvoir y écrire, quel que soit l'utilisateur du conteneur.
        $ecrit = (is_dir($this->dossierRapports) || @mkdir($this->dossierRapports, 0o777, true))
            && false !== @file_put_contents($chemin, $rapport);

        $exigences = array_sum(array_column($resultats, 'exigences'));
        $captees = array_sum(array_column($resultats, 'captees'));
        $taux = \sprintf('Taux d\'exigences captées : %s %% (%d/%d).', self::pourcentage($captees, $exigences), $captees, $exigences);

        if (!$ecrit) {
            // Les appels sont déjà payés : le rapport s'affiche plutôt que d'être perdu.
            $io->warning(\sprintf('Impossible d\'écrire dans api/evaluations/ (droits du dossier) : voici le rapport.'));
            $output->writeln($rapport);
            $io->success($taux);

            return Command::SUCCESS;
        }

        $io->success(\sprintf('%s Rapport : api/evaluations/%s', $taux, basename($chemin)));

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $numeros
     *
     * @return list<string>
     */
    private function fichiers(array $numeros): array
    {
        $fichiers = glob($this->dossierDemandes.'/[0-9][0-9]-*.json') ?: [];
        if ([] === $numeros) {
            return $fichiers;
        }

        $numeros = array_map(static fn (string $n) => str_pad($n, 2, '0', \STR_PAD_LEFT), $numeros);

        return array_values(array_filter($fichiers, static fn (string $f) => \in_array(substr(basename($f), 0, 2), $numeros, true)));
    }

    /**
     * Une conversation complète avec le chatbot, dans une transaction annulée à la fin (sauf --garder).
     *
     * @param array<string, mixed> $demandeTest
     *
     * @return array<string, mixed>
     */
    private function evaluer(array $demandeTest, int $messagesMax, bool $garder, SymfonyStyle $io): array
    {
        $connexion = $this->em->getConnection();
        $connexion->beginTransaction();

        try {
            $demande = new Demande();
            $conversation = new Conversation();
            $demande->setConversation($conversation);
            $this->em->persist($demande);
            $this->em->persist($conversation);
            $this->em->flush();

            /** @var list<array{role: 'client'|'chatbot', contenu: string}> $echanges */
            $echanges = [];
            $message = (string) $demandeTest['conversation'][0]['client'];
            $finie = false;
            for ($n = 1; $n <= $messagesMax; ++$n) {
                $echanges[] = ['role' => 'client', 'contenu' => $message];
                $echanges[] = ['role' => 'chatbot', 'contenu' => $this->repondre($conversation, $message)];
                $io->write('.');

                if (null !== $conversation->getFinLe() || $conversation->isRepriseParConseiller()) {
                    $finie = true;
                    break;
                }
                if ($n === $messagesMax) {
                    break;
                }

                $suite = $this->client->prochainMessage($demandeTest, $echanges);
                if ($suite['termine'] || '' === $suite['message']) {
                    $finie = true;
                    break;
                }
                $message = $suite['message'];
            }
            $io->newLine();

            $comparaison = ComparateurFiche::comparer($demandeTest['exigencesAttendues'], EnregistrerExigences::fiche($demande));
            $aJuger = array_filter($comparaison, static fn (array $c) => ComparateurFiche::A_JUGER === $c['statut']);
            $jugement = $this->juge->juger($aJuger, $echanges);
            foreach ($jugement['champs'] as $champ => $verdict) {
                if (isset($comparaison[$champ])) {
                    $comparaison[$champ]['statut'] = $verdict['verdict'];
                    $comparaison[$champ]['explication'] = $verdict['explication'];
                }
            }

            $tokensChatbot = [0, 0];
            $erreursOutils = [];
            $outilsAppeles = [];
            foreach ($this->em->getRepository(JournalIA::class)->findBy(['demande' => $demande], ['id' => 'ASC']) as $journal) {
                $tokensChatbot[0] += $journal->getTokensEntree() ?? 0;
                $tokensChatbot[1] += $journal->getTokensSortie() ?? 0;
                foreach ($journal->getSortie()['contenu'] ?? [] as $bloc) {
                    $bloc = (array) $bloc;
                    if ('tool_use' === ($bloc['type'] ?? null)) {
                        $outilsAppeles[] = (string) $bloc['name'];
                    }
                }
                // Un outil refusé ne se voit pas dans la conversation : le modèle peut continuer sans rien enregistrer.
                foreach ($journal->getSortie()['resultatsOutils'] ?? [] as $resultatOutil) {
                    if (true === ($resultatOutil['is_error'] ?? false)) {
                        $erreursOutils[] = 'erreur d\'outil : '.mb_substr((string) ($resultatOutil['content'] ?? ''), 0, 300);
                    }
                }
            }

            $resultat = [
                'id' => $demandeTest['id'],
                'titre' => $demandeTest['titre'],
                'typeDeVoyage' => $demandeTest['typeDeVoyage'],
                'messagesClient' => \count(array_filter($echanges, static fn (array $e) => 'client' === $e['role'])),
                'finie' => $finie,
                'recapitulatifConfirme' => null !== $conversation->getFinLe(),
                'passageAttendu' => true === ($demandeTest['attendu']['passageAuConseiller'] ?? false),
                'passageObtenu' => $conversation->isRepriseParConseiller(),
                'prixOuDisponibilite' => $jugement['prixOuDisponibiliteAnnonces'],
                'questionsManquees' => $jugement['questionsManquees'],
                'oublisDuRecapitulatif' => $jugement['oublisDuRecapitulatif'],
                'remarque' => $jugement['remarque'],
                'tokensChatbot' => $tokensChatbot,
                'outilsAppeles' => $outilsAppeles,
                'comparaison' => $comparaison,
                'echanges' => $echanges,
            ];
            $resultat += self::bilan($comparaison);
            array_push($resultat['ecarts'], ...$erreursOutils);

            $garder ? $connexion->commit() : $connexion->rollBack();
        } catch (\Throwable $e) {
            if ($connexion->isTransactionActive()) {
                $connexion->rollBack();
            }

            throw $e;
        } finally {
            $this->em->clear();
        }

        return $resultat;
    }

    /** Réponse complète du chatbot à un message, telle que le client la lit. */
    private function repondre(Conversation $conversation, string $message): string
    {
        $texte = '';
        foreach ($this->agent->repondre($conversation, $message) as $evenement) {
            $donnees = json_decode((string) $evenement->getData(), true);
            match ($evenement->getType()) {
                'texte' => $texte .= (string) ($donnees['texte'] ?? ''),
                'erreur' => $texte .= \sprintf("\n[ERREUR affichée au client : %s]", (string) ($donnees['erreur'] ?? '')),
                default => null,
            };
        }

        return trim($texte);
    }

    /**
     * @param array<string, array{statut: string, attendu: mixed, obtenu: mixed, explication?: string}> $comparaison
     *
     * @return array{exigences: int, captees: int, ecarts: list<string>}
     */
    private static function bilan(array $comparaison): array
    {
        $exigences = 0;
        $captees = 0;
        $ecarts = [];
        foreach ($comparaison as $champ => $c) {
            if (ComparateurFiche::estExigence($c['attendu'])) {
                ++$exigences;
                if (ComparateurFiche::OK === $c['statut']) {
                    ++$captees;
                }
            }
            if (ComparateurFiche::OK !== $c['statut']) {
                $ecarts[] = \sprintf(
                    '%s %s : attendu %s, obtenu %s%s',
                    $champ,
                    $c['statut'],
                    self::valeur($c['attendu']),
                    self::valeur($c['obtenu']),
                    isset($c['explication']) ? ' ('.$c['explication'].')' : '',
                );
            }
        }

        return ['exigences' => $exigences, 'captees' => $captees, 'ecarts' => $ecarts];
    }

    /**
     * Ce que le juge relève dans la conversation elle-même (critères de #193 et garde-fous).
     *
     * @param array<string, mixed> $r
     *
     * @return list<string>
     */
    private static function defautsDeConversation(array $r): array
    {
        $defauts = array_map(static fn (string $q) => 'question non posée : '.$q, $r['questionsManquees']);
        foreach ($r['oublisDuRecapitulatif'] as $oubli) {
            $defauts[] = 'absent du récapitulatif : '.$oubli;
        }
        if ($r['passageObtenu'] && !$r['passageAttendu']) {
            $defauts[] = 'passage au conseiller inattendu (la demande devient prioritaire)';
        }
        if (!$r['passageObtenu'] && $r['passageAttendu']) {
            $defauts[] = 'passage au conseiller attendu mais pas fait';
        }
        if ($r['prixOuDisponibilite']) {
            $defauts[] = 'prix ferme ou disponibilité annoncés';
        }

        return $defauts;
    }

    /**
     * @param list<array<string, mixed>> $resultats
     */
    private function rapport(array $resultats): string
    {
        $exigences = array_sum(array_column($resultats, 'exigences'));
        $captees = array_sum(array_column($resultats, 'captees'));
        $tokens = $this->requete->tokens;
        $tokens[AgentConversation::MODELE] = [
            array_sum(array_map(static fn (array $r) => $r['tokensChatbot'][0], $resultats)),
            array_sum(array_map(static fn (array $r) => $r['tokensChatbot'][1], $resultats)),
        ];

        $lignes = [
            '# Évaluation du chatbot',
            '',
            \sprintf('%s · chatbot %s · client simulé %s · juge %s', date('d/m/Y H:i'), AgentConversation::MODELE, ClientSimule::MODELE, Juge::MODELE),
            '',
            \sprintf('- **Exigences captées : %s %% (%d/%d)**', self::pourcentage($captees, $exigences), $captees, $exigences),
            \sprintf('- Conversations où chaque exigence citée a eu sa question : %d/%d', \count(array_filter($resultats, static fn (array $r) => [] === $r['questionsManquees'])), \count($resultats)),
            \sprintf('- Récapitulatifs complets : %d/%d', \count(array_filter($resultats, static fn (array $r) => [] === $r['oublisDuRecapitulatif'])), \count($resultats)),
            \sprintf('- Fiches confirmées en base : %d/%d', \count(array_filter($resultats, static fn (array $r) => $r['recapitulatifConfirme'])), \count($resultats)),
            \sprintf('- Prix ferme ou disponibilité annoncés : %d', \count(array_filter($resultats, static fn (array $r) => $r['prixOuDisponibilite']))),
            '',
            '| Demande | Type | Exigences captées | Messages du client | Récapitulatif confirmé | Questions non posées | Oublis du récapitulatif | Passage au conseiller (attendu) | Prix ou disponibilité annoncés | Remarque du juge |',
            '| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |',
        ];
        foreach ($resultats as $r) {
            $lignes[] = \sprintf(
                '| %s %s | %s | %d/%d | %d%s | %s | %d | %d | %s (%s) | %s | %s |',
                $r['id'].(isset($r['essai']) && \count($resultats) > \count(array_unique(array_column($resultats, 'id'))) ? '.'.$r['essai'] : ''),
                $r['titre'],
                $r['typeDeVoyage'],
                $r['captees'],
                $r['exigences'],
                $r['messagesClient'],
                $r['finie'] ? '' : ' (non terminée)',
                $r['recapitulatifConfirme'] ? 'Oui' : 'Non',
                \count($r['questionsManquees']),
                \count($r['oublisDuRecapitulatif']),
                $r['passageObtenu'] ? 'Oui' : 'Non',
                $r['passageAttendu'] ? 'oui' : 'non',
                $r['prixOuDisponibilite'] ? '**Oui**' : 'Non',
                str_replace(['|', "\n"], ['\|', ' '], (string) $r['remarque']),
            );
        }

        $lignes[] = '';
        $lignes[] = 'Tokens (entrée / sortie) : '.implode(' · ', array_map(
            static fn (string $modele, array $t) => \sprintf('%s %d / %d', $modele, $t[0], $t[1]),
            array_keys($tokens),
            $tokens,
        ));

        foreach ($resultats as $r) {
            $lignes[] = '';
            $lignes[] = \sprintf('## %s · %s%s', $r['id'], $r['titre'], isset($r['essai']) && $r['essai'] > 1 ? ' (essai '.$r['essai'].')' : '');
            $lignes[] = '';
            $ecarts = [...$r['ecarts'], ...self::defautsDeConversation($r)];
            $lignes[] = [] === $ecarts ? 'Aucun écart.' : 'Écarts :';
            foreach ($ecarts as $ecart) {
                $lignes[] = '- '.$ecart;
            }
            $lignes[] = '';
            $lignes[] = 'Outils appelés, dans l\'ordre : '.([] === $r['outilsAppeles'] ? 'aucun' : implode(', ', $r['outilsAppeles'])).'.';
            $lignes[] = '';
            $lignes[] = '<details><summary>Conversation</summary>';
            $lignes[] = '';
            foreach ($r['echanges'] as $e) {
                $lignes[] = \sprintf('**%s** : %s', 'client' === $e['role'] ? 'Client' : 'Chatbot', str_replace("\n", "  \n", $e['contenu']));
                $lignes[] = '';
            }
            $lignes[] = '</details>';
        }

        return implode("\n", $lignes)."\n";
    }

    private static function valeur(mixed $valeur): string
    {
        $json = json_encode($valeur, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);

        return false === $json ? '?' : $json;
    }

    private static function pourcentage(int $part, int $total): string
    {
        return 0 === $total ? '0' : number_format(100 * $part / $total, 1, ',', '');
    }
}
