<?php

namespace App\Command;

use App\Chatbot\AgentRecherche;
use App\Entity\Conversation;
use App\Entity\Demande;
use App\Enum\TypeDeVoyage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Évaluation de la première proposition (#207) : pour chaque demande test, la fiche attendue est confirmée, puis les
 * agents Recherche et Rédaction réels la traitent comme dans le chat. Mesure le délai entre la confirmation et la
 * proposition enregistrée (moins de 2 minutes) et compare le nombre de jours du programme à la durée demandée.
 * Le programme de chaque demande est écrit dans evaluations/ pour relecture. Appelle la vraie API Claude (payant).
 */
#[AsCommand(name: 'app:proposition:evaluer', description: 'Génère la première proposition des demandes test et mesure délai et durée du programme')]
final class EvaluerPropositionCommand extends Command
{
    private const DELAI_MAX_SECONDES = 120;

    public function __construct(
        private readonly AgentRecherche $recherche,
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
        $this->addArgument('demandes', InputArgument::IS_ARRAY, 'Numéros des demandes test (ex. 01 07) ; toutes par défaut');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $numeros = array_map(static fn (string $n): string => str_pad($n, 2, '0', \STR_PAD_LEFT), $input->getArgument('demandes'));
        $fichiers = array_values(array_filter(
            glob($this->dossierDemandes.'/[0-9][0-9]-*.json') ?: [],
            static fn (string $f): bool => [] === $numeros || \in_array(substr(basename($f), 0, 2), $numeros, true),
        ));

        $lignes = [];
        $rapport = [\sprintf('# Première proposition, %s', date('d/m/Y H:i'))];
        foreach ($fichiers as $fichier) {
            /** @var array{id: string, titre: string, conversation: list<array{client: string}>, exigencesAttendues: array<string, mixed>, attendu: array{passageAuConseiller?: bool}} $test */
            $test = json_decode((string) file_get_contents($fichier), true, flags: \JSON_THROW_ON_ERROR);
            $io->text(\sprintf('%s %s…', $test['id'], $test['titre']));
            $resultat = $this->evaluer($test);
            $lignes[] = $resultat['ligne'];
            $rapport[] = $resultat['rapport'];
        }

        $io->table(['Demande', 'Destination', 'Proposition', 'Délai après confirmation', 'Jours (attendus)', 'Appels rédaction', 'Résultat'], $lignes);
        if (!is_dir($this->dossierRapports)) {
            mkdir($this->dossierRapports, 0o777, true);
        }
        $chemin = \sprintf('%s/proposition-%s.md', $this->dossierRapports, date('Y-m-d-His'));
        file_put_contents($chemin, implode("\n\n", $rapport)."\n");
        $io->success('Programmes écrits dans '.$chemin);

        return Command::SUCCESS;
    }

    /**
     * @param array{id: string, titre: string, conversation: list<array{client: string}>, exigencesAttendues: array<string, mixed>, attendu: array{passageAuConseiller?: bool}} $test
     *
     * @return array{ligne: list<string>, rapport: string}
     */
    private function evaluer(array $test): array
    {
        $connexion = $this->em->getConnection();
        $connexion->beginTransaction();

        try {
            $conversation = new Conversation();
            $demande = self::demande($test['exigencesAttendues'])->setConversation($conversation);
            foreach ($test['conversation'] as $echange) {
                $conversation->ajouterMessage('client', $echange['client']);
            }
            $this->em->persist($demande);
            $this->em->persist($conversation);
            $this->em->flush();

            $debut = hrtime(true);
            $texte = '';
            $combinaisonA = null;
            $destination = '—';
            $erreurs = [];
            foreach ($this->recherche->proposer($conversation) as $evenement) {
                $donnees = json_decode((string) $evenement->getData(), true);
                match ($evenement->getType()) {
                    'combinaison' => [$combinaisonA, $destination] = [(hrtime(true) - $debut) / 1e9, (string) ($donnees['destination']['ville'] ?? '?')],
                    'texte' => $texte .= (string) ($donnees['texte'] ?? ''),
                    'erreur' => $erreurs[] = (string) ($donnees['erreur'] ?? ''),
                    default => null,
                };
            }
            $duree = (hrtime(true) - $debut) / 1e9;

            $proposition = $demande->getPropositions()->last() ?: null;
            $nuits = (int) $demande->getNombreDeJours();
            $jours = null === $proposition ? null : \count($proposition->getProgramme());
            $appels = (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM journal_ia WHERE demande_id = ? AND agent = ?', [$demande->getId(), 'chiffrage']);
            // Une demande test qui doit passer au conseiller (période sans tarif) réussit sans proposition.
            $ok = ($test['attendu']['passageAuConseiller'] ?? false)
                ? null === $proposition && $conversation->isRepriseParConseiller()
                : null !== $proposition && $jours === $nuits + 1 && $duree < self::DELAI_MAX_SECONDES;

            $ligne = [
                $test['id'],
                $destination,
                null === $proposition ? ($conversation->isRepriseParConseiller() ? 'conseiller' : 'aucune') : \sprintf('v%d %s', $proposition->getVersion(), $proposition->getStatut()->value),
                \sprintf('combinaison %s s, brouillon %s s', null === $combinaisonA ? '—' : (int) $combinaisonA, null === $proposition ? '—' : (int) $duree),
                \sprintf('%s (%d)', $jours ?? '—', $nuits + 1),
                (string) $appels,
                $ok ? 'OK' : 'KO',
            ];

            $rapport = [\sprintf("## %s %s\n\n%s · délai %d s · %s", $test['id'], $test['titre'], $destination, $duree, $ok ? 'OK' : 'KO')];
            $rapport[] = "Message au client :\n\n> ".str_replace("\n", "\n> ", trim($texte));
            if ([] !== $erreurs) {
                $rapport[] = 'Erreurs : '.implode(' ; ', $erreurs);
            }
            if (null !== $proposition) {
                $rapport[] = \sprintf('Niveau détaillé : %s · pourquoi : %s', $proposition->getNiveauBudget()->label(), $proposition->getPourquoi());
                $rapport[] = implode("\n", array_map(static fn (array $a): string => \sprintf('- %s → %s', $a['exigence'], $a['reponse']), $proposition->getArgumentaire()));
                $rapport[] = implode("\n", array_map(static fn (array $p): string => \sprintf('- %s (%s) : %s', $p['nom'], implode(', ', $p['niveaux']), implode(' ; ', $p['pointsForts'])), $proposition->getPrestations()));
                $rapport[] = implode("\n", array_map(static fn (array $j): string => \sprintf('- Jour %d (%s) %s : %s%s', $j['jour'], $j['date'], $j['titre'], $j['description'], [] === $j['activiteIds'] ? '' : ' [activités '.implode(', ', $j['activiteIds']).']'), $proposition->getProgramme()));
            }

            return ['ligne' => $ligne, 'rapport' => implode("\n\n", $rapport)];
        } finally {
            $connexion->rollBack();
            $this->em->clear();
        }
    }

    /**
     * La fiche attendue de la demande test, telle que le client l'a confirmée.
     *
     * @param array<string, mixed> $fiche
     */
    private static function demande(array $fiche): Demande
    {
        $date = static fn (mixed $v): ?\DateTimeImmutable => \is_string($v) ? new \DateTimeImmutable($v) : null;

        return (new Demande())
            ->setTypeDeVoyage(TypeDeVoyage::from((string) $fiche['typeDeVoyage']))
            ->setLieuOuZone($fiche['lieuOuZone'])
            ->setVilleDepart($fiche['villeDepart'])
            ->setDateDebut($date($fiche['dateDebut']))
            ->setDateFin($date($fiche['dateFin']))
            ->setDatesFlexibles((bool) $fiche['datesFlexibles'])
            ->setNombreDeJours($fiche['nombreDeJours'])
            ->setAdultes((int) $fiche['adultes'])
            ->setEnfants((int) $fiche['enfants'])
            ->setAgesEnfants($fiche['agesEnfants'])
            ->setBudgetCentimes(null === $fiche['budgetEuros'] ? null : (int) $fiche['budgetEuros'] * 100)
            ->setBudgetParPersonne((bool) $fiche['budgetParPersonne'])
            ->setTransportInclus((bool) $fiche['transportInclus'])
            ->setEnvies($fiche['envies'])
            ->setContraintes($fiche['contraintes']);
    }
}
