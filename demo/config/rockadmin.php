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
    // 'path' needs a rewrite rule; RA_URL_MODE=query runs the same demo with
    // none, e.g.: RA_URL_MODE=query php -S localhost:8080 -t demo demo/index.php
    // Query mode is otherwise never exercised anywhere in this repository --
    // this is what makes it possible to look at, not only read about.
    'url_mode' => getenv('RA_URL_MODE') !== false ? getenv('RA_URL_MODE') : 'path',
    'debug' => true,
    'paths' => [
        'logs' => __DIR__ . '/../storage/logs',
    ],
    'theme' => [
        'dark' => 'auto',
    ],
];
