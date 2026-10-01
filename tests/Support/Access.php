<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Support;

use RockAdmin\Auth\AuthUser;
use RockAdmin\Auth\Gate;
use RockAdmin\Auth\Identity;
use RockAdmin\Auth\Permission;
use RockAdmin\Auth\Roles;
use RockAdmin\Http\ArraySessionStore;
use RockAdmin\Page\PageDefinition;
use RockAdmin\Page\PageRepository;
use RockAdmin\View\Menu;
use RockAdmin\View\MenuItemView;

/**
 * A gate and an identity for a test that renders something.
 *
 * Rendering asks the gate what it may draw, so anything that builds a
 * `ListRegion` or a `FormRegion` has to say who is asking. Most tests do not
 * care — they are about a grid's pager or a form's fields — and for those
 * this hands out one role holding `*` over the permissions the given pages
 * actually generate.
 *
 * **The catalogue is generated from the real page definitions**, never from
 * the page's name, so a page with no form region still generates only
 * `{page}.view` and `*` still grants no write on it. Faking a catalogue of
 * all four actions per page would make a read-only page draw edit links in
 * every test that used this helper, which is the exact defect the gate is
 * here to prevent.
 */
final class Access
{
    /** The role name `identity()` assigns and `gate()` grants by default. */
    public const string ROLE = 'tester';

    /**
     * @param list<PageDefinition> $pages the pages whose permissions exist at all
     * @param array<string, mixed> $roles as `roles.php` declares them
     */
    public static function gate(
        array $pages,
        array $roles = [self::ROLE => ['permissions' => ['*']]],
    ): Gate {
        $catalogue = [];

        foreach ($pages as $page) {
            foreach (Permission::forPage($page) as $permission) {
                $catalogue[] = $permission->name();
            }
        }

        sort($catalogue);

        return new Gate(Roles::fromConfig($roles, $catalogue));
    }

    /**
     * The same, for a test that has a real repository rather than hand-built
     * page definitions.
     *
     * @param array<string, mixed> $roles
     */
    public static function gateFor(
        PageRepository $pages,
        array $roles = [self::ROLE => ['permissions' => ['*']]],
    ): Gate {
        // The repository is handed over, not only its catalogue: a page may
        // declare `post` actions of its own, and the permission one of those
        // needs is read from the action rather than derived from the URL.
        return new Gate(Roles::fromConfig($roles, Permission::catalogue($pages)), $pages);
    }

    /** The navigation a handler test needs: these items, and one role over them. */
    public static function menu(PageRepository $pages, MenuItemView ...$items): Menu
    {
        return new Menu(array_values($items), self::gateFor($pages), self::identity(), $pages);
    }

    /**
     * A user, for tests that need one without an `Identity` around it.
     *
     * @param list<string> $roles
     * @param list<string> $workspaces
     */
    public static function user(
        array $roles = [self::ROLE],
        array $workspaces = [],
        string $id = '7',
    ): AuthUser {
        return new AuthUser($id, 'tester@example.test', 'Tester', $roles, $workspaces);
    }

    /**
     * A signed-in identity holding these roles.
     *
     * @param list<string> $roles
     * @param list<string> $workspaces
     */
    public static function identity(array $roles = [self::ROLE], array $workspaces = []): Identity
    {
        return self::identityFor(self::user($roles, $workspaces));
    }

    /** An identity resolving to this user, or to nobody when it is null. */
    public static function identityFor(?AuthUser $user): Identity
    {
        if ($user === null) {
            return new Identity(new ArraySessionStore(), new FakeAuthenticator());
        }

        return new Identity(
            new ArraySessionStore([Identity::SESSION_KEY => $user->id]),
            new FakeAuthenticator([$user->id => $user]),
        );
    }
}
