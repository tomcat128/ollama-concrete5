<?php

defined('C5_EXECUTE') or die('Access Denied.');

use Concrete\Core\Support\Facade\Application;

/**
 * @var Concrete\Core\Form\Service\Form $form
 * @var string $title
 * @var int $contactPageID
 * @var string $ollamaUrl
 * @var string $model
 * @var int $contextSize
 * @var int $maxDataAgeHours
 * @var OllamaChat\Fewo\FewoSources $sources
 */
$pageSelector = Application::getFacadeApplication()->make('helper/form/page_selector');
?>
<div class="alert <?= $sources->hasCalendar() && $sources->hasPrices() ? 'alert-info' : 'alert-warning' ?>">
    <strong><?= t('Gefundene Daten des Fewo Toolkits') ?></strong><br>
    <?= t('Belegungsplan') ?>:
    <?= $sources->hasCalendar() ? h($sources->feedUrl) : t('nicht gefunden – ohne Belegungsplan kann der Assistent keine freien Termine nennen.') ?><br>
    <?= t('Preise') ?>:
    <?php if ($sources->priceType === 'EX' && $sources->hasPrices()) { ?>
        <?= t('externe Preisberechnung (%s)', h($sources->externalPriceUrl)) ?>
    <?php } elseif ($sources->hasPrices()) { ?>
        <?= t('Preisliste „%s“, berechnet nach der Saison %s', h($sources->priceListName), $sources->priceMode === 'T' ? t('des Anreisetags') : t('des Folgetags')) ?>
    <?php } else { ?>
        <?= t('keine Preisliste gefunden – ohne sie kann der Assistent keine Preise nennen.') ?>
    <?php } ?>
</div>
<div class="mb-3">
    <?= $form->label('title', t('Überschrift (optional)')) ?>
    <?= $form->text('title', $title, ['maxlength' => 255]) ?>
</div>
<div class="mb-3">
    <?= $form->label('contactPageID', t('Seite für Anfragen (optional)')) ?>
    <?= $pageSelector->selectPage('contactPageID', (int) $contactPageID > 0 ? (int) $contactPageID : null) ?>
    <div class="form-text"><?= t('Unter dem Chat erscheint ein Link „Jetzt anfragen“ zu dieser Seite, etwa zum Kontaktformular.') ?></div>
</div>
<div class="mb-3">
    <?= $form->label('maxDataAgeHours', t('Höchstalter des Belegungsplans (Stunden)')) ?>
    <?= $form->number('maxDataAgeHours', $maxDataAgeHours, ['min' => 1, 'max' => 720]) ?>
    <div class="form-text"><?= t('Konnte der Belegungsplan länger nicht aktualisiert werden, nennt der Assistent keine freien Termine, sondern bittet um eine direkte Anfrage.') ?></div>
</div>
<div class="mb-3">
    <?= $form->label('ollamaUrl', t('URL des Ollama-Servers')) ?>
    <?= $form->text('ollamaUrl', $ollamaUrl, ['required' => 'required', 'maxlength' => 255]) ?>
    <div class="form-text"><?= t('Vom Webserver aus gesehen, z. B. %s.', 'http://localhost:11434') ?></div>
</div>
<div class="mb-3">
    <?= $form->label('model', t('Modell')) ?>
    <?= $form->text('model', $model, ['required' => 'required', 'maxlength' => 100]) ?>
</div>
<div class="mb-3">
    <?= $form->label('contextSize', t('Kontextfenster (Tokens)')) ?>
    <?= $form->number('contextSize', $contextSize, ['min' => 2048, 'max' => 131072, 'step' => 1024]) ?>
</div>
