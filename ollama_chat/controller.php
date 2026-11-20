<?php

namespace Concrete\Package\OllamaChat;

use Concrete\Core\Block\BlockType\BlockType;
use Concrete\Core\Package\Package;
use Concrete\Core\Routing\RouterInterface;
use OllamaChat\Controller\AskController;

defined('C5_EXECUTE') or die('Access Denied.');

class Controller extends Package
{
    protected $pkgHandle = 'ollama_chat';

    protected $pkgVersion = '1.0.0';

    protected $appVersionRequired = '9.5.0';

    // Classes in src/ use the namespace OllamaChat\
    protected $pkgAutoloaderRegistries = ['src' => 'OllamaChat'];

    public function getPackageName()
    {
        return t('Ollama Chat');
    }

    public function getPackageDescription()
    {
        return t('Fragen zu dieser Concrete-Installation, beantwortet von einem lokalen Sprachmodell (Ollama).');
    }

    public function install()
    {
        $pkg = parent::install();
        BlockType::installBlockType('ollama_chat', $pkg);

        return $pkg;
    }

    public function on_start()
    {
        // Endpoint the chat block sends its questions to (JSON, see AskController).
        $router = $this->app->make(RouterInterface::class);
        $router->post('/ccm/ollama_chat/ask', AskController::class . '::ask');
    }
}
