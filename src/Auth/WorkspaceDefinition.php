<?php

declare(strict_types=1);

namespace RockAdmin\Auth;

/**
 * One workspace, built from `workspaces.php`.
 *
 * Specification 9.3: a named context — a bundle of variables, a set of
 * visible pages, and a unit of permission. It covers configuration splitting
 * and multi-tenancy alike, which is why it is one mechanism rather than
 * three: production and staging, per-country branches, and two classifieds
 * sites sharing a database are the same arrangement seen from different
 * angles.
 *
 * A plain value object, like `ActionDefinition` and `ColumnDefinition`:
 * everything a `workspaces.php` may get wrong is refused in `Workspaces`,
 * so nothing here needs checking again.
 */
final class WorkspaceDefinition
{
    /**
     * @param array<string, bool|float|int|string> $vars  the values `{{workspace.*}}` binds to
     * @param list<string>|null                    $pages the pages visible here, or null for every page
     */
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly array $vars = [],
        public readonly ?array $pages = null,
    ) {
    }

    /**
     * Whether this workspace shows that page.
     *
     * Null is `'pages' => '@all'`, written out: every page, including ones
     * added after this file was last edited. It is deliberately not the
     * default — see `Workspaces::build()` for why a page nobody listed is a
     * page nobody sees.
     */
    public function shows(string $page): bool
    {
        return $this->pages === null || \in_array($page, $this->pages, true);
    }

    /**
     * One of this workspace's variables, or null.
     *
     * Null is "this workspace does not declare it", which is a different
     * thing from a variable whose value happens to be empty — and the
     * difference matters, because the first is a configuration mistake and
     * the second is data. Whoever binds a placeholder tells them apart.
     */
    public function var(string $name): bool|float|int|string|null
    {
        return $this->vars[$name] ?? null;
    }
}
