<?php

/**
 * One region filling the page. Does not know what a region is, only that
 * the page handed it a slot called 'main'.
 *
 * @var \RockAdmin\View\PageView $view
 * @var \Closure(mixed): string $raw
 */
?>
<div class="ra-layout ra-layout-single">
    <div class="ra-layout-main"><?= $raw($view->slot('main')) ?></div>
</div>
