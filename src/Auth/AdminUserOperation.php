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
 * 5. **Nobody hands out more than they hold, and nobody reaches above
 *    themselves.** `administrators.update` is the permission to manage
 *    accounts; without rules it is also the permission to award oneself every
 *    other permission there is, and to set the password of somebody who
 *    already has them.
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
            self::INVITE => $this->invite($context, $row),  // checked inside, like the others
            default => throw new OperationRefused(\sprintf(
                "This page does not know how to '%s' an administrator.",
                $context->operation,
            )),
        };
    }

    /** @param array<string, mixed> $values */
    private function create(OperationContext $context, array $values, WriteHandler $writes): ?WriteResult
    {
        $this->refuseIfItGrantsMoreThanTheActorHolds($context, $values);

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
        $this->refuseIfTheAccountIsAboveTheActor($context);
        $this->refuseIfItLocksTheBuilding($context, $values);
        $this->refuseIfItGrantsMoreThanTheActorHolds($context, $values);

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
        $this->refuseIfTheAccountIsAboveTheActor($context);
        $this->refuseIfItLocksTheBuilding($context, ['is_active' => false]);

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
        $this->refuseIfTheAccountIsAboveTheActor($context);

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
     * Like the check above it, what the account currently holds is read from
     * the table rather than from the row this operation was handed: the row
     * carries whatever columns the page names, and a page that did not name
     * `roles` would have turned this safeguard off without saying so.
     *
     * @param array<string, mixed> $values
     */
    private function refuseIfItLocksTheBuilding(OperationContext $context, array $values): void
    {
        $widest = $this->roles->widest();

        $holds = $this->users->grantsFor((string) $context->key)['roles'] ?? [];

        if ($widest === '' || !\in_array($widest, $holds, true)) {
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

    /**
     * Refuses touching an account that holds a role the person doing it does
     * not hold.
     *
     * The other half of the same review finding, and the half with teeth. The
     * rule before this one stopped somebody *granting* a role they do not
     * have; it said nothing about whose row they were editing. So an editor
     * who may manage administrators could open a superadmin's row and set its
     * password -- which is not escalation by permission, it is simply signing
     * in as them afterwards. Deleting or deactivating one is the same shape
     * with the damage pointing the other way.
     *
     * So: you may manage accounts at or below your own level, and not above
     * it. Somebody holding the widest role is exempt for the reason they are
     * exempt everywhere here -- the rule would only ever fire on the honest.
     *
     * An account holding no roles at all is below everybody, which is the
     * right answer: it can do nothing, so managing it grants nothing.
     *
     * **Workspaces count as well as roles.** Two administrators with the same
     * role but different tenants are not peers: setting the password of
     * somebody who works in a workspace you cannot see is how you come to work
     * in it. Specification 9.3 says administrators are not tenant-scoped --
     * they are granted access to workspaces -- and this is what keeps that
     * grant from being self-service.
     *
     * **What the account holds is read from the table, not from the row this
     * operation was handed.** That row carries the columns the *page* names,
     * so a page whose form left the roles field out would have made this check
     * pass for everybody -- a rule about privileges cannot depend on a page
     * file remembering to mention them. A row that has gone away refuses too:
     * "I cannot tell" is not "it is fine".
     */
    private function refuseIfTheAccountIsAboveTheActor(OperationContext $context): void
    {
        $actor = $context->actor;

        if ($actor === null) {
            throw new OperationRefused(
                'This admin cannot tell who is making this change, so it will not let anybody change '
                . "somebody else's account. Whoever wired this handler has to pass the signed-in user.",
            );
        }

        if (\in_array($this->roles->widest(), $actor->roles, true)) {
            return;
        }

        $held = $this->users->grantsFor((string) $context->key);

        if ($held === null) {
            throw new OperationRefused(
                'That administrator is no longer there, so this admin cannot tell what they were '
                . 'allowed to do. Reload the page.',
            );
        }

        foreach ($held['roles'] as $role) {
            if (!\in_array($role, $actor->roles, true)) {
                throw new OperationRefused(\sprintf(
                    'You cannot change an account that has the %s role, because you do not have it '
                    . 'yourself -- and setting its password would mean signing in as it. Ask somebody '
                    . 'who does.',
                    $this->roles->label($role),
                ));
            }
        }

        foreach ($held['workspaces'] as $workspace) {
            if (!\in_array($workspace, $actor->workspaces, true)) {
                throw new OperationRefused(\sprintf(
                    "You cannot change an account that works in '%s', because you do not. Setting "
                    . 'its password would be a way of working there yourself.',
                    $workspace,
                ));
            }
        }
    }

    /**
     * Refuses handing out a role or a workspace the person doing it does not
     * have.
     *
     * Found by a security review of the commit before this one, and it is the
     * ordinary shape of privilege escalation: `administrators.update` is the
     * permission to manage accounts, and without this rule it is also the
     * permission to award oneself every other permission there is. An editor
     * who may edit administrators could tick `superadmin` on their own row and
     * be one -- and from there grant it to anybody, with the audit log
     * recording an edit that reads as routine.
     *
     * **Somebody holding the widest role is exempt**, because for them the
     * rule would be theatre: they can already reach everything, including this
     * page, and refusing them the tick box while leaving them the ability to
     * take it would be a check that only ever fired on the honest.
     *
     * **With no actor it refuses rather than skips.** A null actor means the
     * host wired this without an identity, and the right answer to "I cannot
     * tell who is doing this" is not to assume it is fine -- the last time
     * this project made an absent check the default, it was a fail-open
     * throttle that looked exactly like a working one.
     *
     * Workspaces are checked by the same rule and for the same reason:
     * granting somebody a workspace is granting them sight of its rows, and
     * nobody should be able to hand out more than they were given.
     *
     * @param array<string, mixed> $values
     */
    private function refuseIfItGrantsMoreThanTheActorHolds(OperationContext $context, array $values): void
    {
        $actor = $context->actor;

        if ($actor === null) {
            throw new OperationRefused(
                'This admin cannot tell who is making this change, so it will not grant anybody '
                . 'anything. Whoever wired this handler has to pass the signed-in user.',
            );
        }

        if (\in_array($this->roles->widest(), $actor->roles, true)) {
            return;
        }

        foreach ([
            'roles' => ['held' => $actor->roles, 'what' => 'role'],
            'workspaces' => ['held' => $actor->workspaces, 'what' => 'workspace'],
        ] as $column => $actors) {
            if (!\array_key_exists($column, $values)) {
                continue;
            }

            foreach ($this->listOf($values[$column]) as $granted) {
                if (!\in_array($granted, $actors['held'], true)) {
                    throw new OperationRefused(\sprintf(
                        "You cannot give somebody the %s '%s', because you do not have it yourself. "
                        . 'Ask somebody who does.',
                        $actors['what'],
                        $granted,
                    ));
                }
            }
        }
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
