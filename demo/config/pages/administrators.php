<?php

declare(strict_types=1);

/**
 * The administrators of this admin, managed inside it.
 *
 * **It is an ordinary page.** Nothing here is special to `ra_users`: a grid, a
 * form, an action, the same keys `ads.php` uses. That is the decision
 * milestone 12 made rather than shipping a built-in page -- a built-in one
 * needs a second page root in `PageRepository`, a rule for how a project
 * overrides it, and a permission namespace nobody declared, while a page file
 * needs none of that and is editable by the project that owns the admin.
 *
 * **What a page file cannot say lives in `RockAdmin\Auth\AdminUserOperation`**,
 * named here as `@handler:ra_admin_user` and registered in `demo/index.php`:
 * hashing the password, leaving it alone when the box is empty, writing the
 * two JSON columns, refusing to remove the last account that can reach
 * everything, and sending an invitation after the commit. Copy both when you
 * copy this file.
 *
 * **`roles` and `workspaces` repeat what `roles.php` and `workspaces.php`
 * declare**, and that is a real duplication rather than an oversight. A
 * field's options are a list of values a form offers, and roles are
 * configuration read at a different level; a page file cannot reach into them.
 * It is two lines to keep in step, and the alternative -- a dynamic option
 * provider -- is a mechanism this project does not have and does not need for
 * two lists that change about once a year.
 */

return [
    'title' => 'Administrators',
    'entity' => [
        'table' => 'ra_users',
    ],

    'actions' => [
        'invite' => [
            'type' => 'post',
            'to' => '@action:invite',
            'label' => 'Invite',
            'icon' => 'mail',
            // No native dialog anywhere in this project (rule 6): this draws
            // `templates/ui/confirm.php`, which looks like the admin and
            // carries this question.
            'confirm' => 'Send a new link for setting a password to this administrator?',
            'permission' => 'administrators.update',
            // The same operation the form uses. It knows which of the two it
            // is serving, because `OperationContext::$operation` is either a
            // built-in verb or the page's own.
            'handler' => '@handler:ra_admin_user',
            'refresh' => ['grid'],
        ],
    ],

    'regions' => [
        'grid' => [
            'type' => 'list',
            'per_page' => 25,
            'sort' => ['email' => 'asc'],
            'columns' => [
                'email' => ['label' => 'Email', 'link' => true, 'sortable' => true],
                'name' => ['label' => 'Name', 'sortable' => true],
                'is_active' => ['type' => 'bool', 'label' => 'Active'],
                'last_login_at' => ['type' => 'datetime', 'label' => 'Last sign-in', 'sortable' => true],
                'tools' => [
                    'type' => 'actions',
                    'label' => '',
                    'items' => ['invite'],
                ],
            ],
        ],

        'form' => [
            'type' => 'form',
            'form' => [
                'handler' => '@handler:ra_admin_user',
                'fields' => [
                    'email' => [
                        'type' => 'text',
                        'label' => 'Email',
                        'required' => true,
                        'help' => 'What they sign in with. Stored lower-cased, so case never decides '
                            . 'whether somebody can get in.',
                    ],
                    'name' => [
                        'type' => 'text',
                        'label' => 'Name',
                        'required' => true,
                        'help' => 'What the audit log records beside everything they change.',
                    ],
                    'password' => [
                        'type' => 'password',
                        'label' => 'Password',
                        'help' => 'Leave empty to send an invitation instead, or — when editing — to '
                            . 'keep the password they already have.',
                    ],
                    'roles' => [
                        'type' => 'multiselect',
                        'label' => 'Roles',
                        // Mirrors demo/config/roles.php. See the note above.
                        'options' => [
                            'superadmin' => 'Superadmin',
                            'editor' => 'Editor',
                            'viewer' => 'Viewer',
                        ],
                    ],
                    'workspaces' => [
                        'type' => 'multiselect',
                        'label' => 'Workspaces',
                        // Mirrors demo/config/workspaces.php.
                        'options' => [
                            'cyklobazar' => 'Bike Market',
                            'motobazar' => 'Moto Market',
                        ],
                        'help' => 'Which sites they may work in. Somebody holding both may also work '
                            . 'across all of them at once.',
                    ],
                    'is_active' => [
                        'type' => 'checkbox',
                        'label' => 'Active',
                        'help' => 'Unticking it ends their access at the next request, not at the next '
                            . 'sign-in: every request resolves the session back to this row.',
                    ],
                    // No control: the value comes from the default when the
                    // row is created and never from the wire.
                    'created_at' => [
                        'type' => 'hidden',
                        'hidden' => true,
                        'default' => '@now',
                    ],
                ],
            ],
        ],
    ],
];
