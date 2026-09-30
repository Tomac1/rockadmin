<?php

declare(strict_types=1);

/**
 * What each role may do — the configuration half of specification 9.2.
 *
 * Assignment is data: `ra_users.roles` names these keys, and `demo/seed.php`
 * fills it. Nothing here mentions a user, and nothing in the database decides
 * what a role means.
 *
 * Permission names are generated from the pages, so this file can only name
 * permissions the pages in `demo/config/pages` actually produce. `ads` has a
 * form region, so it generates all four; a page with no form region would
 * generate only its `view`, and naming a write permission for it is refused at
 * load rather than silently granted.
 *
 * The three roles exist to make the difference visible in a browser: the
 * editor may change an ad but never delete one, and the viewer sees the grid
 * with no create button, no edit links and no actions column at all.
 */

return [
    'superadmin' => [
        'label' => 'Superadmin',
        'permissions' => ['*'],
    ],
    'editor' => [
        'label' => 'Editor',

        // `ads.*` widens and `!ads.delete` subtracts, and the order does not
        // matter: every subtraction is applied after every widening.
        'permissions' => ['ads.*', '!ads.delete'],
    ],
    'viewer' => [
        'label' => 'Viewer',
        'permissions' => ['*.view'],
    ],
];
