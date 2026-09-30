<?php

/**
 * Every failure on the form, once, at the top.
 *
 * An error is shown twice on purpose: against the control, where the person
 * is looking, and here, because a long form hides a failure below the fold
 * and a page that scrolled back to the top after a refused submission looks
 * like it simply did nothing. Each entry that names a field links to that
 * field's own control, so the summary is a way to get there rather than a
 * second thing to read.
 *
 * A form-level error — a unique index the form definition could not know
 * about, or a message naming a field this form does not draw — has nothing
 * to link to and is listed as plain text. `FormView::allErrors()` decides
 * which is which; this template only draws it.
 *
 * The id it links to is `FormControl::id()`, the same call `field.php`
 * writes into the control. Spelling it here as well is how milestone 6's
 * range filter ended up with a label pointing at nothing.
 *
 * @var \RockAdmin\Form\FormView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(mixed): string $href
 */

$errors = $view->allErrors();
?>
<?php if ($errors !== []) { ?>
    <div class="ra-form-errors alert alert-danger" role="alert">
        <p class="ra-form-errors-title">This form was not saved:</p>
        <ul class="ra-form-errors-list">
            <?php foreach ($errors as $error) { ?>
                <li class="ra-form-errors-item">
                    <?php if ($error['field'] === null) { ?>
                        <?= $e($error['message']) ?>
                    <?php } else { ?>
                        <a class="ra-form-errors-link" href="<?= $href('#' . \RockAdmin\Form\FormControl::idFor($error['field'])) ?>"><?= $e($error['message']) ?></a>
                    <?php } ?>
                </li>
            <?php } ?>
        </ul>
    </div>
<?php } ?>
