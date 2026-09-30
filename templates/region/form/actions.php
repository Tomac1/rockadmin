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
 * @var \RockAdmin\Form\FormView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(mixed): string $href
 */
?>
<div class="ra-form-actions">
    <button class="ra-form-submit btn btn-primary" type="submit"><?= $e($view->isCreate ? 'Create' : 'Save changes') ?></button>
    <a class="ra-form-cancel btn btn-outline-secondary" href="<?= $href($view->returnTo) ?>">Cancel</a>
</div>
