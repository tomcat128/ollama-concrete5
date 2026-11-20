<?php

namespace OllamaChat;

defined('C5_EXECUTE') or die('Access Denied.');

/** Estimates text sizes in tokens and fits the history into the model's context window. */
final class ContextWindow
{
    /** Estimate: German text averages about 3 characters per token; erring on the high side keeps the prompt inside the window. */
    private const CHARS_PER_TOKEN = 3;

    /** The chat template adds a few tokens per message (role header, end marker). */
    private const TOKENS_PER_MESSAGE = 4;

    public static function estimateTokens(string $text): int
    {
        return intdiv(mb_strlen($text), self::CHARS_PER_TOKEN) + self::TOKENS_PER_MESSAGE;
    }

    public static function charsForTokens(int $tokens): int
    {
        return $tokens * self::CHARS_PER_TOKEN;
    }

    /**
     * The most recent messages whose estimated size fits into the budget. Only these are sent to Ollama;
     * otherwise it would silently cut off the start of the prompt.
     *
     * @param array<int, array{role: string, content: string}> $history
     *
     * @return array<int, array{role: string, content: string}>
     */
    public static function mostRecentWithinBudget(array $history, int $budget): array
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
}
