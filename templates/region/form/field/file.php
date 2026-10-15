<?php

/**
 * A file: what is there now, a way to replace it, and a way to take it off.
 *
 * **An empty file input means "keep what is stored", never "clear it".** That
 * is what a browser sends for a control nobody touched, and an edit form is
 * opened far more often to fix a typo than to remove a photograph. Clearing
 * therefore needs a control of its own, which is the checkbox below -- the
 * same shape the password field uses for the same reason: a control that
 * cannot show what it holds cannot ask to keep it either.
 *
 * **The stored file is shown rather than described.** An image is drawn small,
 * anything else is a link carrying its name; both go to a URL the region
 * resolved, which is signed or public depending on the storage and is never
 * the raw key.
 *
 * `readonly` renders as `disabled`, for the reason `select.php` gives: a
 * readonly file input is still clickable in every browser.
 *
 * @var \RockAdmin\Form\FormFieldView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(mixed): string $href
 * @var \Closure(array<int|string, scalar|null>): string $attrs
 */

$attributes = \RockAdmin\Form\FormControl::attributes($view, disableWhenReadonly: true);
$removeId = 'ra-form-remove-' . $view->key;
?>
<?php if ($view->file !== null) { ?>
    <div class="ra-form-control-file-current ra-file-current">
        <?php if ($view->file->isImage) { ?>
            <a class="ra-file-current-link" href="<?= $href($view->file->url) ?>" target="_blank" rel="noopener">
                <img class="ra-avatar ra-avatar--2xl ra-avatar--sq ra-file-current-image" src="<?= $href($view->file->url) ?>" alt="<?= $e($view->file->name) ?>">
            </a>
        <?php } else { ?>
            <a class="ra-link ra-file-current-link" href="<?= $href($view->file->url) ?>" target="_blank" rel="noopener"><?= $e($view->file->name) ?></a>
        <?php } ?>

        <?php if (!$view->required && !$view->readonly && $view->removeField !== '') { ?>
            <label class="ra-form-control ra-form-control-checkbox ra-checkbox-label ra-file-remove">
                <input class="ra-form-control-checkbox-input ra-checkbox-input ra-file-remove-input" type="checkbox" id="<?= $e($removeId) ?>" name="<?= $e($view->removeField) ?>" value="1">
                <span class="ra-checkbox-box" aria-hidden="true">
                    <svg class="ra-checkbox-tick" width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 6.5l2.5 2.5 4.5-5"/></svg>
                </span>
                <span class="ra-checkbox-text ra-file-remove-text">Remove this file</span>
            </label>
        <?php } ?>
    </div>
<?php } ?>

<input class="<?= $e(\RockAdmin\Form\FormControl::classes($view, 'ra-input')) ?> ra-file-input" type="file"<?= $attrs($attributes) ?>>
