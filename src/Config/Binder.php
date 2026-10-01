<?php

declare(strict_types=1);

namespace RockAdmin\Config;

use RockAdmin\Auth\AuthUser;
use RockAdmin\Auth\WorkspaceDefinition;

/**
 * Turns the placeholders that survived configuration into this request's
 * values.
 *
 * `{{workspace.tenant_id}}` and `{{user.id}}` cannot be baked into a cached
 * configuration, because they differ per request — so `Resolver` leaves them
 * as `Placeholder` objects and this is where they stop being objects. It is
 * the last step before a scope reaches a `Query`.
 *
 * **A bound value can only ever be a value.** That is the whole point of the
 * arrangement, and it is why `Placeholder` is an object rather than text that
 * survives: text would have to be interpolated somewhere, and the somewhere
 * would be a WHERE clause. Specification 9.3 draws the same line — workspace
 * variables are values, and a genuine SQL fragment needs the separate,
 * explicitly dangerous `raw_condition`.
 *
 * **Refusing is the important half.** A placeholder naming something that is
 * not there must not bind to null: `WHERE tenant_id IS NULL` is a silently
 * empty grid, and a scope quietly dropped is every tenant's rows at once.
 * Both are worse than a message naming the placeholder.
 */
final class Binder
{
    public function __construct(
        private readonly ?WorkspaceDefinition $workspace,
        private readonly ?AuthUser $user,
    ) {
    }

    /**
     * Every placeholder in a scope, replaced by its value.
     *
     * @param  array<string, mixed> $scope as the page declared it
     * @return array<string, mixed> the same keys, with values
     * @throws ConfigException      naming the column and the placeholder
     */
    public function scope(array $scope): array
    {
        $bound = [];

        foreach ($scope as $column => $value) {
            $bound[$column] = $value instanceof Placeholder
                ? $this->value($value, (string) $column)
                : $value;
        }

        return $bound;
    }

    /**
     * One placeholder's value.
     *
     * @param string $where the column it scopes, for a refusal to name
     *
     * @throws ConfigException
     */
    public function value(Placeholder $placeholder, string $where): bool|float|int|string
    {
        $value = match ($placeholder->namespace) {
            'workspace' => $this->fromWorkspace($placeholder, $where),
            'user' => $this->fromUser($placeholder, $where),
            default => throw new ConfigException(\sprintf(
                "The scope on '%s' is %s, and '%s' is not something that is bound per request. The "
                . "namespaces that are: 'workspace', 'user'.",
                $where,
                (string) $placeholder,
                $placeholder->namespace,
            )),
        };

        return $value;
    }

    private function fromWorkspace(Placeholder $placeholder, string $where): bool|float|int|string
    {
        if ($this->workspace === null) {
            // Either no workspace is active -- "all workspaces", where the
            // scope should have been dropped before reaching here -- or none
            // is configured while a page declares one. Both are mistakes one
            // layer up, and binding anything at all would hide them.
            throw new ConfigException(\sprintf(
                "The scope on '%s' is %s, and no workspace is active. A page scoped to a workspace "
                . 'needs one: either `workspaces.php` declares none, or the scope should have been '
                . 'dropped for a reader working across all of them.',
                $where,
                (string) $placeholder,
            ));
        }

        $value = $this->workspace->var($placeholder->name);

        if ($value === null) {
            $has = $this->workspace->vars === []
                ? 'It declares none.'
                : "It declares: '" . implode("', '", array_keys($this->workspace->vars)) . "'.";

            throw new ConfigException(\sprintf(
                "The scope on '%s' is %s, and the workspace '%s' has no variable '%s'. %s Binding it "
                . 'to nothing would scope the query to rows whose %s is NULL, which is an empty grid '
                . 'with no visible cause.',
                $where,
                (string) $placeholder,
                $this->workspace->name,
                $placeholder->name,
                $has,
                $where,
            ));
        }

        return $value;
    }

    private function fromUser(Placeholder $placeholder, string $where): string
    {
        if ($this->user === null) {
            throw new ConfigException(\sprintf(
                "The scope on '%s' is %s, and nobody is signed in. A page scoped to the signed-in "
                . 'administrator cannot be rendered for an anonymous request.',
                $where,
                (string) $placeholder,
            ));
        }

        // Only what a user actually is. `roles` and `workspaces` are lists
        // and a scope is a value, so they are refused here rather than
        // stringified into something no WHERE clause could mean.
        return match ($placeholder->name) {
            'id' => $this->user->id,
            'email' => $this->user->email,
            'name' => $this->user->name,
            default => throw new ConfigException(\sprintf(
                "The scope on '%s' is %s, and a user has no '%s' that is a single value. The ones "
                . "that are: 'id', 'email', 'name'. Roles and workspaces are lists, and a scope is "
                . 'one value compared to one column.',
                $where,
                (string) $placeholder,
                $placeholder->name,
            )),
        };
    }
}
