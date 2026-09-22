<?php

declare(strict_types=1);

return [
    'url_mode' => 'path',
    'debug' => false,
    'brand' => 'RockAdmin',
    'paths' => [
        'logs' => 'storage/logs/rockadmin',
        'cache' => 'storage/cache/rockadmin',
    ],
    'mail' => ['use' => '@mail:defaults'],
    'assets' => ['css' => ['/css/admin.css', '/css/legacy.css'], 'js' => []],
];
