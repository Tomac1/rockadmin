<?php

declare(strict_types=1);

namespace RockAdmin;

use RockAdmin\Auth\Gate;
use RockAdmin\Auth\Identity;
use RockAdmin\Auth\Workspaces;
use RockAdmin\Config\Config;
use RockAdmin\Db\Connection;
use RockAdmin\Http\Csrf;
use RockAdmin\Http\SessionStore;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\Mail\Mailer;
use RockAdmin\Page\PageRepository;
use RockAdmin\Storage\Storage;
use RockAdmin\View\Assets;
use RockAdmin\View\FlashBag;
use RockAdmin\View\Renderer;

/**
 * What the admin has built, handed to the host so it can build its own on top.
 *
 * A project's `Operation` needs the flash bag; its own handler needs the
 * renderer and the URL generator; a custom mailer wrapper needs the one that
 * was configured. None of those can be constructed before the bootstrap runs,
 * because they are made out of its results — so `Setup` takes closures that
 * receive this, rather than finished objects it could not have been given.
 *
 * It is deliberately a bag of what already exists and not an interface to
 * everything: a field added here is a promise to keep it, and anything a host
 * can reach is something this project can no longer quietly change.
 */
final class Services
{
    public function __construct(
        public readonly Config $config,
        public readonly PageRepository $pages,
        public readonly UrlGenerator $urls,
        public readonly Renderer $renderer,
        public readonly Assets $assets,
        public readonly FlashBag $flashes,
        public readonly SessionStore $session,
        public readonly Csrf $csrf,
        public readonly Identity $identity,
        public readonly Gate $gate,
        public readonly Workspaces $workspaces,
        public readonly Storage $storage,
        public readonly Mailer $mailer,
        public readonly string $brand,
        public readonly string $darkMode,
        public readonly string $publicUrl,
        /** Where the admin is mounted, as `Setup` gave it. */
        public readonly string $baseUrl,
        /** 'path' or 'query', out of `url_mode`: how an incoming path is found. */
        public readonly string $urlMode,
        /**
         * The database, or nothing.
         *
         * Null is a real state and not a half-built one: an installation that
         * has been deployed but not configured still has to answer a request,
         * and it answers it by saying so. Everything that needs rows is
         * registered only when this is not null.
         */
        public readonly ?Connection $connection = null,
    ) {
    }
}
