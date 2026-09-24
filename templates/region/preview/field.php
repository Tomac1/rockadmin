<?php

/**
 * One label-and-value pair, or a wide row when `$view->wide` is true — a
 * `json` cell, a long `text` cell, spanning the full width instead of
 * squeezing into a definition-list column.
 *
 * The value itself is drawn by the very same cell partial a grid cell of
 * the same display would use — `CellPartial::templateFor()`, the mapping
 * `templates/region/list/row.php` also calls, so a value reads identically
 * wherever it is shown; see
 * `PreviewRegionTest::testThePreviewAndTheGridFormatTheSameValueIdentically()`.
 * A field whose cell carries a `$url` is wrapped in `region/list/cell/link.php`
 * instead, exactly as a grid cell would be.
 *
 * A field whose value is genuinely absent — `$view->cell->value` is null,
 * which `CellFormatter` only ever produces for an actually-missing value —
 * uses `region/preview/missing` instead, so an empty field reads as "not
 * set" rather than as a blank line. Checked on `$value`, never on `$text`:
 * a `Display::Check` box that is honestly `false` and a text column whose
 * real value is `''` both format to empty text by `CellFormatter`'s own
 * design, and both are still values, not absences -- the grid shows them
 * as such, and a preview reusing its formatter has to as well.
 *
 * @var \RockAdmin\Grid\FieldView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(string, mixed=): string $partial
 */
?>
<div class="<?= $e($view->classes()) ?>">
    <dt class="ra-field-label"><?= $e($view->label) ?></dt>
    <dd class="ra-field-value">
        <?php if ($view->cell->value === null) { ?>
            <?= $partial('region/preview/missing', $view) ?>
        <?php } else { ?>
            <?= $partial(
                $view->cell->url !== null ? 'region/list/cell/link' : \RockAdmin\Grid\CellPartial::templateFor($view->cell),
                $view->cell,
            ) ?>
        <?php } ?>
    </dd>
</div>
