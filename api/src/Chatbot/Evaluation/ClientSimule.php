<?php

namespace App\Chatbot\Evaluation;

/**
 * Joue le client d'une demande test face au chatbot (#195) : il ne connaît que les messages du fichier de test
 * et ne donne une information que quand le chatbot la demande, comme un vrai client.
 */
final class ClientSimule
{
    public const MODELE = 'claude-sonnet-5-5';

    private const SYSTEME = <<<'TXT'
        Tu joues un client de l'agence de voyages ShortCut qui discute avec le chatbot du site, pour tester ce chatbot.
        Tu écris le prochain message du client, en français, court et naturel, comme on écrit dans une fenêtre de chat.

        Règles :
        - Tu ne connais que les informations des « messages prévus ». Tu réponds aux questions du chatbot avec ces informations, reformulées si besoin.
        - Ne donne pas d'un coup ce qu'on ne t'a pas demandé, sauf ce que le message prévu en cours contient déjà.
        - Si le chatbot demande une information absente des messages prévus, n'invente rien : dis que tu n'as pas de préférence ou que ça t'est égal.
        - Pour tes coordonnées, si on te les demande : prénom {{prenom}}, e-mail {{email}}. Tu ne donnes pas de téléphone.
        - Message « apres_pistes » : envoie-le quand le chatbot te propose plusieurs pistes ou destinations au choix.
        - Message « apres_proposition » : envoie-le quand le chatbot te présente un récapitulatif ou une proposition, avant de confirmer quoi que ce soit.
        - Quand le récapitulatif est juste et que tous les messages prévus ont été utilisés, confirme-le simplement.
        - termine vaut true seulement si le chatbot a clos la conversation (demande transmise, conseiller prévenu) et n'attend plus de réponse ; message est alors vide.
        TXT;

    private const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'message' => ['type' => 'string'],
            'termine' => ['type' => 'boolean'],
        ],
        'required' => ['message', 'termine'],
        'additionalProperties' => false,
    ];

    public function __construct(private readonly RequeteJson $requete)
    {
    }

    /**
     * @param array<string, mixed>                                   $demandeTest contenu du fichier de test
     * @param list<array{role: 'client'|'chatbot', contenu: string}> $echanges
     *
     * @return array{message: string, termine: bool}
     */
    public function prochainMessage(array $demandeTest, array $echanges): array
    {
        $prevus = array_map(
            static fn (array $m) => \sprintf('- [%s] %s', $m['moment'], $m['client']),
            $demandeTest['conversation'],
        );
        $transcription = array_map(
            static fn (array $e) => \sprintf('%s : %s', 'client' === $e['role'] ? 'CLIENT' : 'CHATBOT', $e['contenu']),
            $echanges,
        );

        $message = \sprintf(
            "Messages prévus du client, dans l'ordre :\n%s\n\nConversation jusqu'ici :\n%s\n\nÉcris le prochain message du CLIENT.",
            implode("\n", $prevus),
            implode("\n\n", $transcription),
        );
        $systeme = strtr(self::SYSTEME, [
            '{{prenom}}' => 'Camille',
            '{{email}}' => \sprintf('camille.test%s@exemple.fr', $demandeTest['id']),
        ]);

        $reponse = $this->requete->demander(self::MODELE, 'low', $systeme, $message, self::SCHEMA);

        return [
            'message' => trim((string) ($reponse['message'] ?? '')),
            'termine' => true === ($reponse['termine'] ?? false),
        ];
    }
}
