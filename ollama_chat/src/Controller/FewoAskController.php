<?php

namespace OllamaChat\Controller;

use Concrete\Core\Controller\AbstractController;
use Concrete\Core\Http\Request;
use Concrete\Core\Logging\Channels;
use Concrete\Core\Logging\LoggerFactory;
use Concrete\Core\Support\Facade\Application;
use OllamaChat\ChatRequest;
use OllamaChat\Fewo\FewoAssistant;
use OllamaChat\Fewo\FewoBlockSettings;
use OllamaChat\Fewo\FewoUnavailableException;
use OllamaChat\OllamaException;
use OllamaChat\RateLimiter;
use Symfony\Component\HttpFoundation\JsonResponse;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * POST /ccm/ollama_chat/fewo with a JSON body {bID, question, history, context}: public questions about free dates
 * and prices of the holiday apartment. Answers with {answer, model, context}, or with an error status and {error}.
 * "context" is the request understood from the previous question; the page sends it back with the next one.
 *
 * Open to every visitor, so it reveals only what the occupancy plan and the price list show anyway, and limits
 * the number of questions per visitor. No CSRF token: nothing is changed, and cached pages would carry expired tokens.
 */
class FewoAskController extends AbstractController
{
    public function ask(): JsonResponse
    {
        $data = json_decode((string) Request::getInstance()->getContent(), true);
        if (!is_array($data)) {
            return $this->error(400, 'Ungültige Anfrage.');
        }
        $settings = FewoBlockSettings::forBlock((int) ($data['bID'] ?? 0));
        if ($settings === null) {
            return $this->error(404, 'Der Assistent wurde nicht gefunden. Bitte laden Sie die Seite neu.');
        }
        $question = trim((string) ($data['question'] ?? ''));
        if (!ChatRequest::isValidQuestion($question)) {
            return $this->error(400, sprintf('Bitte eine Frage mit höchstens %d Zeichen eingeben.', ChatRequest::MAX_QUESTION_LENGTH));
        }
        $history = ChatRequest::history($data['history'] ?? []);
        if ($history === null) {
            return $this->error(400, 'Der mitgeschickte Verlauf ist ungültig.');
        }
        if (!RateLimiter::allow()) {
            return $this->error(429, sprintf('Sie haben in kurzer Zeit viele Fragen gestellt. Bitte versuchen Sie es in %d Minuten erneut.', RateLimiter::windowMinutes()));
        }

        // Without a graphics card, Ollama can take longer than PHP's default limit of 30 seconds.
        @set_time_limit(330);
        try {
            $result = (new FewoAssistant())->answer($settings, $question, $history, $data['context'] ?? null);
        } catch (OllamaException $e) {
            $this->log('Ollama Chat (Ferienwohnung): ' . $e->getMessage());

            return $this->error(502, 'Der Assistent ist gerade nicht erreichbar. Bitte fragen Sie direkt bei uns an.');
        } catch (FewoUnavailableException $e) {
            $this->log('Ollama Chat (Ferienwohnung): ' . $e->getMessage());

            return $this->error(503, 'Die Abfrage von Terminen und Preisen ist gerade nicht verfügbar. Bitte fragen Sie direkt bei uns an.');
        }

        return new JsonResponse(['answer' => $result['answer'], 'model' => $settings->ollama->model, 'context' => $result['request']]);
    }

    /** Details for the site owner go to the Concrete log, not to the guest. */
    private function log(string $message): void
    {
        try {
            Application::getFacadeApplication()->make(LoggerFactory::class)
                ->createLogger(Channels::CHANNEL_APPLICATION)->warning($message);
        } catch (\Throwable $e) {
            // logging must never break the answer
        }
    }

    private function error(int $status, string $message): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status);
    }
}
