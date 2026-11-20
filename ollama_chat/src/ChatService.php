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

    /** Estimate: German text averages about 3 characters per token; erring on the high side keeps the prompt inside the window. */
    private const CHARS_PER_TOKEN = 3;

    /** The chat template adds a few tokens per message (role header, end marker). */
    private const TOKENS_PER_MESSAGE = 4;

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
        $dataChars = intdiv($budget, 2) * self::CHARS_PER_TOKEN;
        $system = $this->systemPrompt((new InstallationContext())->build($question, $dataChars));

        $historyBudget = $budget - self::estimateTokens($system) - self::estimateTokens($question);
        $context = self::mostRecentWithinBudget($history, $historyBudget);

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

    /**
     * The most recent messages whose estimated size fits into the budget. Only these are sent to Ollama;
     * otherwise it would silently cut off the start of the prompt.
     *
     * @param array<int, array{role: string, content: string}> $history
     *
     * @return array<int, array{role: string, content: string}>
     */
    private static function mostRecentWithinBudget(array $history, int $budget): array
    {
        $start = count($history);
        $used = 0;
        while ($start > 0 && $used + self::estimateTokens($history[$start - 1]['content']) <= $budget) {
            $used += self::estimateTokens($history[$start - 1]['content']);
            --$start;
        }
        // The context starts with a question; an answer whose question did not fit is left out as well.
        while ($start < count($history) && $history[$start]['role'] !== 'user') {
            ++$start;
        }

        return array_slice($history, $start);
    }

    private static function estimateTokens(string $text): int
    {
        return intdiv(mb_strlen($text), self::CHARS_PER_TOKEN) + self::TOKENS_PER_MESSAGE;
    }
}
