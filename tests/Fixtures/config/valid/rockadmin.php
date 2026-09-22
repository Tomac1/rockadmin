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
    'mail' => [
        'driver' => 'log',
        'host' => '{{env.MAIL_HOST}}',
        'port' => 587,
    ],
    'assets' => ['css' => ['/css/admin.css'], 'js' => []],
];
