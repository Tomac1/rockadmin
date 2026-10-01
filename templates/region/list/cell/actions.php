<?php

/**
 * An `actions` column's cell: the controls this row offers, and nothing else.
 *
 * Which controls those are was decided before this file ran — `ActionBuilder`
 * asked the gate whether this reader may perform each one (spec 9.2: a visible
 * control that answers 403 is a bug, not a safeguard) and each action's own
 * `when` whether this row offers it, then substituted the row placeholders. So
 * an empty list here is a row with nothing to do, not a mistake, and it draws
 * an empty cell rather than disappearing: the cell exists because the column
 * does, and a body that sometimes omitted it would shift every column in that
 * row.
 *
 * `region/list/cell/actions.php` is named after the column type it draws, like
 * every other cell partial, so a project restyling every action cell in the
 * admin overrides this one file.
 *
 * @var \RockAdmin\Grid\CellView $view
 * @var \Closure(string, mixed=): string $partial
 */
?>
<div class="ra-grid-actions">
    <?php foreach ($view->actions as $action) { ?>
        <?= $partial('ui/action', $action) ?>
    <?php } ?>
</div>
