<?php

namespace Concrete\Package\OllamaChat;

use Concrete\Core\Asset\Asset;
use Concrete\Core\Asset\AssetList;
use Concrete\Core\Block\BlockType\BlockType;
use Concrete\Core\Package\Package;
use Concrete\Core\Routing\RouterInterface;
use OllamaChat\Controller\AskController;
use OllamaChat\Controller\FewoAskController;

defined('C5_EXECUTE') or die('Access Denied.');

class Controller extends Package
{
    protected $pkgHandle = 'ollama_chat';

    protected $pkgVersion = '1.1.0';

    protected $appVersionRequired = '9.5.0';

    // Classes in src/ use the namespace OllamaChat\
    protected $pkgAutoloaderRegistries = ['src' => 'OllamaChat'];

    /** Block types of this package; installed on install and on upgrade if missing. */
    private const BLOCK_TYPES = ['ollama_chat', 'ollama_fewo'];

    public function getPackageName()
    {
        return t('Ollama Chat');
    }

    public function getPackageDescription()
    {
        return t('Fragen an ein lokales Sprachmodell (Ollama): zur Concrete-Installation und, mit dem Fewo Toolkit, zu Verfügbarkeit und Preisen der Ferienwohnung.');
    }

    public function install()
    {
        $pkg = parent::install();
        $this->installBlockTypes($pkg);

        return $pkg;
    }

    public function upgrade()
    {
        parent::upgrade();
        $this->installBlockTypes($this->getPackageEntity());
    }

    public function on_start()
    {
        // JSON endpoints the chat blocks send their questions to.
        $router = $this->app->make(RouterInterface::class);
        $router->post('/ccm/ollama_chat/ask', AskController::class . '::ask');
        $router->post('/ccm/ollama_chat/fewo', FewoAskController::class . '::ask');

        // Script and styles shared by both chat blocks.
        $assets = AssetList::getInstance();
        $assets->register('javascript', 'ollama_chat/chat', 'js/ollama-chat.js', [
            'position' => Asset::ASSET_POSITION_FOOTER,
            'version' => $this->pkgVersion,
        ], $this);
        $assets->register('css', 'ollama_chat/chat', 'css/ollama-chat.css', ['version' => $this->pkgVersion], $this);
        $assets->registerGroup('ollama_chat/chat', [
            ['javascript', 'ollama_chat/chat'],
            ['css', 'ollama_chat/chat'],
        ]);
    }

    private function installBlockTypes($pkg)
    {
        foreach (self::BLOCK_TYPES as $handle) {
            if (!BlockType::getByHandle($handle)) {
                BlockType::installBlockType($handle, $pkg);
            }
        }
    }
}
