<?php

declare(strict_types=1);

namespace RockAdmin\Auth;

use RockAdmin\Config\ConfigException;
use RockAdmin\Config\Schema;
use RockAdmin\Config\ValidationError;
use RockAdmin\Config\Validator;

/**
 * What each role may do, resolved once from `roles.php`.
 *
 * Roles are configuration and assignment is data (spec 9.2): this is the
 * configuration half, and it knows nothing about users. It is given the
 * catalogue of permission names the pages generated — `Permission::catalogue()`
 * builds that — and never reads the pages itself, so the only thing that
 * decides which permissions exist is the page configuration.
 *
 * **`*` widens, `!` subtracts, and order does not matter.** Every widening is
 * applied first and every subtraction afterwards, so `['ads.*', '!ads.delete']`
 * and `['!ads.delete', 'ads.*']` resolve identically. Folding the list in
 * sequence would make the meaning of a role depend on the order somebody
 * happened to type it in, which is not a property anybody can reason about.
 */
final class Roles
{
    /**
     * @param array<string, string>       $labels      role => label
     * @param array<string, list<string>> $permissions role => resolved permission names, sorted
     */
    private function __construct(
        private readonly array $labels,
        private readonly array $permissions,
    ) {
    }

    /**
     * @param  array<string, mixed> $raw       the contents of roles.php
     * @param  list<string>         $catalogue every permission name that exists
     * @throws ConfigException      when a role names a page, an action or a permission that does not exist
     */
    public static function fromConfig(array $raw, array $catalogue): self
    {
        $pages = self::pagesOf($catalogue);
        $labels = [];
        $permissions = [];

        foreach ($raw as $key => $definition) {
            $role = (string) $key;

            if (!\is_array($definition)) {
                throw new ConfigException(
                    "Role '{$role}' must be an array, got " . get_debug_type($definition) . '.',
                );
            }

            /** @var array<string, mixed> $definition narrows array<mixed, mixed> */
            $labels[$role] = self::labelOf($role, $definition);
            $permissions[$role] = self::resolve($role, self::patterns($role, $definition), $catalogue, $pages);
        }

        return new self($labels, $permissions);
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->permissions);
    }

    /**
     * The role that can reach the most, by name.
     *
     * Asked by whoever has to pick a role without being told one: `_setup`
     * when it makes an installation's first account, and the administrators
     * page when it decides whether removing somebody would lock the building.
     *
     * Counted rather than detected. `fromConfig()` expands every pattern
     * against the catalogue of generated permissions, so by the time a role is
     * in here `['*']` and `['*.view']` are both plain lists and neither
     * contains a star -- the widest resolved set is the honest reading of "the
     * role that can reach everything", and it does not depend on an
     * installation calling it `superadmin` rather than `owner` or `spravce`.
     *
     * Ties go to the first declared. An installation with no roles at all
     * answers '', which is a configuration with no superuser and not something
     * to invent one for.
     */
    public function widest(): string
    {
        $widest = '';
        $most = -1;

        foreach ($this->names() as $name) {
            $count = \count($this->permissions($name));

            if ($count > $most) {
                $widest = $name;
                $most = $count;
            }
        }

        return $widest;
    }

    public function has(string $role): bool
    {
        return isset($this->permissions[$role]);
    }

    public function label(string $role): string
    {
        return $this->labels[$role] ?? $role;
    }

    /**
     * Everything this role may do, sorted.
     *
     * @return list<string>
     */
    public function permissions(string $role): array
    {
        return $this->permissions[$role] ?? [];
    }

    /**
     * Whether any of these roles carries this permission.
     *
     * Fails closed in both directions: a permission nobody generated is denied
     * even to a role holding `*`, because the resolved set only ever contains
     * names from the catalogue; and a role name that configuration no longer
     * declares grants nothing, because assignment is data and data outlives
     * the configuration that named it.
     *
     * @param list<string> $roles
     */
    public function allows(array $roles, string $permission): bool
    {
        foreach ($roles as $role) {
            if (\in_array($permission, $this->permissions[$role] ?? [], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed> $definition
     * @return list<string>
     */
    private static function patterns(string $role, array $definition): array
    {
        if (!\array_key_exists('permissions', $definition)) {
            throw new ConfigException(
                "Role '{$role}' has no 'permissions' key. A role that grants nothing is written as "
                . "'permissions' => [], so that leaving the key out is always a mistake rather than "
                . 'a role that silently allows nothing.',
            );
        }

        $errors = (new Validator())->validate($definition, RoleSchema::create(), "roles.{$role}");

        if ($errors !== []) {
            throw new ConfigException(
                "Role '{$role}' is invalid:" . PHP_EOL . implode(PHP_EOL, array_map(
                    static fn (ValidationError $e): string => "  {$e->path}: {$e->message}",
                    $errors,
                )),
            );
        }

        $patterns = [];

        /** @var array<array-key, mixed> $declared */
        $declared = $definition['permissions'];

        foreach ($declared as $pattern) {
            if (!\is_string($pattern)) {
                throw new ConfigException(
                    "Role '{$role}' has a permission that is not a string, got "
                    . get_debug_type($pattern) . '.',
                );
            }

            $patterns[] = $pattern;
        }

        return $patterns;
    }

    /** @param array<string, mixed> $definition */
    private static function labelOf(string $role, array $definition): string
    {
        $label = $definition['label'] ?? null;

        if ($label === null) {
            return $role;
        }

        if (!\is_string($label)) {
            throw new ConfigException(
                "Role '{$role}' has a non-string label, got " . get_debug_type($label) . '.',
            );
        }

        return $label;
    }

    /**
     * Widen everything, then subtract everything.
     *
     * The two passes are what makes the result independent of the order the
     * patterns were written in. Any implementation that walks the list once,
     * adding and removing as it goes, gives `['!ads.delete', 'ads.*']` the
     * delete permission and `['ads.*', '!ads.delete']` not.
     *
     * @param  list<string> $patterns
     * @param  list<string> $catalogue
     * @param  list<string> $pages
     * @return list<string>
     */
    private static function resolve(string $role, array $patterns, array $catalogue, array $pages): array
    {
        $granted = [];
        $subtracted = [];

        foreach ($patterns as $pattern) {
            // Explicit string checks rather than a regular expression: the
            // characters that matter here are '*' and '!', and both are PCRE
            // metacharacters that have to be escaped correctly inside a
            // single-quoted PHP string to mean themselves. ReturnAddress
            // reads its scheme the same way, and for the same reason.
            $subtracts = str_starts_with($pattern, '!');
            $body = $subtracts ? substr($pattern, 1) : $pattern;

            [$page, $action] = self::split($role, $pattern, $body, $pages, $catalogue);

            if ($subtracts && $page === '*' && $action === '*') {
                throw new ConfigException(
                    "Role '{$role}' subtracts everything with '{$pattern}'. A role that may do nothing is "
                    . "written as 'permissions' => [], which says so; subtracting every permission from "
                    . 'whatever happened to be granted is never what somebody meant.',
                );
            }

            $matched = self::match($page, $action, $catalogue);

            if ($subtracts) {
                $subtracted = [...$subtracted, ...$matched];

                continue;
            }

            $granted = [...$granted, ...$matched];
        }

        $resolved = array_values(array_diff(array_unique($granted), $subtracted));
        sort($resolved);

        return $resolved;
    }

    /**
     * @param  list<string>            $catalogue
     * @return list<string>
     */
    private static function match(string $page, string $action, array $catalogue): array
    {
        $matched = [];

        foreach ($catalogue as $name) {
            $dot = strrpos($name, '.');

            if ($dot === false) {
                continue;
            }

            $itsPage = substr($name, 0, $dot);
            $itsAction = substr($name, $dot + 1);

            if (($page === '*' || $page === $itsPage) && ($action === '*' || $action === $itsAction)) {
                $matched[] = $name;
            }
        }

        return $matched;
    }

    /**
     * @param  list<string>            $pages
     * @param  list<string>            $catalogue
     * @return array{string, string}   the page and the action, either of which may be '*'
     */
    private static function split(
        string $role,
        string $pattern,
        string $body,
        array $pages,
        array $catalogue,
    ): array {
        if ($body === '*') {
            return ['*', '*'];
        }

        $parts = explode('.', $body);

        if (\count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new ConfigException(
                "Role '{$role}' has the permission '{$pattern}', which is not a 'page.action' pattern. "
                . "A permission names a page and an action, as in 'ads.view'; '*' stands for every page "
                . "or every action, and a leading '!' subtracts.",
            );
        }

        [$page, $action] = $parts;

        if ($page !== '*' && !\in_array($page, $pages, true)) {
            $nearest = Schema::nearestOf($pages, $page);
            $suffix = $nearest === null ? '' : " Did you mean '{$nearest}'?";

            throw new ConfigException(
                "Role '{$role}' has the permission '{$pattern}', but there is no page '{$page}'.{$suffix}",
            );
        }

        if ($action !== '*' && !\in_array($action, Permission::ACTIONS, true)) {
            throw new ConfigException(
                "Role '{$role}' has the permission '{$pattern}', but '{$action}' is not an action. "
                . "The actions are '" . implode("', '", Permission::ACTIONS) . "'.",
            );
        }

        // A wildcard may match nothing — `*.delete` over an admin of read-only
        // pages is a widening that happens to be empty, and that is fine. A
        // pattern that names both a page and an action may not: `reports.delete`
        // is somebody expecting a permission that will never be checked, and
        // saying so at load is the difference between a typo and a hole.
        if ($page !== '*' && $action !== '*' && !\in_array("{$page}.{$action}", $catalogue, true)) {
            throw new ConfigException(
                "Role '{$role}' has the permission '{$pattern}', but the page '{$page}' declares no form "
                . "region, so it generates only '{$page}.view'. Give the page a form region, or drop the "
                . 'permission.',
            );
        }

        return [$page, $action];
    }

    /**
     * @param  list<string> $catalogue
     * @return list<string>
     */
    private static function pagesOf(array $catalogue): array
    {
        $pages = [];

        foreach ($catalogue as $name) {
            $dot = strrpos($name, '.');

            if ($dot !== false) {
                $pages[] = substr($name, 0, $dot);
            }
        }

        return array_values(array_unique($pages));
    }
}
