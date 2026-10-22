<?php

namespace App\Chatbot;

use App\Chatbot\Outil\EnregistrerExigences;
use App\Chatbot\Outil\OutilException;
use App\Chatbot\Outil\RetenirCombinaison;
use App\Chiffrage\Montant;
use App\Entity\Conversation;
use App\Entity\Demande;
use App\Entity\Proposition;
use App\Enum\AgentIA;
use App\Enum\Langue;
use App\Enum\NiveauBudget;
use App\Enum\StatutProposition;
use App\Enum\TypeProposition;
use App\Repository\PrestationRepository;
use App\Texte\Anglais;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\ServerEvent;

/**
 * Nouvelle version après une modification du client dans le chat (ticket #211, US11), en moins d'une minute :
 * - rien n'a changé dans la fiche : la proposition reste valable ;
 * - dates, durée, voyageurs, budget, ville de départ, températures ou contraintes : la même combinaison est
 *   recalculée par retenir_combinaison, sans appel à Claude, et présentée au client ;
 * - autre type de voyage, autre zone, autres envies, ou combinaison refusée aux nouvelles exigences : l'agent
 *   Recherche repart de la combinaison précédente et ne cherche que ce que le changement touche.
 * L'agent Rédaction écrit ensuite la version suivante, envoyée directement au client ; la précédente reste
 * consultable (« remplacée »).
 */
