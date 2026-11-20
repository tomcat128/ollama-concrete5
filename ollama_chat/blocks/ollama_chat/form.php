<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Form\Service\Form $form
 * @var string $title
 * @var string $ollamaUrl
 * @var string $model
 * @var int $contextSize
 */
?>
<div class="mb-3">
    <?= $form->label('title', t('Überschrift (optional)')) ?>
    <?= $form->text('title', $title, ['maxlength' => 255]) ?>
</div>
<div class="mb-3">
    <?= $form->label('ollamaUrl', t('URL des Ollama-Servers')) ?>
    <?= $form->text('ollamaUrl', $ollamaUrl, ['required' => 'required', 'maxlength' => 255]) ?>
    <div class="form-text"><?= t('Vom Webserver aus gesehen, z. B. %s.', 'http://localhost:11434') ?></div>
</div>
<div class="mb-3">
    <?= $form->label('model', t('Modell')) ?>
    <?= $form->text('model', $model, ['required' => 'required', 'maxlength' => 100]) ?>
    <div class="form-text"><?= t('Muss auf dem Ollama-Server installiert sein (%s).', 'ollama pull &lt;name&gt;') ?></div>
</div>
<div class="mb-3">
    <?= $form->label('contextSize', t('Kontextfenster (Tokens)')) ?>
    <?= $form->number('contextSize', $contextSize, ['min' => 2048, 'max' => 131072, 'step' => 1024]) ?>
    <div class="form-text"><?= t('llama3 kann höchstens 8192, llama3.1 und llama3.2 bis 131072. Größere Werte brauchen mehr Arbeitsspeicher.') ?></div>
</div>
