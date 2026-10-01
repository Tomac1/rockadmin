<?php

declare(strict_types=1);

namespace RockAdmin\Auth;

use RockAdmin\Config\ConfigException;
use RockAdmin\Config\Schema;
use RockAdmin\Page\PageRepository;

/**
 * The workspaces, resolved once from `workspaces.php`.
 *
 * The sibling of `Roles`, and built the same way for the same reason: the
 * loader stores the file raw because it has no page repository, and this is
 * the caller that has one. A `workspaces.php` naming a page that does not
 * exist is refused here, where the catalogue is.
 *
 * **No `workspaces.php` means no workspaces, and nothing changes.** That is
 * the single-tenant case and it has to stay free: no scope, no switcher, no
 * extra check, every page visible. An admin over one database with one site
 * should not pay for a subsystem it is not using, and the day it grows a
 * second site it adds one file.
 *
 * **A page no workspace lists is a page nobody sees.** This is the decision
 * the milestone plan left open, and it goes the fail-closed way. The two
 * readings are "visible everywhere" and "visible nowhere", and the cost of
 * being wrong is not symmetric: forgetting to list a page under the first
 * reading silently exposes one tenant's page to every other tenant, while
 * forgetting it under this one makes the page vanish, which somebody notices
 * within a minute and can fix. `'pages' => '@all'` says "every page" out
 * loud, in the vocabulary a preview region already uses for the same idea.
 */
final class Workspaces
{
    /** @param array<string, WorkspaceDefinition> $workspaces keyed by name, in declaration order */
    private function __construct(private readonly array $workspaces)
    {
    }

    /** No workspaces at all: the single-tenant case, where nothing is scoped. */
    public static function none(): self
    {
        return new self([]);
    }

    /**
     * @param  array<string, mixed> $raw   the contents of workspaces.php
     * @param  PageRepository       $pages the catalogue a `pages` list is checked against
     * @throws ConfigException      naming the workspace and the key that is wrong
     */
    public static function fromConfig(array $raw, PageRepository $pages): self
    {
        if ($raw === []) {
            return self::none();
        }

        $built = [];

        foreach ($raw as $name => $config) {
            $key = (string) $name;

            if (!\is_array($config)) {
                throw new ConfigException(\sprintf(
                    "Workspace '%s' is a %s. A workspace is an array of 'label', 'vars' and 'pages'.",
                    $key,
                    get_debug_type($config),
                ));
            }

            /** @var array<string, mixed> $config */
            $built[$key] = self::build($key, $config, $pages);
        }

        return new self($built);
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function build(string $name, array $config, PageRepository $pages): WorkspaceDefinition
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
            throw new ConfigException(
                "'{$name}' is not a workspace name. A workspace is addressed in a URL — "
                . "POST /w/{$name} — and named like every other configuration key: lower-case "
                . 'snake_case.',
            );
        }

        $label = \is_string($config['label'] ?? null) && $config['label'] !== ''
            ? $config['label']
            : $name;

