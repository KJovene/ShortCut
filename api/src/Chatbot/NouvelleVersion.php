<?php

namespace App\Chatbot;

use App\Chatbot\Outil\EnregistrerExigences;
use App\Chatbot\Outil\OutilException;
use App\Chatbot\Outil\RetenirCombinaison;
use App\Chiffrage\Montant;
use App\Confirmation\PropositionClient;
use App\Entity\Conversation;
use App\Entity\Demande;
use App\Entity\Proposition;
use App\Enum\AgentIA;
use App\Enum\NiveauBudget;
use App\Enum\StatutProposition;
use App\Enum\TypeProposition;
use App\Repository\PrestationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\ServerEvent;

/**
 * Nouvelle version après une modification du client dans le chat (ticket #211, US11), en moins d'une minute :
 * - rien n'a changé dans la fiche : la proposition reste valable ;
 * - dates, durée, voyageurs, budget, ville de départ ou contraintes : la même combinaison est
 *   recalculée par retenir_combinaison, sans appel à Claude, et présentée au client ;
 * - autre type de voyage, autre zone, autres envies, ou combinaison refusée aux nouvelles exigences : l'agent
 *   Recherche repart de la combinaison précédente et ne cherche que ce que le changement touche.
 * L'agent Rédaction écrit ensuite la version suivante, en brouillon : le conseiller la relit et l'envoie, comme la
 * première (« une personne compose, signe et confirme ») ; d'ici là, le client garde la précédente, ou consulte son
 * récapitulatif mis à jour si aucune version ne lui a encore été envoyée.
 *
 * Les options (appliquerLesOptions) suivent le même chemin : les activités que le client ajoute deviennent des
 * activités demandées, la combinaison est recalculée au niveau qu'il a choisi, et la rédaction la met à jour.
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
    public static function reference(Demande $demande): ?Proposition
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
     * @return \Generator<int, ServerEvent, mixed, bool> vrai si la conversation attend une réponse du client
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

            return false;
        }

        $optionsChoisies = null !== $reference->getOptionsChoisiesLe();
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

                return yield from $this->recherche->proposer($conversation, $modification->avecRefus($e->getMessage()), $optionsChoisies);
            }

            $message = \sprintf(
                $demande->getLangue()->choisir('Voici votre proposition mise à jour (%s).', 'Here is your updated proposal (%s).'),
                implode(' ; ', $changement->resume($demande->getLangue())),
            );
            $this->ajouter($conversation, $message);
            yield self::evenement('texte', ['texte' => "\n\n".$message]);
            yield from $this->presenter($conversation, $sortie);

            return yield from $this->redaction->rediger($conversation, (string) $sortie['chiffrageId'], $modification, [], $optionsChoisies);
        }

        return yield from $this->recherche->proposer($conversation, $modification, $optionsChoisies);
    }

    /**
     * La réponse du client aux options (choisir_options) : rien à recalculer s'il garde le voyage tel quel ; sinon la
     * même combinaison, avec ses options en activités demandées et au niveau qu'il a choisi, puis la rédaction.
     *
     * @param list<int> $ajouts
     *
     * @return \Generator<int, ServerEvent, mixed, bool> vrai si la conversation attend encore une réponse du client
     */
    public function appliquerLesOptions(Conversation $conversation, Proposition $reference, array $ajouts, ?NiveauBudget $niveau): \Generator
    {
        $demande = $conversation->getDemande() ?? throw new \LogicException('Conversation sans demande.');
        $langue = $demande->getLangue();

        if ([] === $ajouts && null === $niveau) {
            $reference->setOptionsChoisiesLe(new \DateTimeImmutable());
            if ($reference !== PropositionClient::apercu($demande)) {
                $this->em->flush();

                return false;
            }
            $message = $langue->choisir(AgentRedaction::MESSAGE_PRET, AgentRedaction::MESSAGE_PRET_EN).' '.$langue->choisir(AgentRedaction::MESSAGE_SUITE, AgentRedaction::MESSAGE_SUITE_EN);
            $carte = AgentRedaction::carte($reference, $message, false);
            Agent::ajouterALaReponse($conversation, $message, $carte);
            $this->em->flush();
            yield self::evenement('recapitulatif', $carte);

            return false;
        }

        $choix = $reference->getChoix();
        $noms = array_column(array_filter($reference->getOptions(), static fn (array $o): bool => \in_array($o['id'], $ajouts, true)), 'nom');
        yield self::evenement('outil', ['nom' => 'retenir_combinaison']);
        try {
            $sortie = $this->retenirCombinaison->executer(new ContexteAgent($demande, $conversation, AgentIA::Modification), [
                'destination' => $choix['destination'] ?? '',
                'etapes' => $choix['etapes'] ?? [],
                'activitesDemandees' => array_values(array_unique([...$choix['activitesDemandees'] ?? [], ...$ajouts])),
                'activitesSuggerees' => array_values(array_diff($choix['activitesSuggerees'] ?? [], $ajouts)),
                'pourquoi' => $choix['pourquoi'] ?? 'Même combinaison, avec les options choisies par le client.',
                'niveauDetaille' => ($niveau ?? $reference->getNiveauBudget())->value,
            ]);
        } catch (OutilException $e) {
            // Le choix précédent ne passe plus (tarif, dates) : le conseiller lit la demande dans la conversation.
            $this->logger->warning('Options : la combinaison de la version {version} est refusée ({raison}).', ['version' => $reference->getVersion(), 'raison' => $e->getMessage()]);
            $reference->setOptionsChoisiesLe(new \DateTimeImmutable());
            $message = \sprintf($langue->choisir(
                'Je n\'ai pas pu ajouter %s à votre récapitulatif : je le note pour votre conseiller, qui l\'ajoutera en vérifiant les prix.',
                'I could not add %s to your summary: I am noting it for your advisor, who will add it while checking the prices.',
            ), [] === $noms ? $langue->choisir('ce changement', 'this change') : implode($langue->choisir(' et ', ' and '), $noms));
            $this->ajouter($conversation, $message);
            yield self::evenement('texte', ['texte' => "\n\n".$message]);

            return false;
        }

        yield from $this->presenter($conversation, $sortie);

        return yield from $this->redaction->rediger($conversation, (string) $sortie['chiffrageId'], null, $noms, true);
    }

    /**
     * La carte de la combinaison recalculée, comme celle de la recherche.
     *
     * @param array<string, mixed> $sortie sortie de retenir_combinaison
     *
     * @return \Generator<int, ServerEvent>
     */
    private function presenter(Conversation $conversation, array $sortie): \Generator
    {
        $demande = $conversation->getDemande() ?? throw new \LogicException('Conversation sans demande.');
        $carte = ['type' => 'piste'] + Piste::depuis($sortie, $demande);
        Agent::ajouterALaReponse($conversation, Piste::texte($carte, $demande->getLangue()), $carte);
        $this->em->flush();
        yield self::evenement('piste', $carte);
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
