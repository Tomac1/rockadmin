<?php

/**
 * The form region: an ordinary `<form method="post">` to the action route.
 *
 * It works with JavaScript switched off, which is the whole reason it is
 * shaped this way. `core.js` may later intercept the submit and post it in
 * the background, and nothing here depends on that having happened — a
 * form that only saves once a script has bound to it is a form that silently
 * does nothing on the day the script fails to load.
 *
 * Three things travel in the body rather than in the URL, and every one of
 * them is written through a constant rather than spelled out: the CSRF
 * token as `Csrf::FIELD`, the row's key as `FormFields::ID`, and the return
 * address as `FormFields::RETURN_TO`. The action handler reads all three
 * back through the same constants. They are a contract between two pieces
 * of code that never call each other, and a contract spelled from memory on
 * each side is one rename away from a form that posts and changes nothing.
 *
 * The row's key is here at all because `POST /a/{page}/update` names the
 * page and the verb but not the row. A create has no key and writes no
 * input for one.
 *
 * `data-ra-region` carries the fragment's identity, as it does on a list and
 * a preview. There is deliberately no `data-ra-behavior`: nothing in
 * `core.js` binds to this form yet, and an attribute that names a behaviour
 * nothing binds is worse than none, since a project cannot tell "unbound in
 * this build" from "the binding failed".
 *
 * @var \RockAdmin\Form\FormView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(mixed): string $href
 * @var \Closure(string, mixed=): string $partial
 */
?>
<form class="<?= $e($view->classes()) ?>" method="post" action="<?= $href($view->action) ?>" data-ra-region="<?= $e($view->page) ?>">
    <h2 class="ra-form-title"><?= $e($view->title) ?></h2>

    <?= $partial('region/form/errors', $view) ?>

    <input type="hidden" name="<?= $e(\RockAdmin\Http\Csrf::FIELD) ?>" value="<?= $e($view->token) ?>">
    <input type="hidden" name="<?= $e(\RockAdmin\Form\FormFields::RETURN_TO) ?>" value="<?= $e($view->returnTo) ?>">
    <?php if ($view->id !== null) { ?>
        <input type="hidden" name="<?= $e(\RockAdmin\Form\FormFields::ID) ?>" value="<?= $e($view->id) ?>">
    <?php } ?>

    <div class="ra-form-fields">
        <?php foreach ($view->fields as $field) { ?>
            <?= $partial('region/form/field', $field) ?>
        <?php } ?>
    </div>

    <?= $partial('region/form/actions', $view) ?>
</form>
