<?php

/**
 * A named icon. No icon font or sprite ships in this milestone; the element
 * carries the name in a data attribute so a project's stylesheet — or a
 * later milestone — can give it a glyph. It is decoration only, never the
 * sole label for an action.
 *
 * @var string $view
 * @var \Closure(mixed): string $e
 */
?>
<span class="ra-icon" data-ra-icon="<?= $e($view) ?>" aria-hidden="true"></span>
