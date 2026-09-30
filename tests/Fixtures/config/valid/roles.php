<?php

declare(strict_types=1);

return [
    'superadmin' => ['permissions' => ['*']],
    'editor' => [
        'label' => 'Editor',
        // The placeholder proves roles go through the resolver the way
        // enumerations do; nothing else in this file needs one.
        'permissions' => ['{{env.EDITOR_SCOPE}}', '!ads.delete'],
    ],
];