final class NouvelleVersion
{
    public function __construct(
        private readonly RetenirCombinaison $retenirCombinaison,
        private readonly AgentRecherche $recherche,
        private readonly AgentRedaction $redaction,
        private readonly PrestationRepository $prestations,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * La version dont part une modification : la plus récente qui garde sa combinaison, hors confirmation en cours
     * dans le backoffice. Null pour une demande qui n'a pas encore de proposition.
     */
    public function reference(Demande $demande): ?Proposition
    {
        $reference = null;
        foreach ($demande->getPropositions() as $proposition) {
            $enConfirmation = TypeProposition::Confirmee === $proposition->getType() && StatutProposition::Brouillon === $proposition->getStatut();
            if ([] === $proposition->getChoix() || $enConfirmation || StatutProposition::Refusee === $proposition->getStatut()) {
                continue;
            }
            if (null === $reference || $proposition->getVersion() > $reference->getVersion()) {
                $reference = $proposition;
            }
        }

        return $reference;
    }

    /**
     * @return \Generator<int, ServerEvent>
     */
    public function recalculer(Conversation $conversation, Proposition $reference): \Generator
    {
        $demande = $conversation->getDemande() ?? throw new \LogicException('Conversation sans demande.');
        $changement = Changement::entre($reference->getExigences(), EnregistrerExigences::fiche($demande));
        if ($changement->vide()) {
            $message = \sprintf($demande->getLangue()->choisir(
                'Votre demande n\'a pas changé : votre proposition (version %d) reste valable.',
                'Your request has not changed: your proposal (version %d) is still valid.',
            ), $reference->getVersion());
            $this->ajouter($conversation, $message);
            yield self::evenement('texte', ['texte' => "\n\n".$message]);

            return;
        }

        $modification = new Modification($reference, $changement, $this->combinaison($reference));
        if (!$changement->rechercheNecessaire()) {
            yield self::evenement('outil', ['nom' => 'retenir_combinaison']);
            try {
                $sortie = $this->retenirCombinaison->executer(
                    new ContexteAgent($demande, $conversation, AgentIA::Modification),
                    self::choixAdapte($reference->getChoix(), $demande),
                );
            } catch (OutilException $e) {
                // Une prestation ne convient plus (tarif, capacité, climat…) : la recherche en trouve une autre.
                $this->logger->info('Nouvelle version : la combinaison de la version {version} est refusée ({raison}).', ['version' => $reference->getVersion(), 'raison' => $e->getMessage()]);
                yield from $this->recherche->proposer($conversation, $modification->avecRefus($e->getMessage()));

                return;
            }

            yield self::evenement('combinaison', $sortie);
            $message = self::presentation($changement, $sortie, $demande->getLangue());
            $this->ajouter($conversation, $message);
            yield self::evenement('texte', ['texte' => "\n\n".$message]);
            yield from $this->redaction->rediger($conversation, (string) $sortie['chiffrageId'], $modification);

            return;
        }

        yield from $this->recherche->proposer($conversation, $modification);
    }

    /**
     * Le choix de la référence avec les dates et la durée de la fiche : les étapes se suivent à partir du nouveau
     * départ ; une nuit de plus ou de moins se prend sur l'étape la plus longue.
     *
     * @param array{destination?: string, etapes?: list<array{dateDebut: string, nombreDeNuits: int, logementIds: list<int>}>, activitesDemandees?: list<int>, activitesSuggerees?: list<int>, pourquoi?: string} $choix
     *
     * @return array<string, mixed> entrée de retenir_combinaison
     *
     * @throws OutilException si la nouvelle durée ne laisse pas une nuit par étape
     */
    public static function choixAdapte(array $choix, Demande $demande): array
    {
        $etapes = $choix['etapes'] ?? [];
        $debut = $demande->getDateDebut();
        if ([] === $etapes || null === $debut) {
            throw new OutilException('La version précédente n\'a pas d\'étapes, ou la demande n\'a plus de date de départ.');
        }

        $nuits = array_column($etapes, 'nombreDeNuits');
        $total = $demande->getNombreDeJours()
            ?? (null === $demande->getDateFin() ? null : (int) $debut->diff($demande->getDateFin())->days)
            ?? array_sum($nuits);
        if ($total < \count($etapes)) {
            throw new OutilException(\sprintf('%s pour %d étapes : il faut revoir le circuit.', Montant::quantite($total, 'nuit'), \count($etapes)));
        }
        while (array_sum($nuits) !== $total) {
            $plusLongue = (int) array_keys($nuits, max($nuits), true)[0];
            $nuits[$plusLongue] += array_sum($nuits) < $total ? 1 : -1;
        }

        $date = $debut;
        foreach ($etapes as $index => $etape) {
            $etapes[$index] = ['dateDebut' => $date->format('Y-m-d'), 'nombreDeNuits' => $nuits[$index], 'logementIds' => $etape['logementIds']];
            $date = $date->modify(\sprintf('+%d days', $nuits[$index]));
        }

        return [
            'destination' => $choix['destination'] ?? '',
            'etapes' => $etapes,
            'activitesDemandees' => $choix['activitesDemandees'] ?? [],
            'activitesSuggerees' => $choix['activitesSuggerees'] ?? [],
            'pourquoi' => $choix['pourquoi'] ?? 'Mêmes prestations, adaptées à la demande modifiée.',
        ];
    }

    /**
     * Le message du client : ce qui change, puis les trois niveaux et leur total, comme la première présentation.
     *
     * @param array<string, mixed> $sortie sortie de retenir_combinaison
     */
    private static function presentation(Changement $changement, array $sortie, Langue $langue = Langue::Francais): string
    {
        $anglais = Langue::Anglais === $langue;
        $lignes = [\sprintf(
            $langue->choisir('Voici votre proposition mise à jour (%s) :', 'Here is your updated proposal (%s):'),
            implode(' ; ', $changement->resume($langue)),
        )];
        foreach (NiveauBudget::cases() as $niveau) {
            $n = $sortie['niveaux'][$niveau->value];
            $lignes[] = \sprintf(
                $langue->choisir('- %s : %s, %s', '- %s: %s, %s'),
                $anglais ? $niveau->labelEnAnglais() : $niveau->label(),
                implode(', ', array_column([...$n['logements'], ...$n['activites']], 'nom')),
                Montant::euros((int) round($n['totalEuros'] * 100)),
            );
        }
        if ($sortie['budgetInsuffisant']) {
            $arbitrages = array_map(
                static fn (array $a): string => \sprintf('%s (%s)', lcfirst($anglais ? Anglais::traduire($a['libelle']) : $a['libelle']), Montant::euros((int) round($a['totalEuros'] * 100))),
                $sortie['arbitrages'],
            );
            $lignes[] = $anglais
                ? 'Even the essential level exceeds your budget'.([] === $arbitrages ? '.' : '; to get closer to it: '.implode(', ', $arbitrages).'.')
                : 'Même le niveau essentiel dépasse votre budget'.([] === $arbitrages ? '.' : ' ; pour vous en rapprocher : '.implode(', ', $arbitrages).'.');
        }
        $lignes[] = $sortie['avertissement'];

        return implode("\n", $lignes);
    }

    /**
     * La combinaison de la référence, lisible par l'agent Recherche : chaque identifiant avec son nom.
     *
     * @return array<string, mixed>
     */
    private function combinaison(Proposition $reference): array
    {
        $choix = $reference->getChoix();
        $ids = [...array_merge(...array_column($choix['etapes'] ?? [], 'logementIds')), ...$choix['activitesDemandees'] ?? [], ...$choix['activitesSuggerees'] ?? []];
        $noms = [];
        foreach ($this->prestations->findBy(['id' => $ids]) as $prestation) {
            $noms[(int) $prestation->getId()] = (string) $prestation->getNom();
        }
        $nommer = static fn (array $liste): array => array_map(static fn (int $id): array => ['id' => $id, 'nom' => $noms[$id] ?? 'prestation retirée du catalogue'], $liste);

        return [
            'destination' => $choix['destination'] ?? null,
            'etapes' => array_map(static fn (array $e): array => [
                'dateDebut' => $e['dateDebut'],
                'nombreDeNuits' => $e['nombreDeNuits'],
                'logements' => $nommer($e['logementIds']),
            ], $choix['etapes'] ?? []),
            'activitesDemandees' => $nommer($choix['activitesDemandees'] ?? []),
            'activitesSuggerees' => $nommer($choix['activitesSuggerees'] ?? []),
        ];
    }

    private function ajouter(Conversation $conversation, string $message): void
    {
        Agent::ajouterALaReponse($conversation, $message);
        $this->em->flush();
    }

    private static function evenement(string $type, mixed $donnees): ServerEvent
    {
        return new ServerEvent(json_encode($donnees, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE), $type);
    }
}
