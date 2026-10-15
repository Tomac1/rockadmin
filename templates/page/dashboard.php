<?php

/**
 * The admin's front page: its header, and a card per page the reader may open.
 *
 * The cards are the menu, which has already been filtered by the gate — so
 * somebody who may not view a page does not learn it exists from here either.
 * A dropdown's children are drawn as cards of their own rather than nested,
 * because a card is a destination and a destination has no submenu.
 *
 * It says plainly when there is nothing: an installation with an empty `menu`
 * block, or a reader with no permissions at all, is a state somebody has to
 * be able to recognise, and an empty page is not a message.
 *
 * @var \RockAdmin\View\PageView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(string): string $href
 * @var \Closure(string, mixed=): string $partial
 */

$items = [];

foreach ($view->shell->menu as $item) {
    // Not the page being looked at: a card linking to the dashboard, drawn on
    // the dashboard, is a button that does nothing.
    if (!$item->active) {
        $items[] = $item;
    }

    foreach ($item->children as $child) {
        $items[] = $child;
    }
}
?>
<?= $partial('page/header', $view) ?>
<div class="ra-dashboard">
    <?php if ($items === []) { ?>
        <p class="ra-dashboard-empty">
            There is nothing here yet. Add pages to the <code>menu</code> block of
            <code>rockadmin.php</code>, and whoever is signed in will see the ones they
            are allowed to open.
        </p>
    <?php } else { ?>
        <ul class="ra-dashboard-pages">
            <?php foreach ($items as $item) { ?>
                <li class="ra-dashboard-page">
                    <a class="ra-card ra-card--hoverable ra-dashboard-card" href="<?= $href($item->url) ?>">
                        <span class="ra-card__body ra-dashboard-card-body">
                            <span class="ra-card__title ra-dashboard-card-title"><?= $e($item->label) ?></span>
                        </span>
                    </a>
                </li>
            <?php } ?>
        </ul>
    <?php } ?>
</div>
