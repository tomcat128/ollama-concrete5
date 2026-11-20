<?php

namespace OllamaChat;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Answers a question: reads the installation data, fits data, history and question into the model's
 * context window and asks Ollama. Nothing is stored; the history comes from the browser with every question.
 */
final class ChatService
{
    /** Tokens of the context window kept free for the answer. */
    private const RESPONSE_RESERVE = 1024;

    /**
     * @param array<int, array{role: string, content: string}> $history earlier questions and answers, oldest first
     *
     * @throws OllamaException
     *
     * @return array{content: string, promptTokens: int|null, answerTokens: int|null, sentMessages: int}
     */
    public function answer(BlockSettings $settings, string $question, array $history): array
    {
        $budget = $settings->contextSize - self::RESPONSE_RESERVE;
        // At most half of the window for the installation data; history and question get the rest.
        $dataChars = ContextWindow::charsForTokens(intdiv($budget, 2));
        $system = $this->systemPrompt((new InstallationContext())->build($question, $dataChars));

        $historyBudget = $budget - ContextWindow::estimateTokens($system) - ContextWindow::estimateTokens($question);
        $context = ContextWindow::mostRecentWithinBudget($history, $historyBudget);

        $messages = array_merge(
            [['role' => 'system', 'content' => $system]],
            $context,
            [['role' => 'user', 'content' => $question]]
        );
        $result = (new OllamaClient($settings))->chat($messages);
        $result['sentMessages'] = count($context);

        return $result;
    }

    private function systemPrompt(string $data): string
    {
        return t('Du bist ein Assistent für die Verwaltung einer Website. Du beantwortest Fragen zu der Concrete-CMS-Installation, deren Daten unten stehen. Die Daten wurden eben aus ihrer Datenbank gelesen und sind deine einzige Quelle. Steht die Antwort nicht in den Daten, sag das ehrlich, statt zu raten. Listen mit dem Hinweis „gekürzt“ enthalten nicht alle Einträge. Der Super-Administrator hat immer alle Rechte, auch ohne Gruppe. Antworte immer auf Deutsch und konkret: Nenne die passenden Namen, Pfade, Daten und Zahlen aus den Daten.')
            . "\n\n# " . t('Daten der Installation') . "\n\n" . $data;
    }
}
