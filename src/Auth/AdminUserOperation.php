<?php

declare(strict_types=1);

namespace RockAdmin\Auth;

use RockAdmin\Data\Operation;
use RockAdmin\Data\OperationContext;
use RockAdmin\Data\OperationRefused;
use RockAdmin\Db\WriteHandler;
use RockAdmin\Db\WriteResult;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\Mail\Mailbox;
use RockAdmin\Mail\Mailer;
use RockAdmin\Mail\MessageFactory;

/**
 * Administrators, managed from inside the admin.
 *
 * The page itself is ordinary configuration over `ra_users` -- a grid, a form,
 * an action -- and this is the part a page file cannot say. Milestone 11 built
 * the seam for a project's own write logic; this is the SDK using it for its
 * own table, which is the best evidence that the seam is wide enough to be
 * worth having.
 *
 * Four things it does, each of which a plain form save would get wrong:
 *
 * 1. **The password is hashed, and an empty box changes nothing.** A form
 *    redrawn for an edit cannot carry the existing password back (that would
 *    put a hash in the response body), so the box is always empty -- and a
 *    save that wrote it through would clear the password of everybody edited
 *    for any other reason.
 * 2. **`roles` and `workspaces` are JSON columns.** A multiselect produces a
 *    list and `SqlWriteHandler` binds scalars; encoding them is this
 *    operation's job and deliberately not a general cast in the data layer
 *    (see the milestone plan).
 * 3. **The last account that can reach everything cannot be removed.** Not by
 *    deactivating it, not by taking its role away, not by deleting it, and not
 *    by the person doing it to themselves. An installation that locks itself
 *    out has no way back that does not involve a database client -- `_setup`
 *    does not come back, because there are still administrators.
 * 4. **An account created without a password is invited.** The mail goes out
 *    after the commit, because a transaction can take back a row and cannot
 *    take back an e-mail.
 */
final class AdminUserOperation implements Operation
{
    /** The verb a page declares to send somebody their link again. */
    public const string INVITE = 'invite';

