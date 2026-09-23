<?php

/**
 * A list on one side and the selected record's detail on the other. Before
 * anything is selected there is no detail slot to show, so the layout
 * tolerates it being absent rather than failing.
 *
 * @var \RockAdmin\View\PageView $view
 * @var \Closure(mixed): string $raw
 */
?>
<div class="ra-layout ra-layout-sidebar-detail">
    <div class="ra-layout-list"><?= $raw($view->slot('list')) ?></div>
    <div class="ra-layout-detail"><?= $raw($view->slot('detail')) ?></div>
</div>
