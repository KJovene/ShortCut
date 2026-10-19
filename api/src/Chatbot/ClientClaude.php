<?php

namespace App\Chatbot;

use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Un appel à l'API Messages de Claude en streaming (Server-Sent Events), avec Symfony HttpClient.
 * La clé API est ajoutée par le client « claude.client » (config/packages/framework.yaml).
 *
 * Le flux est lu au fil de l'eau : chaque morceau de texte est rendu par le générateur dès réception, et le
 * message complet (blocs de réflexion, de texte et d'appels d'outils) est sa valeur de retour.
 */
final class ClientClaude
{
    public function __construct(
        #[Target('claude.client')]
        private readonly HttpClientInterface $claudeClient,
    ) {
    }

    /**
     * @param array<string, mixed> $parametres corps de la requête POST /v1/messages, sans « stream »
     *
     * @return \Generator<int, string, mixed, MessageClaude> morceaux de texte, puis le message complet
     *
     * @throws ErreurClaude
     */
    public function diffuser(array $parametres): \Generator
    {
        try {
            $reponse = $this->claudeClient->request('POST', '/v1/messages', [
                'body' => json_encode($parametres + ['stream' => true], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION),
                'headers' => ['Content-Type' => 'application/json', 'Accept' => 'text/event-stream'],
                'buffer' => false,
            ]);

            $statut = $reponse->getStatusCode();
            if (200 !== $statut) {
                $corps = json_decode($reponse->getContent(false), true);
                $detail = \is_array($corps) ? ($corps['error']['message'] ?? null) : null;

                throw new ErreurClaude(\sprintf('API Claude : HTTP %d (%s).', $statut, \is_string($detail) ? $detail : 'sans détail'), $statut);
            }

            $message = new MessageClaude();
            $tampon = '';
            foreach ($this->claudeClient->stream($reponse) as $morceau) {
                $tampon .= str_replace("\r\n", "\n", $morceau->getContent());

                // Un événement SSE se termine par une ligne vide.
                while (false !== $fin = strpos($tampon, "\n\n")) {
                    $evenement = substr($tampon, 0, $fin);
                    $tampon = substr($tampon, $fin + 2);

                    $texte = $this->traiter($message, $evenement);
                    if (null !== $texte) {
                        yield $texte;
                    }
                }
            }
        } catch (ExceptionInterface|\JsonException $e) {
            throw new ErreurClaude('API Claude : '.$e->getMessage(), null, $e);
        }

        if (!$message->termine) {
            throw new ErreurClaude('API Claude : flux interrompu avant la fin du message.');
        }

        return $message;
    }

    /**
     * Applique un événement du flux au message en cours ; renvoie le texte à diffuser, s'il y en a.
     */
    private function traiter(MessageClaude $message, string $evenement): ?string
    {
        $donnees = '';
        foreach (explode("\n", $evenement) as $ligne) {
            if (str_starts_with($ligne, 'data:')) {
                $donnees .= ltrim(substr($ligne, 5));
            }
        }
        if ('' === $donnees) {
            return null;
        }

        // Objets et non tableaux : les blocs sont renvoyés à l'API à l'identique (« {} » reste « {} »).
        $data = json_decode($donnees, false, flags: \JSON_THROW_ON_ERROR);
        if (!$data instanceof \stdClass) {
            return null;
        }

        switch ($data->type ?? null) {
            case 'message_start':
                $message->modele = (string) ($data->message->model ?? '');
                $message->ajouterUsage($data->message->usage ?? null);
                break;

            case 'content_block_start':
                $message->blocs[(int) $data->index] = $data->content_block;
                if ('tool_use' === ($data->content_block->type ?? null)) {
                    $message->entreesBrutes[(int) $data->index] = '';
                }
                break;

            case 'content_block_delta':
                $bloc = $message->blocs[(int) $data->index] ?? null;
                $delta = $data->delta;
                if (!$bloc instanceof \stdClass) {
                    break;
                }
                switch ($delta->type ?? null) {
                    case 'text_delta':
                        $bloc->text .= $delta->text;

                        return $delta->text;
                    case 'input_json_delta':
                        $message->entreesBrutes[(int) $data->index] .= $delta->partial_json;
                        break;
                    case 'thinking_delta':
                        $bloc->thinking .= $delta->thinking;
                        break;
                    case 'signature_delta':
                        $bloc->signature = $delta->signature;
                        break;
                }
                break;

            case 'content_block_stop':
                $message->terminerBloc((int) $data->index);
                break;

            case 'message_delta':
                $message->stopReason = $data->delta->stop_reason ?? $message->stopReason;
                $message->ajouterUsage($data->usage ?? null);
                break;

            case 'message_stop':
                $message->termine = true;
                break;

            case 'error':
                $type = (string) ($data->error->type ?? 'erreur');

                throw new ErreurClaude(\sprintf('API Claude : %s (%s).', $type, (string) ($data->error->message ?? '')), 'overloaded_error' === $type ? 529 : null);
        }

        return null;
    }
}
