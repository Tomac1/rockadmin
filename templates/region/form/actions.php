<?php

/**
 * What the person can do with the form: save it, or go back.
 *
 * Cancel is an ordinary link to the return address the form already carries
 * as a hidden input, so leaving lands exactly where saving would have —
 * the grid page they came from, filters and page number intact. A `reset`
 * button would be a different promise: it clears the form and leaves the
 * person on it, which is almost never what "cancel" means to anyone.
 *
 * The submit button says what it does rather than "Submit": a create and an
 * edit are the same markup, and the verb is the only place the difference is
 * visible to the person filling it in.
 *
 * Delete is a submit button belonging to this same form, redirected with
 * `formaction` to `POST /a/{page}/delete`. That is what keeps it working with
 * no JavaScript while staying legal HTML: a second `<form>` cannot be nested
 * inside this one, and the token, the row's key and the return address are
 * already here as hidden inputs, so nothing needs repeating. `formnovalidate`
 * matters — without it a browser refuses to submit while a required field is
 * empty, so a half-filled row could never be deleted.
 *
 * `data-ra-confirm` is what `core.js` binds a confirmation to. The server does
 * not trust that it did: `ActionHandler` looks for no confirmation flag at
 * all, because an attribute in a document anybody can edit is not evidence
 * that anybody agreed to anything. It checks the CSRF token and the method,
 * which are the two things it can actually verify.
 *
 * @var \RockAdmin\Form\FormView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(mixed): string $href
 */
?>
<div class="ra-form-actions">
    <button class="ra-form-submit btn btn-primary" type="submit"><?= $e($view->isCreate ? 'Create' : 'Save changes') ?></button>
    <a class="ra-form-cancel btn btn-outline-secondary" href="<?= $href($view->returnTo) ?>">Cancel</a>
    <?php if ($view->deleteAction !== null) { ?>
        <button
            class="ra-form-delete btn btn-outline-danger"
            type="submit"
            formaction="<?= $href($view->deleteAction) ?>"
            formnovalidate
            data-ra-confirm="Delete this row? This cannot be undone."
        >Delete</button>
    <?php } ?>
</div>
