<?php

namespace OllamaChat;

defined('C5_EXECUTE') or die('Access Denied.');

/** Checks the question and the history that the browser sends with every chat request. */
final class ChatRequest
{
    public const MAX_QUESTION_LENGTH = 4000;

    private const MAX_HISTORY_MESSAGES = 200;

    private const MAX_MESSAGE_LENGTH = 20000;

    public static function isValidQuestion(string $question): bool
    {
        return $question !== '' && mb_strlen($question) <= self::MAX_QUESTION_LENGTH;
    }

    /**
     * The history as sent by the browser, or null if it is malformed. Only questions and answers are accepted,
     * so the browser cannot smuggle in its own system prompt.
     *
     * @param mixed $history
     *
     * @return array<int, array{role: string, content: string}>|null
     */
    public static function history($history): ?array
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
}
