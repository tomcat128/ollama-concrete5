<?php

namespace OllamaChat;

use Concrete\Core\Database\Connection\Connection;
use Concrete\Core\Permission\IPService;
use Concrete\Core\Support\Facade\Application;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Limits how many questions one client may ask in a time window, for the public chat: every question keeps
 * the language model busy for seconds or minutes.
 *
 * Clients are identified by a salted hash of their IP address; the address itself is never stored, and rows
 * are deleted once their window has passed.
 */
final class RateLimiter
{
    private const MAX_REQUESTS = 20;

    private const WINDOW_SECONDS = 600;

    private const SALT_CONFIG_KEY = 'ollama_chat.rate_limit_salt';

    /** Counts the request and returns false if the client has exceeded the limit. */
    public static function allow(): bool
    {
        $app = Application::getFacadeApplication();
        $db = $app->make(Connection::class);
        $now = time();

        $db->executeStatement('DELETE FROM OllamaChatRateLimits WHERE windowStart < ?', [$now - self::WINDOW_SECONDS]);

        $client = hash('sha256', self::salt() . '|' . (string) $app->make(IPService::class)->getRequestIPAddress());
        $requests = $db->fetchOne('SELECT requests FROM OllamaChatRateLimits WHERE clientHash = ?', [$client]);
        if ($requests === false) {
            $db->insert('OllamaChatRateLimits', ['clientHash' => $client, 'windowStart' => $now, 'requests' => 1]);

            return true;
        }
        if ((int) $requests >= self::MAX_REQUESTS) {
            return false;
        }
        $db->executeStatement('UPDATE OllamaChatRateLimits SET requests = requests + 1 WHERE clientHash = ?', [$client]);

        return true;
    }

    public static function windowMinutes(): int
    {
        return intdiv(self::WINDOW_SECONDS, 60);
    }

    /** Secret that makes the hashes useless outside this installation; created on first use. */
    private static function salt(): string
    {
        $config = Application::getFacadeApplication()->make('config/database');
        $salt = (string) $config->get(self::SALT_CONFIG_KEY);
        if ($salt === '') {
            $salt = bin2hex(random_bytes(16));
            $config->save(self::SALT_CONFIG_KEY, $salt);
        }

        return $salt;
    }
}
