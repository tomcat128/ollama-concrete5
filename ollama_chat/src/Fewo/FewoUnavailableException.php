<?php

namespace OllamaChat\Fewo;

defined('C5_EXECUTE') or die('Access Denied.');

/** The Fewo Toolkit or its occupancy plan is not set up, so the assistant cannot answer. */
final class FewoUnavailableException extends \RuntimeException
{
}
