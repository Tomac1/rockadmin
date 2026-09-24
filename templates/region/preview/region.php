<?php

/**
 * The preview region: one row, as a field list. Rendered whole by
 * `DetailHandler` for `GET /p/{page}/{id}` and as a bare fragment by
 * `RegionHandler` for `GET /r/{page}/{region}?id=42` -- the same markup
 * either way, because both handlers hand this same template the same
 * `PreviewView`.
 *
 * `data-ra-region` carries the fragment's identity for whatever later reads
 * it (milestone 8's overlay); a preview has no filter, sort or page state of
 * its own to reload from a URL, unlike a list region, so there is no
 * `data-ra-region-url` to carry alongside it.
 *
 * @var \RockAdmin\Grid\PreviewView $view
 * @var \Closure(mixed): string $e
 * @var \Closure(string, mixed=): string $partial
 */
?>
<div class="<?= $e($view->classes()) ?>" data-ra-region="<?= $e($view->key) ?>">
    <h2 class="ra-preview-title"><?= $e($view->title) ?></h2>
    <?php if ($view->fields === []) { ?>
        <p class="ra-preview-empty">This preview has no fields to show.</p>
    <?php } else { ?>
        <dl class="ra-preview-fields">
            <?php foreach ($view->fields as $field) { ?>
                <?= $partial('region/preview/field', $field) ?>
            <?php } ?>
        </dl>
    <?php } ?>
</div>
