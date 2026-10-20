<?php

namespace App\Chatbot;

use App\Chatbot\Outil\EnregistrerExigences;
use App\Chatbot\Outil\EnregistrerProposition;
use App\Chatbot\Outil\Outil;
use App\Chatbot\Outil\TransmettreAuConseiller;
use App\Chiffrage\ChiffrageCache;
use App\Chiffrage\Periode;
use App\Entity\Conversation;
use App\Entity\Demande;
use App\Enum\AgentIA;
use App\Enum\NiveauBudget;
use App\Repository\DestinationRepository;
use App\Repository\PrestationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\ServerEvent;

/**
 * Agent Chiffrage et rédaction (ticket #207, prompts/redaction.md) : à partir de la combinaison retenue par l'agent
 * Recherche, il rédige la première proposition (programme jour par jour, points forts, argumentaire) et l'enregistre
 * avec enregistrer_proposition, qui la valide et l'enregistre en brouillon.
 *
 * Le conseiller relit le brouillon et l'envoie au client depuis la fiche demande du backoffice. Le client lit
 * seulement un message du serveur qui le lui annonce. Si la rédaction échoue, le serveur transmet la demande au
 * conseiller et prévient le client.
 */
final class AgentRedaction extends Agent
{
    /** Décision D22 : Sonnet 5.5 pour la recherche, le chiffrage et la rédaction. */
    public const MODELE = 'claude-sonnet-5-5';

    public const MESSAGE_BROUILLON = 'Votre proposition détaillée, avec le programme jour par jour, est prête : votre conseiller la vérifie et vous l\'envoie dès qu\'il l\'a validée.';

    /**
     * @param iterable<Outil> $outils
     */
    public function __construct(
        ClientClaude $claude,
        EntityManagerInterface $em,
        LoggerInterface $logger,
        #[AutowireIterator(Outil::TAG)]
        iterable $outils,
        #[Autowire('%kernel.project_dir%/prompts/redaction.md')]
        string $fichierPrompt,
        private readonly ChiffrageCache $cache,
        private readonly DestinationRepository $destinations,
        private readonly PrestationRepository $prestations,
    ) {
        parent::__construct($claude, $em, $logger, $outils, $fichierPrompt);
    }

    /**
     * @return \Generator<int, ServerEvent>
     */
    public function rediger(Conversation $conversation, string $chiffrageId): \Generator
    {
        $demande = $conversation->getDemande() ?? throw new \LogicException('Conversation sans demande.');
        $contexte = new ContexteAgent($demande, $conversation, AgentIA::Chiffrage);
        $chiffrage = $this->cache->retrouver($demande, $chiffrageId);
        $tour = null;

        if (null !== $chiffrage && isset($chiffrage['combinaison'])) {
            $tour = new Tour([['role' => 'user', 'content' => [['type' => 'text', 'text' => $this->premierMessage($conversation, $chiffrageId, $chiffrage)]]]]);
            foreach ($this->boucle($contexte, $tour, static function (): void {}) as $evenement) {
                // Rien de ce que l'agent écrit ne va au client ; ses erreurs sont traitées ci-dessous.
                if ('outil' === $evenement->getType()) {
                    yield $evenement;
                }
            }
            if (null !== $tour->sortie('enregistrer_proposition')) {
                $this->ajouterALaConversation($conversation, self::MESSAGE_BROUILLON);
                yield self::evenement('texte', ['texte' => "\n\n".self::MESSAGE_BROUILLON]);

                return;
            }
        }

        // Le client a déjà la combinaison dans le chat : un conseiller termine la proposition détaillée (l'agent a pu
        // la lui transmettre lui-même).
        $transmission = $tour?->sortie('transmettre_au_conseiller') ?? $this->outil('transmettre_au_conseiller')->executer($contexte, [
            'motif' => 'autre',
            'resume' => \sprintf('La rédaction automatique de la proposition n\'a pas abouti (chiffrage %s) : programme jour par jour à rédiger à partir de la combinaison présentée au client.', $chiffrageId),
        ]);
        $message = 'Je n\'ai pas pu mettre en forme votre programme détaillé. '.$transmission['messagePourLeClient'];
        $conversation->ajouterMessage('assistant', $message);
        $this->em->flush();
        yield self::evenement('texte', ['texte' => "\n\n".$message]);
    }

    /**
     * Tout ce qu'il faut pour rédiger, sans autre recherche : le calendrier jour par jour, la fiche et les messages
     * du client, les faits qui justifient le choix, les niveaux et la fiche de chaque prestation retenue.
     *
     * @param array<string, mixed> $chiffrage
     */
    private function premierMessage(Conversation $conversation, string $chiffrageId, array $chiffrage): string
    {
        $demande = $conversation->getDemande() ?? throw new \LogicException('Conversation sans demande.');
        /** @var array{destination: array{slug: string}, etapes: list<array{dateDebut: string, nombreDeNuits: int, logements: list<array{id: int, nom: string}>}>, justification: array<string, mixed>, niveaux: array<string, array{logements: list<array{id: int, nom: string}>, activites: list<array{id: int, nom: string}>, transport: string, totalEuros: int|float, parPersonneEuros: int|float}>} $combinaison */
        $combinaison = $chiffrage['combinaison'];
        $niveau = self::niveauConseille($demande, $combinaison['niveaux']);
        $destination = $this->destinations->findOneBy(['slug' => $combinaison['destination']['slug']]);

        $sections = [
            'Le client a confirmé sa demande et la combinaison ci-dessous lui a été présentée dans le chat. Rédige sa proposition et enregistre-la.',
            'chiffrageId : '.$chiffrageId,
            \sprintf('Niveau à détailler (niveauDetaille) : %s.', $niveau->value),
            "Calendrier, un jour de programme par ligne :\n".self::calendrier($combinaison['etapes'], $combinaison['niveaux'][$niveau->value]['logements']),
            'Fiche demande : '.self::json(EnregistrerExigences::fiche($demande)),
            "Messages du client, dans l'ordre :\n".self::messagesDuClient($conversation),
            'Destination : '.self::json([
                'ville' => $destination?->getVille(),
                'pays' => $destination?->getPays(),
                'description' => $destination?->getDescription(),
                'pointsForts' => $destination?->getPointsForts() ?? [],
            ]),
            'Faits vérifiés qui justifient le choix : '.self::json($combinaison['justification']),
            'Niveaux de budget : '.self::json(array_map(static fn (array $n): array => [
                'logements' => array_column($n['logements'], 'nom'),
                'activites' => array_column($n['activites'], 'nom'),
                'transport' => $n['transport'],
                'totalEuros' => $n['totalEuros'],
            ], $combinaison['niveaux'])),
            'Prestations retenues (à présenter toutes, et elles seules) : '.self::json($this->fiches($combinaison['niveaux'])),
        ];

        return implode("\n\n", $sections);
    }

