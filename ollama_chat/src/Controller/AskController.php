<?php

namespace OllamaChat\Controller;

use Concrete\Core\Controller\AbstractController;
use Concrete\Core\Http\Request;
use Concrete\Core\Support\Facade\Application;
use OllamaChat\AccessChecker;
use OllamaChat\BlockSettings;
use OllamaChat\ChatRequest;
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
        if (!ChatRequest::isValidQuestion($question)) {
            return $this->error(400, t('Bitte eine Frage mit höchstens %s Zeichen eingeben.', ChatRequest::MAX_QUESTION_LENGTH));
        }
        $history = ChatRequest::history($data['history'] ?? []);
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

    private function error(int $status, string $message): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status);
    }
}
