<?php

namespace App\Chatbot\Evaluation;

/**
 * Tranche ce que le ComparateurFiche ne sait pas comparer mot à mot (lieu, envies, contraintes) et relit la
 * conversation : le chatbot a-t-il annoncé un prix ferme ou une disponibilité ? Un modèle différent de ceux
 * du chatbot et du client simulé, pour rester indépendant.
 */
final class Juge
{
    public const MODELE = 'claude-opus-5-5';

    public const VERDICTS = ['ok', 'partiel', 'manquant', 'faux', 'en trop'];

    private const SYSTEME = <<<'TXT'
        Tu évalues le chatbot d'une agence de voyages, qui recueille les exigences d'un client dans une fiche.
        Pour chaque champ, compare la valeur obtenue à la valeur attendue, sur le sens et non sur les mots :
        - ok : même sens (synonymes, reformulation, précision en plus acceptés). Pour envies et contraintes, chaque élément attendu est couvert ; un élément en plus qui découle de ce qu'a dit le client est accepté.
        - partiel : une partie seulement des éléments attendus est couverte.
        - manquant : rien d'attendu n'a été enregistré.
        - faux : la valeur contredit l'attendu.
        - en trop : rien n'était attendu et la valeur n'est pas tirée de la conversation.
        Puis relis les messages du chatbot :
        - prixOuDisponibiliteAnnonces vaut true s'il a annoncé un prix ferme ou garanti une disponibilité (une fourchette ou un budget répété ne comptent pas).
        - questionsManquees : chaque exigence citée par le client doit déclencher la question qui lui est propre, sauf si le client y a déjà répondu de lui-même. Liste les questions attendues que le chatbot n'a jamais posées, d'après cette table :
          circuit → étapes ou régions, bouger souvent ou rester ; groupe → taille exacte, nature du groupe ; voyage de noces → date du mariage, ce qui le rendrait inoubliable ; entreprise → objectif de l'événement, participants, besoins de salle et d'activités ; zone large → ville de départ, bouger ou rester ; température → dates fixes ou flexibles ; budget → total ou par personne, transport inclus ; nombre de jours sans dates → dates ou période ; enfants → âge de chacun ; mobilité réduite → type de besoin ; régime ou allergie → gravité, et l'annonce qu'elle sera signalée au conseiller ; envie floue → trois ambiances au choix ; changement en cours → reformulation et confirmation.
        - oublisDuRecapitulatif : exigences connues (type, lieu, dates et flexibilité, durée, températures, voyageurs et âges, ville de départ, budget, envies, contraintes) absentes du dernier récapitulatif présenté au client. S'il n'y a eu aucun récapitulatif, écris « aucun récapitulatif ».
        Explications en une phrase, en français.
        TXT;

    private const VERDICT = [
        'type' => 'object',
        'properties' => [
            'verdict' => ['type' => 'string', 'enum' => self::VERDICTS],
            'explication' => ['type' => 'string'],
        ],
        'required' => ['verdict', 'explication'],
        'additionalProperties' => false,
    ];

    public function __construct(private readonly RequeteJson $requete)
    {
    }

    /**
     * @param array<string, array{statut: string, attendu: mixed, obtenu: mixed}> $aJuger   champs à juger
     * @param list<array{role: 'client'|'chatbot', contenu: string}>              $echanges
     *
     * @return array{champs: array<string, array{verdict: string, explication: string}>, prixOuDisponibiliteAnnonces: bool, questionsManquees: list<string>, oublisDuRecapitulatif: list<string>, remarque: string}
     */
    public function juger(array $aJuger, array $echanges): array
    {
        $champs = [];
        foreach (array_keys($aJuger) as $champ) {
            $champs[$champ] = self::VERDICT;
        }
        $schema = [
            'type' => 'object',
            'properties' => [
                'champs' => ['type' => 'object', 'properties' => $champs ?: new \stdClass(), 'required' => array_keys($champs), 'additionalProperties' => false],
                'prixOuDisponibiliteAnnonces' => ['type' => 'boolean'],
                'questionsManquees' => ['type' => 'array', 'items' => ['type' => 'string']],
                'oublisDuRecapitulatif' => ['type' => 'array', 'items' => ['type' => 'string']],
                'remarque' => ['type' => 'string', 'description' => 'Le défaut le plus gênant du chatbot dans cette conversation, ou « Aucune ».'],
            ],
            'required' => ['champs', 'prixOuDisponibiliteAnnonces', 'questionsManquees', 'oublisDuRecapitulatif', 'remarque'],
            'additionalProperties' => false,
        ];

        $fiche = array_map(static fn (array $c) => ['attendu' => $c['attendu'], 'obtenu' => $c['obtenu']], $aJuger);
        $transcription = array_map(
            static fn (array $e) => \sprintf('%s : %s', 'client' === $e['role'] ? 'CLIENT' : 'CHATBOT', $e['contenu']),
            $echanges,
        );
        $message = \sprintf(
            "Champs à juger :\n%s\n\nConversation :\n%s",
            json_encode($fiche, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR),
            implode("\n\n", $transcription),
        );

        $reponse = $this->requete->demander(self::MODELE, 'medium', self::SYSTEME, $message, $schema);

        /** @var array<string, array{verdict: string, explication: string}> $verdicts */
        $verdicts = \is_array($reponse['champs'] ?? null) ? $reponse['champs'] : [];

        return [
            'champs' => $verdicts,
            'prixOuDisponibiliteAnnonces' => true === ($reponse['prixOuDisponibiliteAnnonces'] ?? false),
            'questionsManquees' => self::textes($reponse['questionsManquees'] ?? []),
            'oublisDuRecapitulatif' => self::textes($reponse['oublisDuRecapitulatif'] ?? []),
            'remarque' => (string) ($reponse['remarque'] ?? ''),
        ];
    }

    /**
     * @return list<string>
     */
    private static function textes(mixed $valeurs): array
    {
        return \is_array($valeurs) ? array_values(array_map(static fn (mixed $v) => (string) $v, $valeurs)) : [];
    }
}
