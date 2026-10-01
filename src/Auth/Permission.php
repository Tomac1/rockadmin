<?php

declare(strict_types=1);

namespace RockAdmin\Auth;

use RockAdmin\Config\ConfigException;
use RockAdmin\Config\Schema;
use RockAdmin\Page\PageDefinition;
use RockAdmin\Page\PageRepository;

/**
 * One thing a role may be granted: `page.action`.
 *
 * Permission names are not configured — they are generated from the pages
 * (spec 9.2), so adding a page file brings its own permissions and there is no
 * second list to keep in step with the first.
 */
final class Permission
{
    /**
     * The only actions a permission may name.
     *
     * A closed set, so `ads.publish` is refused at load with the alternatives
     * rather than silently denied forever at request time.
     *
     * @var list<string>
     */
    public const ACTIONS = ['view', 'create', 'update', 'delete'];

    /**
     * The actions a page only has when something can write to it.
     *
     * @var list<string>
     */
    private const WRITE_ACTIONS = ['create', 'update', 'delete'];

    public function __construct(
        public readonly string $page,
        public readonly string $action,
    ) {
    }

    public function name(): string
    {
        return $this->page . '.' . $this->action;
    }

    /**
     * Every permission name this admin has, sorted.
     *
     * Sorted so that two runs produce the same list and a caller may compare
     * resolved sets with assertSame rather than by hand.
     *
     * @return list<string>
     */
    public static function catalogue(PageRepository $pages): array
    {
        $names = [];

        foreach ($pages->names() as $page) {
            foreach (self::forPage($pages->get($page)) as $permission) {
                $names[] = $permission->name();
            }
        }

        sort($names);

        return $names;
    }

    /**
     * Refuses an action whose `permission` no page generates.
     *
     * An action declares the permission a reader needs (spec 8.5), and a
     * typo in it would deny the action forever, silently, to everybody —
     * exactly the failure `Roles::fromConfig()` refuses for a role pattern.
     *
     * It is checked here rather than in `PageRepository`, where every other
     * refusal about a page lives, because the catalogue is generated from
     * *all* the pages and this class is what generates it. `Auth` depends on
     * `Page`; a page repository that checked the catalogue itself would have
     * to depend back on `Auth`, and that is a cycle. So the rule is the same
     * one the rest of the project follows — parse where the syntax is,
     * validate where the knowledge is — and the knowledge is here.
     *
     * @param  list<string>    $catalogue every permission name that exists
     * @throws ConfigException naming the page, the action and the nearest real permission
     */
    public static function assertActionsAreGrantable(PageRepository $pages, array $catalogue): void
    {
        foreach ($pages->names() as $name) {
            foreach ($pages->get($name)->actions as $action) {
                if ($action->permission === '' || \in_array($action->permission, $catalogue, true)) {
                    continue;
                }

                $nearest = Schema::nearestOf($catalogue, $action->permission);
                $suffix = $nearest === null ? '' : " Did you mean '{$nearest}'?";

                throw new ConfigException(\sprintf(
                    "Page '%s': action '%s' needs the permission '%s', which no page generates, so nobody "
                    . 'could ever be granted it and the action would never be drawn for anybody.%s',
                    $name,
                    $action->key,
                    $action->permission,
                    $suffix,
                ));
            }
        }
    }

    /**
     * The permissions one page generates.
     *
     * A page with no form region generates `view` and nothing else. That is
     * the same fact `ListRegion` reads to decide whether a row draws an edit
     * link and whether the create button appears — one fact, read from the
     * page definition, never two conditions that have to be kept in agreement.
     *
     * @return list<self>
     */
    public static function forPage(PageDefinition $page): array
    {
        $permissions = [new self($page->name, 'view')];

        if ($page->firstFormRegion() === null) {
            return $permissions;
        }

        foreach (self::WRITE_ACTIONS as $action) {
            $permissions[] = new self($page->name, $action);
        }

        return $permissions;
    }
}
