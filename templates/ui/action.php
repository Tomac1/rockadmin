<?php

/**
 * One configured action, drawn as what it actually does.
 *
 * A `link` navigates, so it is an anchor. A `post` changes data, so it is a
 * button inside its own tiny form carrying the CSRF token — which is what makes
 * it work with JavaScript switched off, where the confirmation is the browser's
 * own and the submit is an ordinary one. `core.js` binds the confirmation to
 * `data-ra-confirm` by delegation, so a cell fetched into the page an hour
 * later behaves exactly like one from the first render; the server trusts none
 * of it and re-checks the token, the method and the action's own `when`.
 *
 * `method="post"` with no `data-ra-*` on the form is deliberate: there is
 * nothing for `core.js` to intercept yet. When `refresh` arrives it is the form
 * that grows the attribute, not this comment.
 *
 * The URL is `$href($view->url)`, never assembled here: `ActionBuilder` built
 * it from the action's reference and the row, which is the only place that
 * knows either, and a template that wrote a path by hand would break the admin
 * in query-string mode (rule 6).
 *
 * @var \RockAdmin\View\ActionView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(mixed): string $href
 * @var \Closure(string, mixed=): string $partial
 */
?>
<?php if ($view->isPost) { ?>
    <form class="ra-action-form ra-action-form-<?= $e($view->key) ?>" method="post" action="<?= $href($view->url) ?>">
        <input type="hidden" name="<?= $e(\RockAdmin\Http\Csrf::FIELD) ?>" value="<?= $e($view->token) ?>">
        <?php
        /*
         * Which row. `POST /a/{page}/{verb}` names the page and the verb and
         * not the row, so the key travels in the body exactly as a form's
         * does. Without it every row-level `post` answered 404 — "this
         * request names no row to write" — and the control looked perfectly
         * correct: gated, confirmed, and carrying a token.
         *
         * Absent for a control with no row behind it: a page header button, a
         * bulk action. Those are not posting about a row.
         */
        ?>
        <?php if ($view->rowKey !== null) { ?>
            <input type="hidden" name="<?= $e(\RockAdmin\Form\FormFields::ID) ?>" value="<?= $e($view->rowKey) ?>">
        <?php } ?>
        <button
            class="ra-btn ra-btn--outline ra-btn--sm <?= $e($view->classes()) ?>"
            type="submit"
            data-ra-confirm="<?= $e($view->confirm) ?>"
        >
            <?php if ($view->icon !== null) { ?>
                <?= $partial('ui/icon', $view->icon) ?>
            <?php } ?>
            <span class="ra-action-label"><?= $e($view->label) ?></span>
        </button>
    </form>
<?php } else { ?>
    <a class="ra-btn ra-btn--outline ra-btn--sm <?= $e($view->classes()) ?>" href="<?= $href($view->url) ?>">
        <?php if ($view->icon !== null) { ?>
            <?= $partial('ui/icon', $view->icon) ?>
        <?php } ?>
        <span class="ra-action-label"><?= $e($view->label) ?></span>
    </a>
<?php } ?>
