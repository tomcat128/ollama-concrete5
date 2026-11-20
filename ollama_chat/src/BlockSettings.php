<?php

namespace OllamaChat;

use Concrete\Core\Database\Connection\Connection;
use Concrete\Core\Support\Facade\Application;

defined('C5_EXECUTE') or die('Access Denied.');

/** Settings of one chat block, as entered in its edit form. */
final class BlockSettings
{
    public const DEFAULT_OLLAMA_URL = 'http://localhost:11434';

    public const DEFAULT_MODEL = 'llama3:latest';

    public const DEFAULT_CONTEXT_SIZE = 8192;

    public const MIN_CONTEXT_SIZE = 2048;

    public const MAX_CONTEXT_SIZE = 131072;

    /**
     * Ollama URL suggested for new blocks: the environment variable OLLAMA_CHAT_URL if set (e.g. in Docker,
     * where Ollama runs in its own container), otherwise localhost.
     */
    public static function defaultOllamaUrl(): string
    {
        $url = getenv('OLLAMA_CHAT_URL');

        return is_string($url) && trim($url) !== '' ? rtrim(trim($url), '/') : self::DEFAULT_OLLAMA_URL;
    }

    /** Model suggested for new blocks: the environment variable OLLAMA_CHAT_MODEL if set, otherwise llama3. */
    public static function defaultModel(): string
    {
        $model = getenv('OLLAMA_CHAT_MODEL');

        return is_string($model) && trim($model) !== '' ? trim($model) : self::DEFAULT_MODEL;
    }

    /** @var string */
    public $ollamaUrl;

    /** @var string */
    public $model;

    /** @var int */
    public $contextSize;

    public function __construct(string $ollamaUrl, string $model, int $contextSize)
    {
        $this->ollamaUrl = $ollamaUrl;
        $this->model = $model;
        $this->contextSize = $contextSize;
    }

    /** Settings of the block with the given ID, or null if there is no such chat block. */
    public static function forBlock(int $bID): ?self
    {
        if ($bID <= 0) {
            return null;
        }
        $db = Application::getFacadeApplication()->make(Connection::class);
        $row = $db->fetchAssociative('SELECT ollamaUrl, model, contextSize FROM btOllamaChat WHERE bID = ?', [$bID]);
        if (!$row) {
            return null;
        }

        return new self((string) $row['ollamaUrl'], (string) $row['model'], (int) $row['contextSize']);
    }
}
