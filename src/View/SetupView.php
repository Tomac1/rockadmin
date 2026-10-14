<?php

declare(strict_types=1);

namespace RockAdmin\View;

/**
 * The first administrator, prepared for its template.
 *
 * `LoginView`'s third sibling, and the one drawn exactly once per
 * installation: `_setup` exists only while `ra_users` is empty.
 *
 * **It says which role and which workspaces the account will get.** An
 * installation's first account is the one that can create every other, so the
 * page that creates it has to be honest about what it is handing over --
 * somebody who thinks they are making themselves a viewer and is in fact made
 * a superadmin has not been asked.
 *
 * No password property, for the reason `LoginView` gives.
 */
final class SetupView
{
    public function __construct(
        public readonly ShellView $shell,
        public readonly string $action,
        public readonly string $token,
        /** The role the first account is given, by label. */
        public readonly string $role,
        /** How many workspaces it will hold; 0 where none are configured. */
        public readonly int $workspaces,
        public readonly string $email = '',
        public readonly string $name = '',
        public readonly ?string $message = null,
        public readonly string $title = 'First administrator',
    ) {
    }

    public function classes(): string
    {
        return Classes::of('login-form') . ' ' . Classes::identity('setup-form', 'first-user');
    }

    public function bodyClasses(): string
    {
        return Classes::of('login') . ' ' . Classes::identity('page-type', 'setup');
    }
}
