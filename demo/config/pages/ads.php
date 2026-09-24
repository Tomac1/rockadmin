<?php

declare(strict_types=1);

/**
 * The worked example the reference documentation points at: one grid using
 * every feature milestone 6 built for a list region.
 *
 * `id` and `title` are plain text, `title` carries a `contains` filter and is
 * searched by the toolbar's search box; `user_name` is joined from the
 * `user` relation; `price` is money; `state` is an enum shown as a badge and
 * filtered by a select; `stats` is JSON, shown truncated; `created_at` is a
 * sortable date; `tags` is a one-to-many, fetched once for the whole page
 * rather than once per row.
 *
 * Runs over the same fixture tables `tests/Support/DatabaseTestCase.php`
 * builds for the test suite, seeded richer by `demo/seed.php` — see there for
 * how to fill them.
 */
return [
    'title' => 'Ads',
    'layout' => 'single',
    'header' => [
        'description' => 'Every feature the list region built: a filtered text column, a joined '
            . 'column, money, an enum badge, JSON, a sortable date and a one-to-many of tags.',
    ],
    'entity' => [
        'table' => 'ra_test_ads',
        'key' => 'id',
        'relations' => [
            'user' => [
                'table' => 'ra_test_users',
                'on' => 'ra_test_users.id = ra_test_ads.user_id',
            ],
        ],
    ],
    'regions' => [
        'grid' => [
            'type' => 'list',
            'per_page' => 5,
            'sort' => ['created_at' => 'desc'],
            'search' => [
                'placeholder' => 'Search ads…',
            ],
            'columns' => [
                'id' => [
                    'type' => 'int',
                    'label' => 'ID',
                    'link' => true,
                ],
                'title' => [
                    'type' => 'text',
                    'searchable' => true,
                    'filter' => [
                        'type' => 'text',
                        'placeholder' => 'Title contains…',
                    ],
                ],
                'user_name' => [
                    'type' => 'text',
                    'label' => 'Seller',
                    'source' => 'user.name',
                ],
                'price' => [
                    'type' => 'money',
                    'sortable' => true,
                    'currency' => 'CZK',
                ],
                'state' => [
                    'type' => 'enum',
                    'display' => 'badge',
                    'options' => [
                        'active' => ['label' => 'Active', 'color' => 'success'],
                        'draft' => ['label' => 'Draft', 'color' => 'secondary'],
                        'sold' => ['label' => 'Sold', 'color' => 'primary'],
                    ],
                    'filter' => [
                        'type' => 'select',
                        'label' => 'State',
                    ],
                ],
                'stats' => [
                    'type' => 'json',
                    'label' => 'Stats',
                ],
                'created_at' => [
                    'type' => 'datetime',
                    'label' => 'Created',
                    'sortable' => true,
                    'format' => 'Y-m-d',
                ],
                'tags' => [
                    'type' => 'json',
                    'label' => 'Tags',
                    'collection' => [
                        'table' => 'ra_test_tags',
                        'foreign_key' => 'ad_id',
                        'column' => 'label',
                    ],
                ],
            ],
        ],
    ],
];
