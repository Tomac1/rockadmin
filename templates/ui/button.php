<?php

/**
 * A button or a link, depending on whether it navigates.
 *
 * @var \RockAdmin\View\ButtonView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(mixed): string $href
 * @var \Closure(array<int|string, scalar|null>): string $attrs
 * @var \Closure(string, mixed=): string $partial
 */
?>
<a class="<?= $e($view->classes()) ?>" href="<?= $href($view->url) ?>"<?= $attrs($view->attributes) ?>>
    <?php if ($view->icon !== null) { ?>
        <?= $partial('ui/icon', $view->icon) ?>
    <?php } ?>
    <span class="ra-btn-label"><?= $e($view->label) ?></span>
</a>