        return new WorkspaceDefinition(
            name: $name,
            label: $label,
            vars: self::vars($name, $config['vars'] ?? null),
            pages: self::pages($name, $config['pages'] ?? null, $pages),
        );
    }

    /**
     * The values `{{workspace.*}}` binds to.
     *
     * Scalars only, and that is 9.3's own rule rather than a limitation here:
     * "workspace variables are values, not SQL". A value becomes a bound
     * parameter and can only ever be a parameter; anything else would have to
     * be interpolated, and interpolating configuration into a WHERE clause is
     * the thing the whole arrangement exists to make impossible.
     *
     * @return array<string, bool|float|int|string>
     */
    private static function vars(string $name, mixed $raw): array
    {
        if ($raw === null) {
            return [];
        }

        if (!\is_array($raw)) {
            throw new ConfigException(\sprintf(
                "Workspace '%s' has 'vars' => %s. It is a map of name to value, such as "
                . "['tenant_id' => 1].",
                $name,
                get_debug_type($raw),
            ));
        }

        $vars = [];

        foreach ($raw as $varName => $value) {
            if (!\is_scalar($value)) {
                throw new ConfigException(\sprintf(
                    "Workspace '%s' sets '%s' to a %s. A workspace variable is a value — a string, a "
                    . 'number, true or false — because it becomes a bound parameter in a WHERE clause. '
                    . 'Specification 9.3: variables are values, not SQL, and a genuine SQL fragment is '
                    . "the separate 'raw_condition'.",
                    $name,
                    (string) $varName,
                    get_debug_type($value),
                ));
            }

            $vars[(string) $varName] = $value;
        }

        return $vars;
    }

    /**
     * The pages this workspace shows, or null for every page.
     *
     * @return list<string>|null
     */
    private static function pages(string $name, mixed $raw, PageRepository $pages): ?array
    {
        if ($raw === '@all') {
            return null;
        }

        if ($raw === null) {
            throw new ConfigException(
                "Workspace '{$name}' lists no 'pages', so it would show none. A page no workspace "
                . 'lists is a page nobody sees — the alternative reading, where an unlisted page shows '
                . 'everywhere, hides a mistake instead of a page, and in a multi-tenant admin the '
                . "mistake it hides is one tenant's page appearing in another's. Write the pages out, "
                . "or 'pages' => '@all' to say every page on purpose.",
            );
        }

        if (!\is_array($raw)) {
            throw new ConfigException(\sprintf(
                "Workspace '%s' has 'pages' => %s. It is a list of page names, or '@all'.",
                $name,
                get_debug_type($raw),
            ));
        }

        $known = $pages->names();
        $listed = [];

        foreach ($raw as $page) {
            if (!\is_string($page)) {
                throw new ConfigException(\sprintf(
                    "Workspace '%s' names a page as a %s. Page names are strings.",
                    $name,
                    get_debug_type($page),
                ));
            }

            if (!\in_array($page, $known, true)) {
                $nearest = Schema::nearestOf($known, $page);

                throw new ConfigException(\sprintf(
                    "Workspace '%s' shows the page '%s', which does not exist.%s",
                    $name,
                    $page,
                    $nearest === null ? '' : " Did you mean '{$nearest}'?",
                ));
            }

            $listed[] = $page;
        }

        if ($listed === []) {
            throw new ConfigException(
                "Workspace '{$name}' has 'pages' => [], so it shows nothing at all and nobody in it "
                . 'could do anything. Remove the workspace, or list what it shows.',
            );
        }

        return $listed;
    }

    /** Whether any workspace is configured at all. */
    public function configured(): bool
    {
        return $this->workspaces !== [];
    }

    public function has(string $name): bool
    {
        return isset($this->workspaces[$name]);
    }

    /** @throws ConfigException when nothing of that name is configured */
    public function get(string $name): WorkspaceDefinition
    {
        if (isset($this->workspaces[$name])) {
            return $this->workspaces[$name];
        }

        $nearest = Schema::nearestOf($this->names(), $name);

        throw new ConfigException(
            "No workspace '{$name}' is configured."
            . ($nearest === null ? '' : " Did you mean '{$nearest}'?"),
        );
    }

    /** @return list<string> every workspace name, in declaration order */
    public function names(): array
    {
        return array_keys($this->workspaces);
    }

    /**
     * The workspaces this user may work in, in declaration order.
     *
     * A name on the user that no longer matches a configured workspace is
     * dropped rather than refused: a workspace can be removed from the file
     * while users still carry it, and an admin that refused to let those
     * people in at all would turn a tidy-up into an outage.
     *
     * @return list<WorkspaceDefinition>
     */
    public function forUser(?AuthUser $user): array
    {
        if ($user === null) {
            return [];
        }

        $theirs = [];

        foreach ($this->workspaces as $name => $workspace) {
            if ($user->hasWorkspace($name)) {
                $theirs[] = $workspace;
            }
        }

        return $theirs;
    }

    /**
     * Whether this user holds every workspace there is.
     *
     * The condition 9.3 puts on "all workspaces": it appears in the switcher
     * only for somebody who could reach every row through it anyway, so
     * choosing it grants nothing they did not already have. Somebody holding
     * two workspaces of three does not get it, because for them it would be a
     * view with a boundary in it and no way to see where.
     */
    public function userHoldsEvery(?AuthUser $user): bool
    {
        return $this->configured() && \count($this->forUser($user)) === \count($this->workspaces);
    }
}
