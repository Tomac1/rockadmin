<?php

declare(strict_types=1);

namespace RockAdmin\Http;

use RockAdmin\Auth\AdminUsers;
use RockAdmin\Auth\Identity;
use RockAdmin\Auth\Roles;
use RockAdmin\Auth\SqlAuthenticator;
use RockAdmin\Auth\Workspaces;
use RockAdmin\Db\DbException;
use RockAdmin\View\Assets;
use RockAdmin\View\Renderer;
use RockAdmin\View\SetupView;
use RockAdmin\View\ShellView;

/**
 * `GET|POST /_setup` — the first administrator, on an installation that has
 * none.
 *
 * Specification 11.2 makes this the CLI's job (`rockadmin user:create`), and
 * the CLI is a later milestone. Until it exists there has to be some way to
 * get into a freshly migrated admin that does not involve writing an Argon2id
 * hash into the database by hand, and this is it. When the CLI ships both
 * remain: a host with shell access uses the command, a host with only FTP and
 * a browser uses this.
 *
 * **It exists only while `ra_users` is empty.** The moment one account exists
 * there is somebody who can create the rest, and this becomes a 404 -- not a
 * 403. A 403 tells whoever found the URL that the installation is live and
 * somebody is already in it, which is the one fact this page would otherwise
 * be giving away for free.
 *
 * **The check is on the write, not only on the form.** Two people opening the
 * page on a fresh installation at the same time is the obvious race, and the
 * unobvious one is a form left open in a tab for an hour. Both end at the same
 * place: the POST re-asks, and the database's unique index on the address is
 * the backstop behind that.
 *
 * **The account is a superadmin with every workspace**, and the page says so
 * before it is created. It is the account that creates the others, so it needs
 * to be able to reach the page that does; and somebody who thinks they are
 * making an ordinary login and is in fact made a superadmin has not been
 * asked.
 */
final class SetupHandler implements Handler
{
    public const string EMAIL_FIELD = 'email';

    public const string NAME_FIELD = 'name';

    public const string PASSWORD_FIELD = 'password';

    public const string CONFIRM_FIELD = 'password_confirm';

    private const string GONE = 'This installation already has an administrator.';

    public function __construct(
        private readonly AdminUsers $users,
        private readonly Identity $identity,
        private readonly SqlAuthenticator $authenticator,
        private readonly Roles $roles,
        private readonly Workspaces $workspaces,
        private readonly Csrf $csrf,
        private readonly Renderer $renderer,
        private readonly Assets $assets,
        private readonly UrlGenerator $urls,
        private readonly string $brand,
        private readonly string $darkMode = 'auto',
    ) {
    }

    public function handle(Route $route, Request $request): Response
    {
        if (!$this->users->none()) {
            throw new NotFoundException(self::GONE);
        }

        if ($route->name !== 'setup.submit') {
            return $this->draw();
        }

        return $this->create($request);
    }

    private function create(Request $request): Response
    {
        $email = $this->text($request->input(self::EMAIL_FIELD));
        $name = $this->text($request->input(self::NAME_FIELD));

        if (!$this->csrf->isValid($this->token($request))) {
            return $this->draw($email, $name, 'This form was open too long and could not be checked. '
                . 'It has been redrawn; fill it in again.', 403);
        }

        $password = $this->text($request->input(self::PASSWORD_FIELD));

        if (mb_strlen($password) < PasswordResetHandler::MINIMUM_LENGTH) {
            return $this->draw($email, $name, \sprintf(
                'A password needs at least %d characters. Length is what makes one hard to guess; '
                . 'there are no other rules.',
                PasswordResetHandler::MINIMUM_LENGTH,
            ), 422);
        }

        if ($password !== $this->text($request->input(self::CONFIRM_FIELD))) {
            return $this->draw($email, $name, 'The two passwords are not the same. Type both again.', 422);
        }

        if (trim($name) === '' || !str_contains($email, '@')) {
            return $this->draw($email, $name, 'A name and an address are both needed: the address is '
                . 'what signs in, and the name is what the audit log records.', 422);
        }

        try {
            $this->users->create($email, trim($name), $password, [$this->role()], $this->workspaces->names());
        } catch (DbException) {
            // The unique index on the address, which is the backstop behind
            // the emptiness check above: between that check and this write,
            // somebody else may have done exactly the same thing.
            throw new NotFoundException(self::GONE);
        }

        // Signed in through the ordinary authenticator rather than by writing
        // the session directly: it is the one place that decides what signing
        // in means -- the session id is reissued, `last_login_at` is stamped,
        // and a deactivated account is refused. A shortcut here would be a
        // second answer to that question, and the one most likely to drift.
        $result = $this->authenticator->attempt($email, $password, '0.0.0.0');

        if ($result->succeeded() && $result->user !== null) {
            $this->identity->login($result->user);
        }

        return Response::redirect($this->urls->route('dashboard'));
    }

    /**
     * The role the first account is given: the widest one configured.
     *
     * Asked of the roles themselves rather than by looking for one called
     * `superadmin` -- roles are configuration and an installation is free to
     * call its own `owner` or `spravce`. And asked by counting what each one
     * resolves to rather than by looking for a literal `*`: `Roles` expands
     * every pattern against the catalogue of generated permissions, so by the
     * time they are here `['*']` and `['*.view']` are both plain lists and
     * neither contains a star. The widest set is the honest reading of "the
     * role that can reach everything", and it is the one an installation's
     * first account has to have -- it is the account that creates the others,
     * so it must be able to reach the page that does.
     *
     * Ties go to the first declared, and an installation with no roles at all
     * gets an empty one: an account with no role can sign in and see nothing,
     * which reads as a broken admin, so the page says that is what it is about
     * to make rather than pretending otherwise.
     */
    private function role(): string
    {
        $widest = '';
        $most = -1;

        foreach ($this->roles->names() as $name) {
            $count = \count($this->roles->permissions($name));

            if ($count > $most) {
                $widest = $name;
                $most = $count;
            }
        }

        return $widest;
    }

    private function draw(string $email = '', string $name = '', ?string $message = null, int $status = 200): Response
    {
        $role = $this->role();

        $view = new SetupView(
            shell: new ShellView($this->brand, styles: $this->assets->styles(), darkMode: $this->darkMode),
            action: $this->urls->route('setup.submit'),
            token: $this->csrf->token(),
            role: $role === '' ? 'no role at all, because none is configured' : $this->roles->label($role),
            workspaces: \count($this->workspaces->names()),
            email: $email,
            name: $name,
            message: $message,
        );

        return Response::html($this->renderer->render('page/setup', $view), $status);
    }

    /** From the body for an ordinary post, from the header for one core.js sent. */
    private function token(Request $request): ?string
    {
        $body = $request->input(Csrf::FIELD);

        return \is_string($body) ? $body : $request->header(Csrf::HEADER);
    }

    private function text(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }
}
