<?php

declare(strict_types=1);

namespace RockAdmin\Auth;

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
