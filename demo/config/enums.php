<?php

declare(strict_types=1);

/**
 * The shared enumerations the demo's pages reference as `@enum:key`.
 *
 * `ad_state` is defined once here and read three times on `/p/ads`: the grid
 * column draws it as a badge, the toolbar's select filters by it, and the
 * form's select offers exactly the same choices. That is the whole point of
 * declaring an enumeration rather than writing the options out in each of the
 * three places — a state added here appears in all three, and a form can
 * never offer a value the grid cannot display.
 */
return [
    'ad_state' => [
        'active' => ['label' => 'Active', 'color' => 'success'],
        'draft' => ['label' => 'Draft', 'color' => 'secondary'],
        'sold' => ['label' => 'Sold', 'color' => 'primary'],
    ],
];
