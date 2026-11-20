<?php

namespace OllamaChat;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;

defined('C5_EXECUTE') or die('Access Denied.');

/** Sends a chat to the Ollama server (endpoint /api/chat). */
final class OllamaClient
{
    /** Without a graphics card, answers can take a few minutes. */
    private const TIMEOUT_SECONDS = 300;

    /** @var BlockSettings */
    private $settings;

    public function __construct(BlockSettings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages system prompt, history and question
     *
     * @throws OllamaException if Ollama could not answer
     *
     * @return array{content: string, promptTokens: int|null, answerTokens: int|null}
     */
    public function chat(array $messages): array
    {
        $client = new Client([
            'base_uri' => rtrim($this->settings->ollamaUrl, '/') . '/',
            'timeout' => self::TIMEOUT_SECONDS,
            'connect_timeout' => 5,
        ]);
        try {
            $response = $client->post('api/chat', [
                'json' => [
                    'model' => $this->settings->model,
                    'messages' => $messages,
                    'stream' => false,
                    'options' => ['num_ctx' => $this->settings->contextSize],
                ],
            ]);
        } catch (ConnectException $e) {
            throw new OllamaException(t('Der Ollama-Server unter %s ist nicht erreichbar. Läuft er (ollama serve)?', $this->settings->ollamaUrl), 0, $e);
        } catch (RequestException $e) {
            $status = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0;
            if ($status === 404) {
                throw new OllamaException(t('Das Modell %1$s ist auf dem Ollama-Server nicht installiert. Lade es mit „ollama pull %1$s“ herunter.', $this->settings->model), 0, $e);
            }
            throw new OllamaException(t('Ollama hat einen Fehler gemeldet: %s', $e->getMessage()), 0, $e);
        }

        $data = json_decode((string) $response->getBody(), true);
        if (!is_array($data) || !isset($data['message']['content'])) {
            throw new OllamaException(t('Ollama hat keine Antwort geliefert.'));
        }

        return [
            'content' => (string) $data['message']['content'],
            'promptTokens' => isset($data['prompt_eval_count']) ? (int) $data['prompt_eval_count'] : null,
            'answerTokens' => isset($data['eval_count']) ? (int) $data['eval_count'] : null,
        ];
    }
}
