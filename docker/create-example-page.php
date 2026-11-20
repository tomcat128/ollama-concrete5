<?php
// Creates the page /ki-assistent with an Ollama Chat block, unless it exists already.
// The page is hidden from the navigation. Run with: concrete/bin/concrete c5:exec
use Concrete\Core\Block\BlockType\BlockType;
use Concrete\Core\Page\Page;
use Concrete\Core\Page\Template;
use Concrete\Core\Page\Type\Type;
use OllamaChat\BlockSettings;

$path = '/ki-assistent';
$existing = Page::getByPath($path);
if ($existing && !$existing->isError()) {
    echo "Beispielseite {$path} ist bereits vorhanden.\n";

    return 0;
}

$home = Page::getByID(Page::getHomePageID());
$page = $home->add(
    Type::getByHandle('page'),
    ['cName' => 'KI-Assistent', 'cHandle' => 'ki-assistent'],
    Template::getByHandle('full')
);
$page->setAttribute('exclude_nav', true);
$page->addBlock(BlockType::getByHandle('ollama_chat'), 'Main', [
    'title' => 'Fragen zur Installation',
    'ollamaUrl' => BlockSettings::defaultOllamaUrl(),
    'model' => BlockSettings::defaultModel(),
    'contextSize' => BlockSettings::DEFAULT_CONTEXT_SIZE,
]);
echo "Beispielseite {$path} angelegt (Ollama: " . BlockSettings::defaultOllamaUrl() . ', Modell: ' . BlockSettings::defaultModel() . ").\n";

// c5:exec uses the return value as exit code; without it, include() returns 1.
return 0;
