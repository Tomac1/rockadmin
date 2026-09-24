<?php

declare(strict_types=1);

/**
 * The configuration the demo loads.
 *
 * Small but real: every key here is one an actual project would set, and
 * every key is declared in RootSchema — that is what \RockAdmin\Config\Loader
 * checks it against. There is nothing demo-specific about this format; a
 * project's own rockadmin.php looks exactly like this, only bigger.
 *
 * `pages_path` is left at its default, so `demo/config/pages/ads.php` is the
 * `ads` page, reachable at `/p/ads` and refreshed a region at a time from
 * `/r/ads/{region}`.
 */
return [
    'brand' => 'RockAdmin Demo',
    'url_mode' => 'path',
    'debug' => true,
    'paths' => [
        'logs' => __DIR__ . '/../storage/logs',
    ],
    'theme' => [
        'dark' => 'auto',
    ],
];
