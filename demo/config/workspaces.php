<?php

declare(strict_types=1);

/**
 * Workspaces (specification 9.3): a named context — a bundle of variables, a
 * set of visible pages, and a unit of permission.
 *
 * The demo runs one database with two sites in it, which is the arrangement
 * most of the reason for this file: `ra_test_ads.tenant_id` says which site
 * an ad belongs to, and `demo/config/pages/ads.php` scopes the page to
 * `{{workspace.tenant_id}}`. That scope reaches the WHERE of every query and
 * every write — it is not a default filter, so no URL can remove it and no
 * posted `_id` can reach past it.
 *
 * **`vars` are values, not SQL.** `{{workspace.tenant_id}}` becomes a bound
 * parameter: `WHERE tenant_id = ?`. That is what makes it safe to put
 * configuration into a query at all.
 *
 * **`pages` is not optional**, and a page no workspace lists is a page nobody
 * sees. The other reading — unlisted means visible everywhere — hides a
 * mistake instead of a page, and in a multi-tenant admin the mistake it hides
 * is one tenant's page appearing in another's. `'@all'` says "every page" on
 * purpose, and is what both of these use because the demo's four pages belong
 * to both sites.
 *
 * Deleting this file is the single-tenant case: no scope, no switcher, no
 * extra check, every page visible. It costs nothing to not have.
 */
return [
    'cyklobazar' => [
        'label' => 'Cyklobazar.cz',
        'vars' => ['tenant_id' => 1],
        'pages' => '@all',
    ],
    'motobazar' => [
        'label' => 'Motobazar.cz',
        'vars' => ['tenant_id' => 2],
        'pages' => '@all',
    ],
];
