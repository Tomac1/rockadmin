<?php

declare(strict_types=1);

namespace RockAdmin\Auth;

/**
 * Who is asking — id, email, name, roles and workspaces.
 *
 * **Never the password hash.** Not as a property, not behind a getter, not in
 * an array of "the rest of the row". This object travels into the gate, into
 * the audit log and into templates, and a view object that can print a hash
 * eventually prints one — in a `var_dump` during a debugging session, in an
 * exception's context, in a JSON response somebody added later. The hash is
 * read inside `SqlAuthenticator::attempt()` and goes no further, which is why
 * this class has no way to carry it rather than a convention against doing so.
 *
 * `roles` and `workspaces` are assignment, which is data (spec 9.2) — they are
 * lists of names, and what each name may do is configuration this object knows
 * nothing about. `Roles::allows()` answers that, and it deliberately grants
 * nothing for a role name configuration no longer declares.
 *
 * `workspaces` is carried and not yet used: workspaces are the next milestone,
 * and reading the column now means that milestone changes no query here.
 */
final class AuthUser
{
    /**
     * @param string       $id         the key as text, whatever the column's type
     * @param list<string> $roles      role names, as stored
     * @param list<string> $workspaces workspace names, as stored
     */
    public function __construct(
        public readonly string $id,
        public readonly string $email,
        public readonly string $name,
        public readonly array $roles = [],
        public readonly array $workspaces = [],
    ) {
    }

    public function hasRole(string $role): bool
    {
        return \in_array($role, $this->roles, true);
    }

    /**
     * Whether this user is assigned that workspace.
     *
     * The scope check that uses it belongs to the workspace milestone; this is
     * the half that reads the column, and it answers only about assignment.
     */
    public function hasWorkspace(string $workspace): bool
    {
        return \in_array($workspace, $this->workspaces, true);
    }
}
