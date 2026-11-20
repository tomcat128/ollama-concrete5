<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var int $bID
 * @var string $title
 * @var string $model
 * @var bool $canUse
 * @var bool $isRegistered
 * @var string $askUrl
 * @var string $token
 */

// Guests do not see the block at all; it would only advertise an internal tool.
if (!$canUse && !$isRegistered) {
    return;
}
?>
<div class="ollama-chat"
     data-ask-url="<?= h($askUrl) ?>"
     data-token="<?= h($token) ?>"
     data-bid="<?= (int) $bID ?>">
    <?php if ((string) $title !== '') { ?>
        <h3 class="ollama-chat__title"><?= h($title) ?></h3>
    <?php } ?>

    <?php if (!$canUse) { ?>
        <p class="ollama-chat__notice"><?= t('Nur Benutzer mit Zugang zum Dashboard können hier Fragen zur Installation stellen.') ?></p>
    <?php } else { ?>
        <div class="ollama-chat__messages" aria-live="polite">
            <p class="ollama-chat__empty">
                <?= t('Stelle eine Frage zu dieser Concrete-Installation, etwa: „Welche Pakete sind installiert?“ oder „Welche Seiten wurden zuletzt geändert?“') ?>
            </p>
        </div>
        <form class="ollama-chat__form">
            <label class="ollama-chat__label" for="ollama-chat-input-<?= (int) $bID ?>"><?= t('Deine Frage') ?></label>
            <div class="ollama-chat__row">
                <textarea id="ollama-chat-input-<?= (int) $bID ?>" class="ollama-chat__input" rows="2"
                          placeholder="<?= h(t('Frage eingeben …')) ?>"></textarea>
                <button type="submit" class="ollama-chat__send btn btn-primary"><?= t('Fragen') ?></button>
            </div>
            <div class="ollama-chat__footer">
                <span><?= t('Modell: %s. Das Gespräch wird nicht gespeichert; beim Verlassen oder Neuladen der Seite ist es weg.', h($model)) ?></span>
                <button type="button" class="ollama-chat__reset btn btn-link btn-sm"><?= t('Neues Gespräch') ?></button>
            </div>
        </form>
    <?php } ?>
</div>
