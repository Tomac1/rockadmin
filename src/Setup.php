<?php

declare(strict_types=1);

namespace RockAdmin;

use Closure;
use RockAdmin\Auth\Authenticator;
use RockAdmin\Http\SessionStore;
use RockAdmin\Storage\Storage;

/**
 * What the host supplies: the path to its configuration, and the services
 * configuration cannot hold.
 *
 * **Configuration holds data, the host supplies services** (specification
 * 11.2). The division is not a preference: a closure cannot be written into
 * `config.cache.php`, and the cache is what makes loading the configuration a
 * single `include` in production. So a PDO, a session store, an authenticator,
 * a storage and a mailer callback arrive here, and everything else arrives in
 * `rockadmin.php`.
 *
 * Every field has a default, so the smallest useful setup is one line:
 *
 * ```php
 * new Setup(basePath: __DIR__);
 * ```
 *
 * which loads `config/rockadmin/`, opens the database the configuration
 * names, keeps the session in PHP's own, and writes files where
 * `storage.path` says.
 */
final class Setup
{
    /**
     * @param (Closure(): ?\PDO)|null                          $pdo        the host's own connection, borrowed
     * @param (Closure(string): ?string)|null                  $env        how `{{env.*}}` is read
     * @param (Closure(Services): array<string, \RockAdmin\Data\Operation>)|null $operations the project's own write logic
     * @param (Closure(Services): array<string, \RockAdmin\Http\Handler>)|null $handlers routes the host serves itself
     * @param list<\RockAdmin\View\MenuItemView>|null          $menu       the navigation, when not configured
     */
    public function __construct(
        /** The project root. Every relative path in the configuration is resolved against it. */
        public readonly string $basePath,
        /** Where `rockadmin.php` and `pages/` live. Defaults to `config/rockadmin` under the base path. */
        public readonly ?string $configPath = null,
        /**
         * Where the admin is mounted, as a URL path.
         *
         * `/admin` under Laravel, `/` for an installation that owns its
         * domain. It is not read from the request, because the request only
         * knows the URL somebody asked for and not which part of it was the
         * mount point.
         */
        public readonly string $baseUrl = '/',
        /**
         * The database, borrowed rather than opened again.
         *
         * One connection, one place holding the password, and transactions
         * that do not diverge when the project writes alongside the admin.
         * Without it the `database.*` keys are used, and without those the
         * admin runs with no database and says so on every page that needs
         * one.
         */
        public readonly ?Closure $pdo = null,
        public readonly ?Closure $env = null,
        /**
         * Where the session lives.
         *
         * The default is PHP's own. A host with its own session — a framework
         * that has already started one, or a store in Redis — passes it here,
         * and the CSRF token, the flash messages, the signed-in user and the
         * file-link secret all follow it without anything else changing.
         */
        public readonly ?SessionStore $session = null,
        /**
         * Who may sign in.
         *
         * The default authenticates against `ra_users`, which is the point of
         * that table existing. A host that wants its own staff table, LDAP or
         * single sign-on replaces this one object.
         */
        public readonly ?Authenticator $authenticator = null,
        /** A disk abstraction the project already has, in place of the configured driver. */
        public readonly ?Storage $storage = null,
        /** What `mail.driver = 'callback'` means: the project sends the message itself. */
        public readonly ?Closure $mailCallback = null,
        public readonly ?Closure $operations = null,
        /**
         * Handlers registered after the SDK's own, so a host can replace one.
         *
         * The dashboard is the one most projects will want: register a handler
         * against `dashboard` and the built-in page is gone, with no template
         * override and no fork.
         */
        public readonly ?Closure $handlers = null,
        public readonly ?array $menu = null,
        /**
         * The caller's address, for the login throttle.
         *
         * The host's to decide, because only the host knows whether it sits
         * behind a proxy whose forwarded header it trusts. The default is
         * `REMOTE_ADDR`, which trusts nothing.
         */
        public readonly ?string $clientIp = null,
    ) {
    }

    public function configDirectory(): string
    {
        return $this->configPath ?? rtrim($this->basePath, '/\\') . '/config/rockadmin';
    }
}
