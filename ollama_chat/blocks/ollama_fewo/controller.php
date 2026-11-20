<?php

namespace Concrete\Package\OllamaChat\Block\OllamaFewo;

use Concrete\Core\Block\BlockController;
use Concrete\Core\Page\Page;
use Concrete\Core\Support\Facade\Application;
use Concrete\Core\Support\Facade\Url;
use OllamaChat\AccessChecker;
use OllamaChat\BlockSettings;
use OllamaChat\Fewo\FewoBlockSettings;
use OllamaChat\Fewo\FewoSources;

defined('C5_EXECUTE') or die('Access Denied.');

class Controller extends BlockController
{
    protected $btTable = 'btOllamaFewo';

    protected $btInterfaceWidth = 560;

    protected $btInterfaceHeight = 560;

    protected $btCacheBlockOutput = true;

    protected $btCacheBlockOutputForRegisteredUsers = false;

    public function getBlockTypeName()
    {
        return t('Ferienwohnung: Verfügbarkeit & Preis');
    }

    public function getBlockTypeDescription()
    {
        return t('Gäste fragen ein lokales Sprachmodell (Ollama) nach freien Terminen und Preisen; Grundlage sind Belegungsplan und Preisliste des Fewo Toolkits.');
    }

    public function add()
    {
        $this->set('title', '');
        $this->set('contactPageID', 0);
        $this->set('ollamaUrl', BlockSettings::defaultOllamaUrl());
        $this->set('model', BlockSettings::defaultModel());
        $this->set('contextSize', BlockSettings::DEFAULT_CONTEXT_SIZE);
        $this->set('maxDataAgeHours', FewoBlockSettings::DEFAULT_MAX_DATA_AGE_HOURS);
        $this->set('sources', FewoSources::detect());
    }

    public function edit()
    {
        $this->set('sources', FewoSources::detect());
    }

    public function registerViewAssets($outputContent = '')
    {
        $this->requireAsset('ollama_chat/chat');
    }

    public function view()
    {
        $sources = FewoSources::detect();
        $this->set('configured', $sources->hasCalendar() || $sources->hasPrices());
        $this->set('isRegistered', AccessChecker::isRegistered());
        $this->set('askUrl', (string) Url::to('/ccm/ollama_chat/fewo'));
        $contact = (int) $this->contactPageID > 0 ? Page::getByID((int) $this->contactPageID) : null;
        $this->set('contactUrl', $contact && !$contact->isError() ? (string) Url::to($contact) : '');
    }

    public function validate($args)
    {
        $errors = Application::getFacadeApplication()->make('helper/validation/error');
        if (!preg_match('#^https?://[^\s/]+#i', trim((string) ($args['ollamaUrl'] ?? '')))) {
            $errors->add(t('Bitte die URL des Ollama-Servers angeben, z. B. %s.', BlockSettings::DEFAULT_OLLAMA_URL));
        }
        if (trim((string) ($args['model'] ?? '')) === '') {
            $errors->add(t('Bitte ein Modell angeben, z. B. %s.', BlockSettings::DEFAULT_MODEL));
        }
        $contextSize = (int) ($args['contextSize'] ?? 0);
        if ($contextSize < BlockSettings::MIN_CONTEXT_SIZE || $contextSize > BlockSettings::MAX_CONTEXT_SIZE) {
            $errors->add(t('Das Kontextfenster muss zwischen %s und %s Tokens liegen.', BlockSettings::MIN_CONTEXT_SIZE, BlockSettings::MAX_CONTEXT_SIZE));
        }
        $maxAge = (int) ($args['maxDataAgeHours'] ?? 0);
        if ($maxAge < 1 || $maxAge > FewoBlockSettings::MAX_DATA_AGE_HOURS_LIMIT) {
            $errors->add(t('Das Höchstalter des Belegungsplans muss zwischen 1 und %s Stunden liegen.', FewoBlockSettings::MAX_DATA_AGE_HOURS_LIMIT));
        }

        return $errors;
    }

    public function save($args)
    {
        parent::save([
            'title' => trim((string) ($args['title'] ?? '')),
            'contactPageID' => (int) ($args['contactPageID'] ?? 0),
            'ollamaUrl' => rtrim(trim((string) $args['ollamaUrl']), '/'),
            'model' => trim((string) $args['model']),
            'contextSize' => (int) $args['contextSize'],
            'maxDataAgeHours' => (int) $args['maxDataAgeHours'],
        ]);
    }
}
