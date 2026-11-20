<?php

namespace OllamaChat;

use Concrete\Core\Page\Page;
use Concrete\Core\Permission\Checker;
use Concrete\Core\Support\Facade\Application;
use Concrete\Core\User\User;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Decides who may use the chat. The answers reveal internals of the installation (users, packages, versions,
 * log entries), so only signed-in users who may open the dashboard are allowed.
 */
final class AccessChecker
{
    /** Action name of the CSRF token that protects the ask endpoint. */
    public const TOKEN_ACTION = 'ollama_chat_ask';

    public static function isRegistered(): bool
    {
        return Application::getFacadeApplication()->make(User::class)->isRegistered();
    }

    public static function canUse(): bool
    {
        if (!self::isRegistered()) {
            return false;
        }
        $dashboard = Page::getByPath('/dashboard');
        if (!$dashboard || $dashboard->isError()) {
            return false;
        }

        return (new Checker($dashboard))->canViewPage();
    }
}
