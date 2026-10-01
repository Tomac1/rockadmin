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
    <?php
    /*
     * `data-ra-refresh` names the regions that re-render once this completes,
     * and its absence is what every action did before: a redirect, and the
     * whole page comes back. With no script that is what happens either way —
     * the attribute is a signal, never a requirement.
     */
    ?>
    <form
        class="ra-action-form ra-action-form-<?= $e($view->key) ?>"
        method="post"
        action="<?= $href($view->url) ?>"
        <?php if ($view->refresh !== []) { ?>data-ra-refresh="<?= $e(implode(' ', $view->refresh)) ?>"<?php } ?>
    >
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
    <?php
    /*
     * A link, including when it opens an overlay.
     *
     * An `open` is an anchor that script upgrades, never a button that script
     * replaces. 8.6's own example is a button, and a button does nothing at
     * all with JavaScript off — so `href` is the standalone page showing the
     * same thing, and `data-ra-open` is the fragment `core.js` fetches
     * instead when it is there to intercept the click.
     *
     * Linking straight at the fragment is what 8.6's "a fragment is always
     * valid HTML, so it can be opened directly in a browser" suggests. That
     * sentence is about debugging. Somebody with no script who clicks Preview
     * should not land on an unstyled fragment with no navigation.
     */
    ?>
    <a
        class="ra-btn ra-btn--outline ra-btn--sm <?= $e($view->classes()) ?>"
        href="<?= $href($view->url) ?>"
        <?php if ($view->isOpen()) { ?>
            data-ra-action="open"
            data-ra-open="<?= $href($view->fragmentUrl) ?>"
            data-ra-in="<?= $e($view->in) ?>"
            <?php if ($view->size !== '') { ?>data-ra-size="<?= $e($view->size) ?>"<?php } ?>
            <?php if ($view->title !== '') { ?>data-ra-title="<?= $e($view->title) ?>"<?php } ?>
        <?php } ?>
    >
        <?php if ($view->icon !== null) { ?>
            <?= $partial('ui/icon', $view->icon) ?>
        <?php } ?>
        <span class="ra-action-label"><?= $e($view->label) ?></span>
    </a>
<?php } ?>
