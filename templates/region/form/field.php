<?php

/**
 * One field: its label, its control, its help text and its error.
 *
 * Which control is drawn is `FormControl::templateFor()`'s decision, not
 * this template's — the filename under `field/` is the type's own name, so a
 * project restyling every date input copies `region/form/field/date.php` and
 * nothing else. That is the rule `CellPartial` already applies to the grid's
 * cells, on the other half of the admin.
 *
 * Every control is labelled, and the label's `for` is
 * `FormControl::id($view)` — the same call the control itself writes as its
 * `id`, so the two cannot drift. Milestone 6 shipped a range filter whose
 * label pointed at nothing, which is what a second hand-written spelling
 * looks like once one of them changes.
 *
 * A `radio` field is the exception, and not an arbitrary one: it has no
 * single control for a `for` to point at, because each option is its own
 * input with its own label. Its group is labelled the other way round, by
 * `aria-labelledby` from the group to this label — so this label carries an
 * id and no `for`, rather than a `for` naming something that is not there.
 *
 * A `hidden` field has no label, no wrapper and no help: there is nothing to
 * label, and a wrapper holding only an invisible input draws an empty row
 * with a border.
 *
 * The error is a `ra-form-error`, deliberately not Bootstrap's own
 * `.invalid-feedback`, which is `display: none` until a sibling carries
 * `.is-invalid`. Milestone 4 shipped a toast nobody could see because its
 * test asserted the classes and the text — both true of markup Bootstrap was
 * hiding. The message a person needs in order to fix their form is not a
 * thing to hang on that.
 *
 * @var \RockAdmin\Form\FormFieldView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(array<int|string, scalar|null>): string $attrs
 * @var \Closure(string, mixed=): string $partial
 */

$labelAttributes = $view->type === \RockAdmin\Page\FieldType::Radio
    ? ['id' => \RockAdmin\Form\FormControl::labelId($view)]
    : ['for' => \RockAdmin\Form\FormControl::id($view)];
?>
<?php if ($view->type === \RockAdmin\Page\FieldType::Hidden) { ?>
    <?= $partial(\RockAdmin\Form\FormControl::templateFor($view), $view) ?>
<?php } else { ?>
    <div class="<?= $e($view->classes()) ?>" data-ra-field="<?= $e($view->key) ?>">
        <label class="ra-form-label form-label"<?= $attrs($labelAttributes) ?>>
            <?= $e($view->label) ?><?php if ($view->required) { ?><span class="ra-form-required" aria-hidden="true">*</span><?php } ?>
        </label>

        <?= $partial(\RockAdmin\Form\FormControl::templateFor($view), $view) ?>

        <?php if ($view->hasError()) { ?>
            <p class="ra-form-error" id="<?= $e(\RockAdmin\Form\FormControl::errorId($view)) ?>"><?= $e($view->error()) ?></p>
        <?php } ?>

        <?php if ($view->help !== '') { ?>
            <p class="ra-form-help form-text" id="<?= $e(\RockAdmin\Form\FormControl::helpId($view)) ?>"><?= $e($view->help) ?></p>
        <?php } ?>
    </div>
<?php } ?>
