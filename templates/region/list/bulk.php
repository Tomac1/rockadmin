<?php

/**
 * The selection form and the bar of controls that act on it.
 *
 * **The form wraps nothing.** It is an empty `<form>` beside the table, and
 * every row's checkbox points at it with `form="..."`. That is not a trick —
 * it is what the attribute is for — and it is the only arrangement that
 * works. A selection form wrapping the table would contain the `<form>` a
 * `post` action draws inside its `<td>`, and nested forms are not legal HTML:
 * the browser drops the inner one without a word, so with JavaScript off
 * every row action would become a button that does nothing and no error
 * anywhere would say why.
 *
 * **One form, several actions.** Each button carries `formaction`, which is
 * how HTML says "this submit goes somewhere else". So the token is written
 * once, the selection is read once, and which action runs is the button that
 * was pressed — rather than a hidden input that a script would have to keep
 * in step with the control.
 *
 * **The bar is always drawn, not revealed.** `core.js` disables the buttons
 * while nothing is selected and keeps the count honest; with no script at
 * all the buttons stay enabled and an empty selection is refused by the
 * server, which says so. A bar that only existed once a script had run would
 * be a feature that silently is not there.
 *
 * @var \RockAdmin\Grid\ListView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(mixed): string $href
 * @var \Closure(string, mixed=): string $partial
 */
?>
<form
    class="ra-grid-bulk"
    id="<?= $e($view->bulkFormId()) ?>"
    method="post"
    action="<?= $href($view->bulkActions[0]->url) ?>"
    data-ra-behavior="grid-bulk"
>
    <input type="hidden" name="<?= $e(\RockAdmin\Http\Csrf::FIELD) ?>" value="<?= $e($view->bulkToken) ?>">
    <input type="hidden" name="<?= $e(\RockAdmin\Form\FormFields::RETURN_TO) ?>" value="<?= $e($view->pageUrl) ?>">
    <?php
    /*
     * The marker that says this submission is a bulk one. An unchecked
     * checkbox sends nothing, so a form submitted with nothing ticked carries
     * no `_ids` at all and is indistinguishable in the body from a single-row
     * action — which would answer 404 "this request names no row" when the
     * true answer is "nothing was selected".
     */
    ?>
    <input type="hidden" name="<?= $e(\RockAdmin\Form\FormFields::BULK) ?>" value="1">

    <p class="ra-grid-bulk-count" data-ra-bulk-count>
        <span class="ra-grid-bulk-count-number">0</span> selected
    </p>

    <div class="ra-grid-bulk-actions">
        <?php foreach ($view->bulkActions as $action) { ?>
            <button
                class="ra-btn ra-btn--outline ra-btn--sm ra-grid-bulk-action <?= $e($action->classes()) ?>"
                type="submit"
                formaction="<?= $href($action->url) ?>"
                data-ra-confirm="<?= $e($action->confirm) ?>"
            >
                <?php if ($action->icon !== null) { ?>
                    <?= $partial('ui/icon', $action->icon) ?>
                <?php } ?>
                <span class="ra-action-label"><?= $e($action->label) ?></span>
            </button>
        <?php } ?>
    </div>
</form>
