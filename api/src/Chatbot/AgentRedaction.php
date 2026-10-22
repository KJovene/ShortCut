<?php

namespace App\Chatbot;

use App\Chatbot\Outil\EnregistrerExigences;
use App\Chatbot\Outil\EnregistrerProposition;
use App\Chatbot\Outil\Outil;
use App\Chatbot\Outil\RetenirCombinaison;
use App\Chatbot\Outil\TransmettreAuConseiller;
use App\Chiffrage\ChiffrageCache;
use App\Chiffrage\Periode;
use App\Confirmation\PropositionClient;
use App\Entity\Conversation;
use App\Entity\Proposition;
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
 * Le conseiller relit le brouillon et l'envoie au client depuis la fiche demande du backoffice. D'ici là, le client
 * consulte ce récapitulatif depuis le chat (carte « recapitulatif ») et son espace, dans le format de « Ma
 * proposition » ; s'il reste des options auxquelles il n'a pas répondu, le message du serveur les lui propose. Si la
 * rédaction échoue, le serveur transmet la demande au conseiller et prévient le client.
 *
 * Après une modification du client (ticket #211), il écrit la version suivante en reprenant le programme précédent ;
 * si une version a déjà été envoyée, elle aussi reste en brouillon jusqu'à ce que le conseiller l'envoie, et le client
 * garde la précédente d'ici là.
 */
final class AgentRedaction extends Agent
{
    /** Décision D22 : Sonnet 5.5 pour la recherche, le chiffrage et la rédaction. */
    public const MODELE = 'claude-sonnet-5-5';

    public const MESSAGE_PRET = 'Votre récapitulatif est prêt : programme jour par jour, hébergements et budget.';
    public const MESSAGE_A_JOUR = 'Votre récapitulatif est à jour%s.';
    public const MESSAGE_QUESTION_OPTIONS = 'Avant que votre conseiller le relise, une option vous tente-t-elle, ou gardez-vous le voyage tel quel ?';
    public const MESSAGE_SUITE = 'Votre conseiller le relit, vérifie chaque prix et chaque place, puis vous l\'envoie sous 2 jours ouvrés. Vous le retrouvez à tout moment dans votre espace.';
    public const MESSAGE_NOUVELLE_VERSION = 'Votre conseiller relit maintenant la version %d de votre proposition, avec votre changement : il vérifie chaque prix et chaque place, puis vous l\'envoie. D\'ici là, la version %d reste sur votre page « Ma proposition ».';
    public const MESSAGE_PRET_EN = 'Your summary is ready: day-by-day itinerary, accommodation and budget.';
    public const MESSAGE_A_JOUR_EN = 'Your summary is up to date%s.';
    public const MESSAGE_QUESTION_OPTIONS_EN = 'Before your advisor reviews it, would you like to add one of the options, or keep the trip as it is?';
    public const MESSAGE_SUITE_EN = 'Your advisor reviews it, checks every price and every place, then sends it to you within 2 business days. You can find it at any time in your account.';
    public const MESSAGE_NOUVELLE_VERSION_EN = 'Your advisor is now reviewing version %d of your proposal, with your change: they check every price and every place, then send it to you. Until then, version %d stays on your “My proposal” page.';

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
     * @param list<string> $optionsAjoutees options que le client vient d'ajouter (« Cérémonie du thé en kimono »)
     * @param bool         $optionsChoisies le client a déjà répondu aux options : elles ne lui sont plus proposées
     *
     * @return \Generator<int, ServerEvent, mixed, bool> vrai si la conversation attend la réponse du client aux options
     */
    public function rediger(Conversation $conversation, string $chiffrageId, ?Modification $modification = null, array $optionsAjoutees = [], bool $optionsChoisies = false): \Generator
    {
        $demande = $conversation->getDemande() ?? throw new \LogicException('Conversation sans demande.');
        $contexte = new ContexteAgent($demande, $conversation, AgentIA::Chiffrage);
        $chiffrage = $this->cache->retrouver($demande, $chiffrageId);
        $tour = null;

        if (null !== $chiffrage && isset($chiffrage['combinaison'])) {
            $tour = new Tour([['role' => 'user', 'content' => [['type' => 'text', 'text' => $this->premierMessage($conversation, $chiffrageId, $chiffrage, $modification, $optionsAjoutees)]]]]);
            foreach ($this->boucle($contexte, $tour, static function (): void {}) as $evenement) {
                // Rien de ce que l'agent écrit ne va au client ; ses erreurs sont traitées ci-dessous.
                if ('outil' === $evenement->getType()) {
                    yield $evenement;
                }
            }
            $enregistree = $tour->sortie('enregistrer_proposition');
            $proposition = null === $enregistree ? null : $demande->getPropositions()->findFirst(static fn (int $i, Proposition $p): bool => $p->getVersion() === (int) $enregistree['version']);
            if (null !== $proposition) {
                if ($optionsChoisies) {
                    $proposition->setOptionsChoisiesLe(new \DateTimeImmutable());
                }

                return yield from $this->annoncer($conversation, $proposition, null !== $modification || $optionsChoisies, $optionsAjoutees);
            }
        }

        // Le client a déjà la combinaison dans le chat : un conseiller termine la proposition détaillée (l'agent a pu
        // la lui transmettre lui-même).
        $transmission = $tour?->sortie('transmettre_au_conseiller') ?? $this->outil('transmettre_au_conseiller')->executer($contexte, [
            'motif' => 'autre',
            'resume' => \sprintf('La rédaction automatique de la proposition n\'a pas abouti (chiffrage %s) : programme jour par jour à rédiger à partir de la combinaison présentée au client.', $chiffrageId),
        ]);
        $message = $demande->getLangue()->choisir('Je n\'ai pas pu mettre en forme votre programme détaillé. ', 'I could not put together your detailed itinerary. ')
            .$transmission['messagePourLeClient'];
        $conversation->ajouterMessage('assistant', $message);
        $this->em->flush();
        yield self::evenement('texte', ['texte' => "\n\n".$message]);

        return false;
    }

    /**
     * Le message du serveur une fois la version enregistrée. Avant toute version envoyée, c'est le récapitulatif du
     * client : une carte avec son lien, et la question des options s'il n'y a pas encore répondu. Après, c'est un
     * brouillon pour le conseiller : le client garde la version qu'il a reçue.
     *
     * @param list<string> $optionsAjoutees
     *
     * @return \Generator<int, ServerEvent, mixed, bool> vrai si la conversation attend la réponse du client aux options
     */
    private function annoncer(Conversation $conversation, Proposition $proposition, bool $miseAJour, array $optionsAjoutees): \Generator
    {
        $demande = $conversation->getDemande() ?? throw new \LogicException('Conversation sans demande.');
        $langue = $demande->getLangue();

        if ($proposition !== PropositionClient::apercu($demande)) {
            $recue = max(0, ...array_map(
                static fn (Proposition $p): int => null !== $p->getEnvoyeeLe() || null !== $p->getConfirmeeLe() ? $p->getVersion() : 0,
                $demande->getPropositions()->toArray(),
            ));
            $message = \sprintf($langue->choisir(self::MESSAGE_NOUVELLE_VERSION, self::MESSAGE_NOUVELLE_VERSION_EN), $proposition->getVersion(), $recue);
            $this->ajouterALaConversation($conversation, $message);
            yield self::evenement('texte', ['texte' => "\n\n".$message]);

            return false;
        }

        $question = [] !== $proposition->getOptions() && null === $proposition->getOptionsChoisiesLe();
        $avec = [] === $optionsAjoutees ? '' : $langue->choisir(', avec ', ', with ').implode($langue->choisir(' et ', ' and '), $optionsAjoutees);
        $message = ($miseAJour ? \sprintf($langue->choisir(self::MESSAGE_A_JOUR, self::MESSAGE_A_JOUR_EN), $avec) : $langue->choisir(self::MESSAGE_PRET, self::MESSAGE_PRET_EN))
            .' '.($question ? $langue->choisir(self::MESSAGE_QUESTION_OPTIONS, self::MESSAGE_QUESTION_OPTIONS_EN) : $langue->choisir(self::MESSAGE_SUITE, self::MESSAGE_SUITE_EN));
        $carte = self::carte($proposition, $message, $question);
        $this->ajouterALaConversation($conversation, $message, $carte);
        yield self::evenement('recapitulatif', $carte);

        return $question;
    }

    /**
     * La carte « recapitulatif » du chat : le lien de la page, le total du niveau détaillé et, si la question est
     * posée, les options que le client peut ajouter d'un clic.
     *
     * @return array<string, mixed>
     */
    public static function carte(Proposition $proposition, string $message, bool $question): array
    {
        $demande = $proposition->getDemande();

        return [
            'type' => 'recapitulatif',
            'texte' => $message,
            'jeton' => $proposition->getJetonPublic(),
            'version' => $proposition->getVersion(),
            'ville' => $proposition->getDestination()?->getVille() ?? $demande?->getLieuOuZone(),
            'jours' => \count($proposition->getProgramme()),
            'niveau' => $proposition->getNiveauBudget()->value,
            'totalCentimes' => $proposition->getTotalCentimes(),
            'parPersonneCentimes' => $proposition->getTotalParPersonneCentimes(),
            'voyageurs' => (int) $demande?->getAdultes() + (int) $demande?->getEnfants(),
            'options' => $question ? array_map(static fn (array $o): array => [
                'id' => $o['id'],
                'nom' => $o['nom'],
                'prixCentimes' => $o['prixCentimes'],
            ], $proposition->getOptions()) : [],
        ];
    }

    /**
     * Tout ce qu'il faut pour rédiger, sans autre recherche : le calendrier jour par jour, la fiche et les messages
     * du client, les faits qui justifient le choix, les niveaux et la fiche de chaque prestation retenue.
     *
     * @param array<string, mixed> $chiffrage
     * @param list<string>         $optionsAjoutees
     */
    private function premierMessage(Conversation $conversation, string $chiffrageId, array $chiffrage, ?Modification $modification, array $optionsAjoutees = []): string
    {
        $demande = $conversation->getDemande() ?? throw new \LogicException('Conversation sans demande.');
        /** @var array{destination: array{slug: string}, etapes: list<array{dateDebut: string, nombreDeNuits: int, logements: list<array{id: int, nom: string}>}>, justification: array<string, mixed>, niveaux: array<string, array{logements: list<array{id: int, nom: string}>, activites: list<array{id: int, nom: string}>, transport: string, totalEuros: int|float, parPersonneEuros: int|float}>, niveauConseille?: string} $combinaison */
        $combinaison = $chiffrage['combinaison'];
        $niveau = NiveauBudget::tryFrom((string) ($combinaison['niveauConseille'] ?? '')) ?? RetenirCombinaison::niveauConseille($demande, $combinaison['niveaux']);
        $destination = $this->destinations->findOneBy(['slug' => $combinaison['destination']['slug']]);

        $sections = [
            null === $modification
                ? 'Le client a confirmé sa demande et la combinaison ci-dessous lui a été présentée dans le chat. Rédige sa proposition et enregistre-la.'
                : 'Le client a modifié sa demande et la combinaison mise à jour ci-dessous lui a été présentée dans le chat. Rédige la version suivante de sa proposition et enregistre-la.',
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
        if (null !== $modification) {
            $sections[] = $modification->pourLaRedaction();
        }
        if ([] !== $optionsAjoutees) {
            $sections[] = 'Le client vient d\'ajouter ces options, déjà comprises dans la combinaison : '.implode(', ', $optionsAjoutees).'. Place-les dans le programme et dis-le en une phrase dans pourquoi.';
        }

        return implode("\n\n", $sections).AgentRecherche::langue($demande);
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
