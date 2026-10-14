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
 * **The check is on the write, not only on the form**, and behind it is a
 * claim the database enforces. Two people opening the page on a fresh
 * installation at the same moment is the obvious race, and a form left open in
 * a tab for an hour is the unobvious one. The POST re-asks -- which closes the
 * second -- and for the first the account carries `founder`, a column a unique
 * index permits exactly one row to hold (migration 004). The index is what
 * makes "first wins" true rather than likely: the window between reading
 * "there are no administrators" and writing one is small, and on an endpoint
 * that by its nature has no authentication, small is not the same as closed.
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
            $this->users->create(
                $email,
                trim($name),
                $password,
                [$this->role()],
                $this->workspaces->names(),
                founder: true,
            );
        } catch (DbException) {
            // Where the race actually ends. The emptiness check above is a
            // read followed by a write, and between the two another request
            // can do the same thing -- two people opening this page on a fresh
            // installation, or a stranger who found the URL in the minute
            // between `migrate` and somebody filling the form in. Their
            // addresses differ, so the unique index on the address does not
            // catch it; `founder` does, and the database refuses the second
            // insert at the moment it happens.
            //
            // The loser is told what a late arrival is told: this installation
            // already has an administrator.
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
     * It is the account that creates the others, so it has to be able to reach
     * the page that does. `Roles::widest()` is where the choosing lives, and
     * why it counts rather than looking for a literal `*`.
     *
     * An installation with no roles at all gets an empty one: an account with
     * no role can sign in and see nothing, which reads as a broken admin, so
     * the page says that is what it is about to make rather than pretending
     * otherwise.
     */
    private function role(): string
    {
        return $this->roles->widest();
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
