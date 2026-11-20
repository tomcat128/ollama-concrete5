<?php

namespace Concrete\Package\OllamaChat\Block\OllamaChat;

use Concrete\Core\Block\BlockController;
use Concrete\Core\Support\Facade\Application;
use Concrete\Core\Support\Facade\Url;
use OllamaChat\AccessChecker;
use OllamaChat\BlockSettings;

defined('C5_EXECUTE') or die('Access Denied.');

class Controller extends BlockController
{
    protected $btTable = 'btOllamaChat';

    protected $btInterfaceWidth = 520;

    protected $btInterfaceHeight = 440;

    // Every view needs a fresh CSRF token and the permission check, so the output must not be cached.
    protected $btCacheBlockOutput = false;

    protected $btCacheBlockOutputForRegisteredUsers = false;

    public function getBlockTypeName()
    {
        return t('Ollama Chat');
    }

    public function getBlockTypeDescription()
    {
        return t('Fragen zu dieser Concrete-Installation an ein lokales Sprachmodell (Ollama) stellen.');
    }

    public function add()
    {
        $this->set('title', '');
        $this->set('ollamaUrl', BlockSettings::defaultOllamaUrl());
        $this->set('model', BlockSettings::defaultModel());
        $this->set('contextSize', BlockSettings::DEFAULT_CONTEXT_SIZE);
    }

    public function registerViewAssets($outputContent = '')
    {
        $this->requireAsset('ollama_chat/chat');
    }

    public function view()
    {
        $app = Application::getFacadeApplication();
        $this->set('canUse', AccessChecker::canUse());
        $this->set('isRegistered', AccessChecker::isRegistered());
        $this->set('askUrl', (string) Url::to('/ccm/ollama_chat/ask'));
        $this->set('token', $app->make('token')->generate(AccessChecker::TOKEN_ACTION));
    }

    public function validate($args)
    {
        $app = Application::getFacadeApplication();
        $errors = $app->make('helper/validation/error');
        $url = trim((string) ($args['ollamaUrl'] ?? ''));
        if (!preg_match('#^https?://[^\s/]+#i', $url)) {
            $errors->add(t('Bitte die URL des Ollama-Servers angeben, z. B. %s.', BlockSettings::DEFAULT_OLLAMA_URL));
        }
        if (trim((string) ($args['model'] ?? '')) === '') {
            $errors->add(t('Bitte ein Modell angeben, z. B. %s.', BlockSettings::DEFAULT_MODEL));
        }
        $contextSize = (int) ($args['contextSize'] ?? 0);
        if ($contextSize < BlockSettings::MIN_CONTEXT_SIZE || $contextSize > BlockSettings::MAX_CONTEXT_SIZE) {
            $errors->add(t('Das Kontextfenster muss zwischen %s und %s Tokens liegen.', BlockSettings::MIN_CONTEXT_SIZE, BlockSettings::MAX_CONTEXT_SIZE));
        }

        return $errors;
    }

    public function save($args)
    {
        parent::save([
            'title' => trim((string) ($args['title'] ?? '')),
            'ollamaUrl' => rtrim(trim((string) $args['ollamaUrl']), '/'),
            'model' => trim((string) $args['model']),
            'contextSize' => (int) $args['contextSize'],
        ]);
    }
}
