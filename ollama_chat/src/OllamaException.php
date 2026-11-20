<?php

namespace OllamaChat;

defined('C5_EXECUTE') or die('Access Denied.');

/** Ollama could not answer. The message is meant for the user and says how to fix it. */
final class OllamaException extends \RuntimeException
{
}
