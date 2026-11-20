<?php

namespace OllamaChat\Controller;

use Concrete\Core\Controller\AbstractController;
use Concrete\Core\Http\Request;
use Concrete\Core\Support\Facade\Application;
use OllamaChat\AccessChecker;
use OllamaChat\BlockSettings;
use OllamaChat\ChatService;
use OllamaChat\OllamaException;
use Symfony\Component\HttpFoundation\JsonResponse;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * POST /ccm/ollama_chat/ask with a JSON body {token, bID, question, history}.
 * Answers with {answer, model}, or with an error status and {error}.
 */
class AskController extends AbstractController
{
    private const MAX_QUESTION_LENGTH = 4000;

    private const MAX_HISTORY_MESSAGES = 200;

    private const MAX_MESSAGE_LENGTH = 20000;

    public function ask(): JsonResponse
    {
        $app = Application::getFacadeApplication();
        $data = json_decode((string) Request::getInstance()->getContent(), true);
        if (!is_array($data)) {
            return $this->error(400, t('Ungültige Anfrage.'));
        }
        if (!$app->make('token')->validate(AccessChecker::TOKEN_ACTION, (string) ($data['token'] ?? ''))) {
            return $this->error(403, t('Die Sitzung ist abgelaufen. Bitte lade die Seite neu.'));
        }
        if (!AccessChecker::canUse()) {
            return $this->error(403, t('Nur angemeldete Benutzer mit Zugang zum Dashboard dürfen Fragen stellen.'));
        }
        $settings = BlockSettings::forBlock((int) ($data['bID'] ?? 0));
        if ($settings === null) {
            return $this->error(404, t('Der Chat-Block wurde nicht gefunden. Bitte lade die Seite neu.'));
        }

        $question = trim((string) ($data['question'] ?? ''));
        if ($question === '' || mb_strlen($question) > self::MAX_QUESTION_LENGTH) {
            return $this->error(400, t('Bitte eine Frage mit höchstens %s Zeichen eingeben.', self::MAX_QUESTION_LENGTH));
        }
        $history = $this->validHistory($data['history'] ?? []);
        if ($history === null) {
            return $this->error(400, t('Der mitgeschickte Verlauf ist ungültig.'));
        }

        // Without a graphics card, Ollama can take longer than PHP's default limit of 30 seconds.
        @set_time_limit(330);
        try {
            $result = (new ChatService())->answer($settings, $question, $history);
        } catch (OllamaException $e) {
            return $this->error(502, $e->getMessage());
        }

        return new JsonResponse(['answer' => $result['content'], 'model' => $settings->model]);
    }

    /**
     * The history as sent by the browser, or null if it is malformed. Only questions and answers are accepted,
     * so the browser cannot smuggle in its own system prompt.
     *
     * @param mixed $history
     *
     * @return array<int, array{role: string, content: string}>|null
     */
    private function validHistory($history): ?array
    {
        if (!is_array($history) || count($history) > self::MAX_HISTORY_MESSAGES) {
            return null;
        }
        $result = [];
        foreach ($history as $message) {
            if (!is_array($message)
                || !in_array($message['role'] ?? null, ['user', 'assistant'], true)
                || !is_string($message['content'] ?? null)
                || mb_strlen($message['content']) > self::MAX_MESSAGE_LENGTH) {
                return null;
            }
            $result[] = ['role' => $message['role'], 'content' => $message['content']];
        }

        return $result;
    }

    private function error(int $status, string $message): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status);
    }
}