    /**
     * Équilibré, sauf si son total dépasse le budget du client : l'essentiel, le plus proche de ce qu'il peut payer.
     *
     * @param array<string, array{totalEuros: int|float, parPersonneEuros: int|float}> $niveaux
     */
    public static function niveauConseille(Demande $demande, array $niveaux): NiveauBudget
    {
        $budget = $demande->getBudgetCentimes();
        if (null === $budget) {
            return NiveauBudget::Equilibre;
        }
        $equilibre = $niveaux[NiveauBudget::Equilibre->value];
        $prix = $demande->isBudgetParPersonne() ? $equilibre['parPersonneEuros'] : $equilibre['totalEuros'];

        return $prix * 100 <= $budget ? NiveauBudget::Equilibre : NiveauBudget::Essentiel;
    }

    /**
     * « Jour 1, samedi 12 juin 2027 : arrivée, nuit à Suite Caldera Oia » … « Jour 8, samedi 19 juin 2027 : départ ».
     *
     * @param list<array{dateDebut: string, nombreDeNuits: int}> $etapes
     * @param list<array{id: int, nom: string}>                  $logements logements du niveau détaillé, un par étape
     */
    private static function calendrier(array $etapes, array $logements): string
    {
        $lignes = [];
        $jour = 1;
        foreach ($etapes as $index => $etape) {
            $debut = new \DateTimeImmutable($etape['dateDebut']);
            for ($nuit = 0; $nuit < $etape['nombreDeNuits']; ++$nuit) {
                $quoi = 0 === $nuit ? (0 === $index ? 'arrivée' : 'changement d\'étape').', nuit à ' : 'nuit à ';
                $lignes[] = \sprintf('- Jour %d, %s : %s%s', $jour++, Periode::jour($debut->modify(\sprintf('+%d days', $nuit))), $quoi, $logements[$index]['nom'] ?? 'logement de l\'étape');
            }
        }
        $derniere = end($etapes) ?: throw new \LogicException('Combinaison sans étape.');
        $depart = (new \DateTimeImmutable($derniere['dateDebut']))->modify(\sprintf('+%d days', $derniere['nombreDeNuits']));
        $lignes[] = \sprintf('- Jour %d, %s : départ', $jour, Periode::jour($depart));

        return implode("\n", $lignes);
    }

    /**
     * La fiche de chaque prestation retenue par au moins un niveau, avec les niveaux qui la retiennent.
     *
     * @param array<string, array{logements: list<array{id: int}>, activites: list<array{id: int}>}> $niveaux
     *
     * @return list<array<string, mixed>>
     */
    private function fiches(array $niveaux): array
    {
        $parId = [];
        foreach ($niveaux as $nom => $niveau) {
            foreach ([...$niveau['logements'], ...$niveau['activites']] as $prestation) {
                $parId[$prestation['id']][] = $nom;
            }
        }

        $fiches = [];
        foreach ($this->prestations->findBy(['id' => array_keys($parId)], ['type' => 'ASC', 'id' => 'ASC']) as $prestation) {
            $fiches[] = array_filter([
                'id' => $prestation->getId(),
                'type' => $prestation->getType()?->value,
                'nom' => $prestation->getNom(),
                'categorie' => $prestation->getCategorie(),
                'description' => $prestation->getDescription(),
                'equipements' => $prestation->getEquipements(),
                'envies' => $prestation->getEnvies(),
                'dureeMinutes' => $prestation->getDureeMinutes(),
                'ageMinimum' => $prestation->getAgeMinimum(),
                'niveaux' => $parId[(int) $prestation->getId()],
            ], static fn ($v): bool => null !== $v && [] !== $v);
        }

        return $fiches;
    }

    /**
     * @param array<mixed> $donnees
     */
    private static function json(array $donnees): string
    {
        return json_encode($donnees, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION);
    }

    protected function agent(): AgentIA
    {
        return AgentIA::Chiffrage;
    }

    protected function modele(): string
    {
        return self::MODELE;
    }

    /** Rédiger vite (moins de 2 minutes en tout, #207) : tout est dans le premier message, rien à chercher. */
    protected function effort(): string
    {
        return 'low';
    }

    protected function outils(): array
    {
        return [EnregistrerProposition::class, TransmettreAuConseiller::class];
    }

    protected function outilFinal(): string
    {
        return 'enregistrer_proposition';
    }

    /** L'enregistrement, et deux corrections si la proposition est refusée. */
    protected function appelsMax(): int
    {
        return 3;
    }
}
