<?php

/**
 * The picture a storage key names.
 *
 * Drawn through the design system's square avatar, which is the component it
 * has for "a small image in a row of text" -- sized by CSS rather than by
 * attributes, so a project restyling it changes one rule and not every page.
 *
 * **The source goes through `$href()` and not through `$attrs()`.** It is a
 * URL, it was built from a column value, and `$href()` is the only helper in
 * this project that checks a scheme (rule 6). A `src` written any other way
 * is the one place a data column could put `javascript:` in front of a
 * browser.
 *
 * **A cell with no resolvable file draws its name as text.** The formatter
 * answers with no `src` when there is no storage wired, when the key belongs
 * to another page, or when the value is not a key at all -- three states a
 * real installation reaches, none of which is a reason to draw a broken
 * image.
 *
 * No `loading="lazy"`: a grid of twenty five rows is one screen of pictures,
 * and deferring them trades a flash of empty boxes for bandwidth nobody in an
 * admin is counting.
 *
 * @var \RockAdmin\Grid\CellView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(mixed): string $href
 */

$source = $view->attributes['src'] ?? null;
?>
<?php if (\is_string($source) && $source !== '') { ?>
    <img class="ra-avatar ra-avatar--sm ra-avatar--sq ra-grid-cell-image" src="<?= $href($source) ?>" alt="<?= $e($view->attributes['alt'] ?? '') ?>">
<?php } else { ?>
    <span class="ra-grid-cell-image ra-grid-cell-image-missing"><?= $e($view->text) ?></span>
<?php } ?>