    public function __construct(
        private readonly AdminUsers $users,
        private readonly Roles $roles,
        private readonly PasswordResets $resets,
        private readonly MessageFactory $messages,
        private readonly Mailer $mailer,
        private readonly UrlGenerator $urls,
        /** From `public_url`; empty means no link can be built and none is sent. */
        private readonly string $publicUrl = '',
        /** From `auth.invite_lifetime_minutes`. */
        private readonly int $inviteLifetimeMinutes = 2880,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $values
     */
    public function perform(
        OperationContext $context,
        array $row,
        array $values,
        WriteHandler $writes,
    ): ?WriteResult {
        return match ($context->operation) {
            'create' => $this->create($context, $values, $writes),
            'update' => $this->update($context, $row, $values, $writes),
            'delete' => $this->delete($context, $row, $writes),
            self::INVITE => $this->invite($context, $row),
            default => throw new OperationRefused(\sprintf(
                "This page does not know how to '%s' an administrator.",
                $context->operation,
            )),
        };
    }

    /** @param array<string, mixed> $values */
    private function create(OperationContext $context, array $values, WriteHandler $writes): ?WriteResult
    {
        $password = $this->plainPassword($values);
        $email = $this->address($values);

        $write = $context->writeDefault($writes, $this->columns($values, $password));

        if ($password === null) {
            // No password was typed, so the account cannot be signed into
            // until somebody sets one. The link goes out once the row is
            // real.
            $this->inviteAfterCommit($context, $email, $this->name($values));
        }

        return $write;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $values
     */
    private function update(
        OperationContext $context,
        array $row,
        array $values,
        WriteHandler $writes,
    ): ?WriteResult {
        $this->refuseIfItLocksTheBuilding($context, $row, $values);

        $password = $this->plainPassword($values);
        $columns = $this->columns($values, $password);

        if ($password === null) {
            // The box was left empty, which on an edit means "leave it alone"
            // and not "clear it". It is the only way an edit form can behave:
            // it cannot show what is there, so it cannot ask to keep it.
            unset($columns['password']);
        }

        return $context->writeDefault($writes, $columns);
    }

    /** @param array<string, mixed> $row */
    private function delete(OperationContext $context, array $row, WriteHandler $writes): ?WriteResult
    {
        $this->refuseIfItLocksTheBuilding($context, $row, ['is_active' => false]);

        if ((string) $context->key === ($context->actor->id ?? '')) {
            throw new OperationRefused(
                'You cannot delete the account you are signed in with. Ask another administrator to '
                . 'do it, which also means somebody notices that it happened.',
            );
        }

        return $context->writeDefault($writes, []);
    }

    /** @param array<string, mixed> $row */
    private function invite(OperationContext $context, array $row): null
    {
        $email = $this->text($row['email'] ?? null);

        if ($email === '') {
            throw new OperationRefused('That administrator has no address to send an invitation to.');
        }

        $this->inviteAfterCommit($context, $email, $this->text($row['name'] ?? null));

        // Nothing was written, and nothing should be recorded as written. The
        // invitation is a side effect; the row is exactly as it was.
        return null;
    }

    /**
     * Refuses a change that would leave nobody able to reach everything.
     *
     * The check is "would anybody else still hold the widest role", asked of
     * the other rows rather than of this one -- so it catches all three ways
     * of doing it: deactivating the account, taking the role off it, and
     * deleting it. A page cannot express this, because it is a rule about the
     * table rather than about the row.
     *
     * @param array<string, mixed> $row
     * @param array<string, mixed> $values
     */
    private function refuseIfItLocksTheBuilding(OperationContext $context, array $row, array $values): void
    {
        $widest = $this->roles->widest();

        if ($widest === '' || !\in_array($widest, $this->rolesOf($row['roles'] ?? null), true)) {
            // This account does not hold the widest role, so nothing it does
            // can be the last one losing it.
            return;
        }

        $keepsRole = !\array_key_exists('roles', $values)
            || \in_array($widest, $this->listOf($values['roles'] ?? null), true);
        $staysActive = !\array_key_exists('is_active', $values) || $this->truthy($values['is_active']);

        if ($keepsRole && $staysActive) {
            return;
        }

        if ($this->users->othersHolding($widest, (string) $context->key) > 0) {
            return;
        }

        throw new OperationRefused(\sprintf(
            'This is the last active account with the %s role, so this would leave nobody able to '
            . 'manage the admin -- including nobody able to undo it. Give somebody else that role '
            . 'first.',
            $this->roles->label($widest),
        ));
    }

    private function inviteAfterCommit(OperationContext $context, string $email, string $name): void
    {
        if ($email === '' || $this->publicUrl === '') {
            // No address, or no way to build a link anybody could follow. The
            // account is still created; what is missing is said by the page
            // that sent them here, not by a mail to nowhere.
            return;
        }

        $context->afterCommit(function () use ($email, $name): void {
            $token = $this->resets->issue($email);

            $this->mailer->send($this->messages->invitation(
                new Mailbox($email, $name),
                rtrim($this->publicUrl, '/')
                    . $this->urls->route('password.reset.form', ['token' => $token]),
                $this->inviteLifetimeMinutes,
            ));
        });
    }

    /**
     * The values as columns: hashed password, JSON for the two list columns.
     *
     * @param  array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function columns(array $values, ?string $password): array
    {
        $columns = $values;

        $columns['password'] = $password === null ? null : password_hash($password, PASSWORD_ARGON2ID);

        foreach (['roles', 'workspaces'] as $column) {
            if (\array_key_exists($column, $values)) {
                $columns[$column] = json_encode($this->listOf($values[$column]), JSON_THROW_ON_ERROR);
            }
        }

        if (\array_key_exists('email', $values)) {
            // Folded here as everywhere else, so that the unique index in
            // migration 002 and the lookup in `SqlAuthenticator` agree with
            // what this page writes.
            $columns['email'] = mb_substr(mb_strtolower(trim($this->address($values))), 0, 255);
        }

        return $columns;
    }

    /** @param array<string, mixed> $values */
    private function plainPassword(array $values): ?string
    {
        $password = $this->text($values['password'] ?? null);

        return $password === '' ? null : $password;
    }

    /** @param array<string, mixed> $values */
    private function address(array $values): string
    {
        return $this->text($values['email'] ?? null);
    }

    /** @param array<string, mixed> $values */
    private function name(array $values): string
    {
        return $this->text($values['name'] ?? null);
    }

    /**
     * A stored JSON column read back as a list of role names.
     *
     * @return list<string>
     */
    private function rolesOf(mixed $stored): array
    {
        if (\is_string($stored)) {
            $decoded = json_decode($stored, true);

            return $this->listOf($decoded);
        }

        return $this->listOf($stored);
    }

    /** @return list<string> */
    private function listOf(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $names = [];

        foreach ($value as $item) {
            if (\is_scalar($item)) {
                $names[] = (string) $item;
            }
        }

        return $names;
    }

    private function truthy(mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        return \in_array($value, [1, '1', 't', 'true', 'on'], true);
    }

    private function text(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }
}
