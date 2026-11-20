<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var int $bID
 * @var string $title
 * @var bool $configured
 * @var bool $isRegistered
 * @var string $askUrl
 * @var string $contactUrl
 */

if (!$configured) {
    // Visitors see nothing; signed-in editors learn why.
    if ($isRegistered) {
        echo '<p class="ollama-chat__notice">' . t('Ferienwohnung: Verfügbarkeit & Preis – kein Belegungsplan und keine Preisliste des Fewo Toolkits gefunden.') . '</p>';
    }

    return;
}
?>
<div class="ollama-chat ollama-chat--fewo"
     data-ask-url="<?= h($askUrl) ?>"
     data-bid="<?= (int) $bID ?>">
    <?php if ((string) $title !== '') { ?>
        <h3 class="ollama-chat__title"><?= h($title) ?></h3>
    <?php } ?>
    <div class="ollama-chat__messages" aria-live="polite">
        <p class="ollama-chat__empty">
            <?= t('Fragen Sie nach freien Terminen und Preisen, zum Beispiel: „Ist vom 3. bis 10. August für zwei Personen frei, und was kostet das?“') ?>
        </p>
    </div>
    <form class="ollama-chat__form">
        <label class="ollama-chat__label" for="ollama-chat-input-<?= (int) $bID ?>"><?= t('Ihre Frage') ?></label>
        <div class="ollama-chat__row">
            <textarea id="ollama-chat-input-<?= (int) $bID ?>" class="ollama-chat__input" rows="2"
                      placeholder="<?= h(t('Frage eingeben …')) ?>"></textarea>
            <button type="submit" class="ollama-chat__send btn btn-primary"><?= t('Fragen') ?></button>
        </div>
        <div class="ollama-chat__footer">
            <span>
                <?= t('Automatische Auskunft, alle Angaben ohne Gewähr. Das Gespräch wird nicht gespeichert.') ?>
                <?php if ($contactUrl !== '') { ?>
                    <a href="<?= h($contactUrl) ?>"><?= t('Jetzt anfragen') ?></a>
                <?php } ?>
            </span>
            <button type="button" class="ollama-chat__reset btn btn-link btn-sm"><?= t('Neues Gespräch') ?></button>
        </div>
    </form>
</div>
