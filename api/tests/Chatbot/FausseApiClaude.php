<?php

namespace App\Tests\Chatbot;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Remplace l'API Claude en test (framework.http_client.mock_response_factory) : rend les réponses prévues
 * par le test, dans l'ordre, au format Server-Sent Events de l'API, et garde les requêtes reçues.
 */
final class FausseApiClaude
{
    /** @var list<MockResponse> */
    private array $reponses = [];

    /** @var list<array{url: string, cle: string, corps: array<string, mixed>}> */
    public array $requetes = [];

    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(string $methode, string $url, array $options = []): ResponseInterface
    {
        $corps = json_decode((string) ($options['body'] ?? '{}'), true, flags: \JSON_THROW_ON_ERROR);
        $this->requetes[] = [
            'url' => $url,
            // En-têtes normalisés : « x-api-key: valeur ».
            'cle' => substr((string) ($options['normalized_headers']['x-api-key'][0] ?? ''), \strlen('x-api-key: ')),
            'corps' => \is_array($corps) ? $corps : [],
        ];

        return array_shift($this->reponses) ?? throw new \LogicException(\sprintf('Aucune réponse prévue pour %s %s.', $methode, $url));
    }

    public function prevoir(MockResponse ...$reponses): void
    {
        array_push($this->reponses, ...$reponses);
    }

    /** Réponse finale : un bloc de réflexion (texte masqué, comme sur Haiku 5.5), puis le texte en deux morceaux. */
    public static function texte(string $texte): MockResponse
    {
        $milieu = intdiv(mb_strlen($texte), 2);

        return self::flux([
            ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'thinking', 'thinking' => '', 'signature' => '']],
            ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'signature_delta', 'signature' => 'signature-'.md5($texte)]],
            ['type' => 'content_block_stop', 'index' => 0],
            ['type' => 'content_block_start', 'index' => 1, 'content_block' => ['type' => 'text', 'text' => '']],
            ['type' => 'content_block_delta', 'index' => 1, 'delta' => ['type' => 'text_delta', 'text' => mb_substr($texte, 0, $milieu)]],
            ['type' => 'content_block_delta', 'index' => 1, 'delta' => ['type' => 'text_delta', 'text' => mb_substr($texte, $milieu)]],
            ['type' => 'content_block_stop', 'index' => 1],
        ], 'end_turn');
    }

    /**
     * Appel d'un outil, son entrée reçue en morceaux.
     *
     * @param array<string, mixed> $entree
     */
    public static function appelOutil(string $nom, array $entree, string $id = 'toolu_01'): MockResponse
    {
        $json = json_encode((object) $entree, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
        $milieu = intdiv(\strlen($json), 2);

        return self::flux([
            ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'tool_use', 'id' => $id, 'name' => $nom, 'input' => new \stdClass()]],
            ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'input_json_delta', 'partial_json' => substr($json, 0, $milieu)]],
            ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'input_json_delta', 'partial_json' => substr($json, $milieu)]],
            ['type' => 'content_block_stop', 'index' => 0],
        ], 'tool_use');
    }

    public static function erreur(int $statut, string $message): MockResponse
    {
        return new MockResponse(
            json_encode(['type' => 'error', 'error' => ['type' => 'api_error', 'message' => $message]], \JSON_THROW_ON_ERROR),
            ['http_code' => $statut, 'response_headers' => ['content-type' => 'application/json']],
        );
    }

    /**
     * @param list<array<string, mixed>> $blocs
     */
    private static function flux(array $blocs, string $stopReason): MockResponse
    {
        $evenements = [
            ['type' => 'message_start', 'message' => ['id' => 'msg_test', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-haiku-5-5', 'content' => [], 'stop_reason' => null, 'usage' => ['input_tokens' => 1200, 'output_tokens' => 1]]],
            ['type' => 'ping'],
            ...$blocs,
            ['type' => 'message_delta', 'delta' => ['stop_reason' => $stopReason], 'usage' => ['output_tokens' => 42]],
            ['type' => 'message_stop'],
        ];

        // Un morceau réseau par événement, coupé au milieu pour vérifier la reconstitution du flux.
        $morceaux = [];
        foreach ($evenements as $evenement) {
            $sse = \sprintf("event: %s\ndata: %s\n\n", $evenement['type'], json_encode($evenement, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE));
            $coupure = intdiv(\strlen($sse), 2);
            $morceaux[] = substr($sse, 0, $coupure);
            $morceaux[] = substr($sse, $coupure);
        }

        return new MockResponse($morceaux, ['http_code' => 200, 'response_headers' => ['content-type' => 'text/event-stream']]);
    }
}
