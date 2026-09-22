<?php

declare(strict_types=1);

return [
    'mail' => [
        // Holds a placeholder, so expanding this reference feeds the resolver.
        'defaults' => ['driver' => 'log', 'host' => '{{env.MAIL_HOST}}', 'port' => 587],
    ],
];
