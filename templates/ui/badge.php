<?php

/**
 * A short status label, coloured the way an enum or a column display maps
 * it — the same 'color' a definition in enums.php carries.
 *
 * @var array{label: string, color?: string} $view
 * @var \Closure(mixed): string $e
 */

$color = $view['color'] ?? 'secondary';
?>
<span class="<?= $e(\RockAdmin\View\Classes::of('badge', null, ['badge', 'text-bg-' . $color])) ?>"><?= $e($view['label']) ?></span>
