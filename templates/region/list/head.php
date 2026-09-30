<?php

/**
 * One `<th>` per column. A sortable column's header is an ordinary link
 * carrying the URL `ListRegion` already toggled — clicking it works with no
 * JavaScript at all, because it is just navigation. `aria-sort` names the
 * current sort for anyone using a screen reader; the arrow itself is drawn
 * by CSS from that same attribute, never an image or an icon font.
 *
 * Alignment is the column's own decision, carried in `$column->align` and
 * never recomputed here: it becomes Bootstrap's own `text-start`/`text-end`
 * utility class rather than an inline style, so a project overriding the
 * theme still controls it from one place.
 *
 * `data-ra-sort-column` carries the column's own key on the sort link —
 * identity metadata a project's own CSS or script can hook a specific
 * column by, the same role `data-ra-region` plays on the fragment root.
 * `core.js` itself never reads it: `data-ra-action="sort"` is what it binds.
 *
 * @var \RockAdmin\Grid\ListView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(mixed): string $href
 */
?>
<thead class="ra-grid-head">
    <tr class="ra-grid-head-row">
        <?php foreach ($view->columns as $column) { ?>
            <th
                class="<?= $e($column->classes . ' text-' . $column->align) ?>"
                scope="col"
                <?php if ($column->width !== null) { ?>style="width: <?= $e($column->width) ?>;"<?php } ?>
                <?php if ($column->sortDirection !== null) { ?>aria-sort="<?= $e($column->sortDirection) ?>"<?php } ?>
            >
                <?php if ($column->sortUrl !== null) { ?>
                    <a class="ra-grid-sort" href="<?= $href($column->sortUrl) ?>" data-ra-action="sort" data-ra-sort-column="<?= $e($column->key) ?>">
                        <span class="ra-grid-sort-label"><?= $e($column->label) ?></span>
                    </a>
                <?php } else { ?>
                    <span class="ra-grid-head-label"><?= $e($column->label) ?></span>
                <?php } ?>
            </th>
        <?php } ?>
        <?php if ($view->hasRowActions()) { ?>
            <th class="ra-grid-head-actions" scope="col">
                <span class="ra-grid-head-label visually-hidden">Actions</span>
            </th>
        <?php } ?>
    </tr>
</thead>
