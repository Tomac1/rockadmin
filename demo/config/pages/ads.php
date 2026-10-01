<?php

declare(strict_types=1);

/**
 * The worked example the reference documentation points at: one grid using
 * every feature milestone 6 built for a list region, and one form using every
 * field type milestone 7 could honestly put over this table.
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
    // Milestone 8. Three actions, each showing a different thing an action
    // can be.
    //
    // `edit` is a `link`: it navigates, it carries a row placeholder, and it
    // needs no confirmation because it changes nothing.
    //
    // `archive` is a `post` with a `when`. It is drawn only on a row that is
    // active, and the condition is re-checked on the server when it runs --
    // `when` decides what is *offered*, and a client that was never shown the
    // control can post to it anyway.
    //
    // `bin` points at `@action:delete`, which is how a page gets a working
    // delete out of configuration alone: it reaches the verb the admin
    // already has, so no `set` has to spell out what deleting means.
    'actions' => [
        'edit' => [
            'type' => 'link',
            'to' => '@page:ads/{id}/edit',
            'label' => 'Edit',
            'permission' => 'ads.update',
        ],
        'archive' => [
            'type' => 'post',
            'to' => '@action:archive',
            'label' => 'Archive',
            'confirm' => 'Archive this ad?',
            'permission' => 'ads.update',
            'when' => ['state' => 'active'],
            'set' => ['state' => 'archived'],
        ],
        'bin' => [
            'type' => 'post',
            'to' => '@action:delete',
            'label' => 'Delete',
            'confirm' => 'Delete this ad? This cannot be undone.',
            'permission' => 'ads.delete',
        ],

        // Milestone 9. The same region, opened two ways, which is 8.5's own
        // argument for keeping regions and actions apart: a region renders
        // content and an action decides where that content appears, so the
        // panel below needs no second copy of itself to be shown as a modal.
        //
        // 8.5 calls `preview` "only shorthand" for exactly this -- a region
        // plus an `open` action. The region shipped in milestone 6; this is
        // the other half, spelled out rather than implied.
        'peek' => [
            'type' => 'open',
            'in' => 'offcanvas',
            'size' => 'lg',
            'target' => '@region:preview',
            'title' => 'Ad #{id}',
            'params' => ['id' => '{id}'],
            'label' => 'Preview',
            'permission' => 'ads.view',
        ],
        'peek_modal' => [
            'type' => 'open',
            'in' => 'modal',
            'size' => 'lg',
            'target' => '@region:preview',
            'title' => 'Ad #{id}',
            'params' => ['id' => '{id}'],
            'label' => 'Modal',
            'permission' => 'ads.view',
        ],
    ],

    // Over a selection rather than one row. `edit` is deliberately absent:
    // there is no such thing as editing forty rows at once, and a `link` in
    // here is refused at load for that reason. Offered on a row and offered
    // over a selection are two different permissions, and this list is how a
    // page says which.
    'bulk_actions' => ['archive', 'bin'],

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
                    // One definition, in demo/config/enums.php, read here for
                    // the badge, by the filter below, and by the form's select.
                    'options' => '@enum:ad_state',
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
                // An actions column is an ordinary column, placed among the
                // others and named for what it holds -- the key becomes the
                // class `ra-grid-cell-tools` and the template override path,
                // so `tools` is worth more than `actions2`. Declaring one
                // also takes away the grid's own Edit cell: a page that says
                // where its row controls go has said it.
                'tools' => [
                    'type' => 'actions',
                    'label' => '',
                    'items' => ['peek', 'peek_modal', 'edit', 'archive', 'bin'],
                ],
            ],
        ],
        // No 'fields' key: this preview inherits the grid's own columns
        // (spec 8.5), in the same order — the row a click on 'ID' above
        // opens at /p/ads/{id}, the same fields the grid already shows, one
        // per line. 'stats' being JSON is what shows the wide-row rule: it
        // spans the full width of the preview instead of squeezing into a
        // label-and-value column.
        'preview' => [
            'type' => 'preview',
        ],
        // The form. A page's create, edit and copy screens are this one
        // region, drawn three ways: `FormRegion::create()` starts every field
        // at its default, `edit()` at the row's stored value, and `copy()` at
        // the row's value except for the fields `copy.reset` names.
        //
        // Six of the eleven field types are here, which is every one this
        // table can honestly carry: `multiselect` and `radio` would need a
        // column that does not exist, and `password` over a public ads table
        // would be a lie. `id` is deliberately not a field: it is generated
        // by the database (see demo/seed.php), and a form that offered it
        // would be offering to collide with an existing row.
        'form' => [
            'type' => 'form',
            'form' => [
                'fields' => [
                    'title' => [
                        'type' => 'text',
                        'required' => true,
                        'min' => 3,
                        'max' => 200,
                        'placeholder' => 'Horské kolo',
                        'help' => 'What the ad is for. Shown in the grid and searched by its search box.',
                    ],
                    'description' => [
                        'type' => 'textarea',
                        'rows' => 4,
                        'max' => 2000,
                        'help' => 'Free text. Not shown in the grid — this is what a textarea is for.',
                    ],
                    'price' => [
                        'type' => 'number',
                        'label' => 'Price (CZK)',
                        'required' => true,
                        'min' => 0,
                        'max' => 10000000,
                        'step' => '100',
                        'default' => 5000,
                    ],
                    'state' => [
                        'type' => 'select',
                        'required' => true,
                        // The same enumeration the grid's badge and filter
                        // read, so the form cannot offer a state the grid
                        // has no way to display.
                        'options' => '@enum:ad_state',
                        'default' => 'draft',
                    ],
                    'featured' => [
                        'type' => 'checkbox',
                        'label' => 'Featured',
                        'help' => 'Unticked sends nothing at all, which is stored as false rather than left alone.',
                    ],
                    'published_on' => [
                        'type' => 'date',
                        'label' => 'Published on',
                        'default' => '@now',
                        'help' => 'Defaults to today. A copy starts here again rather than carrying the original date.',
                    ],
                    // No control: `hidden` means the value comes from the
                    // default when the row is saved and never from the wire,
                    // so a submission edited in a browser cannot set it.
                    // Applied on create only — an update must not stamp a
                    // row's own created_at with the time of the last typo fix.
                    'created_at' => [
                        'type' => 'hidden',
                        'hidden' => true,
                        'default' => '@now',
                    ],
                ],
                'copy' => [
                    // A copy is a new ad, not a republished one: its
                    // publication date starts at today's default again while
                    // the title, the description, the price, the state and
                    // the featured flag are carried over.
                    'reset' => ['published_on'],
                ],
            ],
        ],
    ],
];
