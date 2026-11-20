<?php

namespace OllamaChat\Fewo;

use Concrete\Core\Database\Connection\Connection;
use Concrete\Core\Support\Facade\Application;
use OllamaChat\BlockSettings;

defined('C5_EXECUTE') or die('Access Denied.');

/** Settings of one "Ferienwohnung: Verfügbarkeit & Preis" block. */
final class FewoBlockSettings
{
    public const DEFAULT_MAX_DATA_AGE_HOURS = 24;

    public const MAX_DATA_AGE_HOURS_LIMIT = 720;

    /** @var BlockSettings connection to Ollama */
    public $ollama;

    /** @var int after this many hours without a successful update, the occupancy is treated as unknown */
    public $maxDataAgeHours;

    public function __construct(BlockSettings $ollama, int $maxDataAgeHours)
    {
        $this->ollama = $ollama;
        $this->maxDataAgeHours = $maxDataAgeHours;
    }

    public static function forBlock(int $bID): ?self
    {
        if ($bID <= 0) {
            return null;
        }
        $db = Application::getFacadeApplication()->make(Connection::class);
        $row = $db->fetchAssociative('SELECT ollamaUrl, model, contextSize, maxDataAgeHours FROM btOllamaFewo WHERE bID = ?', [$bID]);
        if (!$row) {
            return null;
        }

        return new self(
            new BlockSettings((string) $row['ollamaUrl'], (string) $row['model'], (int) $row['contextSize']),
            (int) $row['maxDataAgeHours']
        );
    }
}
