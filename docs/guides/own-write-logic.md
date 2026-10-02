# Your own write logic

RockAdmin's default write is an INSERT, an UPDATE or a DELETE through
`WriteHandler`, inside a transaction, with an audit row after it. For most
pages that is the whole of what saving means.

When it is not — the ad that has to re-index when it is published, the invoice
that must not be deleted once it is sent, the archive that notifies a seller —
you replace **the logic of the write**, not the request around it. The thing
you write is an `Operation`.

This is not a controller. The CSRF check, the permission, the `when`
conditions re-checked against the row as it actually is, the workspace scope,
the transaction, the switch into the row's workspace, the audit row, the flash
and the redirect all still happen, in that order, whether or not you supply an
operation. Specification rule 7 says there is exactly one write path; this
hands you the inside of it rather than a way around it.

## The three steps

**1. Name it in the page file.**

```php
// config/pages/ads.php
'actions' => [
    'archive' => [
        'type' => 'post',
        'to' => '@action:archive',
        'label' => 'Archive',
        'confirm' => 'Archive this ad?',
        'permission' => 'ads.update',
        'when' => ['state' => 'active'],
        'handler' => '@handler:archive_ad',   // instead of a `set`
    ],
],
'regions' => [
    'form' => [
        'type' => 'form',
        'form' => [
            'handler' => '@handler:save_ad',  // create, update and delete
            'fields' => [/* … */],
        ],
    ],
],
```

`handler` is a `@handler:` reference, the same shape as `@page:`, `@action:`,
`@region:` and `@enum:`. An action carries either a `handler` or a `set`, never
both: only one of them could run and the file would not say which, so both are
refused together.

It is written in the page file, and not only in your wiring, on purpose. A file
reading `'handler' => '@handler:save_ad'` is one somebody can open and know
that saving this page is not the default. The argument for describing an admin
in configuration is that the configuration is what you read to know what
happens.

**2. Write the class.** One method, four arguments, and your own constructor —
it is your object, built by you, with your mailer and your repositories in it.

```php
use RockAdmin\Data\Operation;
use RockAdmin\Data\OperationContext;
use RockAdmin\Data\OperationRefused;
use RockAdmin\Db\WriteHandler;
use RockAdmin\Db\WriteResult;

final class SaveAd implements Operation
{
    public function __construct(
        private readonly Mailer $mailer,
        private readonly SearchIndex $index,
    ) {
    }

    public function perform(
        OperationContext $context,
        array $row,              // the columns about to be written, as they are now
        array $values,           // what the form validated, or what the action's `set` named
        WriteHandler $writes,
    ): ?WriteResult {
        if ($context->operation === 'delete' && ($row['state'] ?? null) === 'sold') {
            throw new OperationRefused(
                'A sold ad cannot be deleted, because the sale has to stay auditable. '
                . 'Archive it instead.',
            );
        }

        // The write RockAdmin would have done, plus whatever you want changed.
        $write = $context->writeDefault($writes, [...$values, 'indexed_at' => null]);

        // After the commit. A transaction can take back a row and cannot take
        // back an e-mail.
        $context->afterCommit(function () use ($context, $values): void {
            $this->index->queue((string) $context->key);
            $this->mailer->adSaved($values['email'] ?? null);
        });

        return $write;
    }
}
```

What the four arguments are:

| | |
|---|---|
| `$context` | which write this is (`create`, `update`, `delete`, or your action's own verb), the row's key, the bound scope, the row's workspace, and who pressed the button |
| `$row` | the before-values of the columns being written, plus the columns your page's forms manage. Empty for a create |
| `$values` | the validated submission, or what the action's `set` named. Empty when the action carries a `handler` instead |
| `$writes` | the one way to write. Not a `Connection`: a write that does not go through here is a write with no audit row |

Three rules worth knowing:

- **Return null for "nothing worth recording".** No audit row is written for
  it, and the person is told nothing needed changing. An operation that decides
  a row needs no change should say so rather than invent a `WriteResult` the
  log would then claim was a change.
- **Raise `OperationRefused` to say no.** It rolls everything back — the
  transaction, the audit row, anything queued — and then answers where the
  person is: the form redrawn with your sentence above it, a flash for an
  action, a skipped row with your reason for it inside a bulk action. The
  message is shown verbatim, so write it for them and name nothing about the
  schema.
- **Anything else you raise is a 500**, which is the right answer to logic that
  did not expect its own input.

**3. Register it, and let the admin check itself.**

```php
$operations = Operations::of([
    'save_ad' => new SaveAd($mailer, $index),
    'archive_ad' => new ArchiveAd($mailer),
]);

// Every `@handler:` any page names is registered. Refused here, naming the
// page and where the reference is written -- not when somebody presses Save.
$operations->assertEveryHandlerExists($pages);
```

Then pass `$operations` to `ActionHandler`. The assertion is the point of the
class: a registry that answered "nothing" at click time would turn a wiring
typo into a 500 on one button, found by whoever happened to press it.

`demo/src/ArchiveAd.php` and `demo/index.php` are a working example of all
three steps, and `demo/config/pages/ads.php` is the page that names it.

## Multi-tenant hosts: the second half

If your application has its own notion of the current tenant, RockAdmin's
scope is not enough on its own, and the reason is worth being precise about:
**the scope reaches the `WHERE` of the queries RockAdmin builds, and your own
code builds its own.** A form field whose options come from your
`CategoryRepository` gets the categories of whatever *your* service currently
names. Two ideas of the current workspace, and they have to agree.

`WorkspaceContext` is how you are told, and it wraps rather than sets, because
`switchTo()` / work / `switchBack()` is three statements and the day one of them
is skipped is the day a request leaks a tenant into whatever runs next:

```php
use RockAdmin\Auth\WorkspaceContext;
use RockAdmin\Auth\WorkspaceDefinition;

final class TenantWorkspaceContext implements WorkspaceContext
{
    public function __construct(private readonly TenantService $tenants)
    {
    }

    public function runIn(?WorkspaceDefinition $workspace, Closure $work): mixed
    {
        // Null means "no particular workspace": the page is not divided by
        // workspace, or none are configured. Read it as your own default --
        // it is "whatever you would have done without this", not "none".
        if ($workspace === null) {
            return $work();
        }

        return $this->tenants->runAs((int) $workspace->var('tenant_id'), $work);
    }
}
```

Wire it alongside a `RowWorkspace`, which works out which workspace a *row* is
in:

```php
$rowWorkspace = new RowWorkspace($requestScope, $reader);
$context = new TenantWorkspaceContext($tenants);

$forms = new FormHandler(/* … */, $rowWorkspace, $context);
$actions = new ActionHandler(/* … */, $operations, $rowWorkspace, $context);
```

Every write, every form render, and every redraw of a refused submission then
happens with that workspace active, and each side effect queued with
`afterCommit()` runs in the workspace its own operation ran in — a selection of
forty rows across several tenants does not send one tenant's mail as another.

**The row's workspace is not always the active one.** Somebody holding every
workspace may work across all of them, in which case nothing is active and every
row still belongs to somebody: a superadmin editing one ad of Motobazar.cz gets
Motobazar.cz while that write runs. When a workspace *is* active, that is the
answer and nothing is read — the scope is in the `WHERE`, so a row the request
can reach is a row in that workspace.

### One address, several tenants

An admin on a single hostname cannot resolve its tenant from the request, so
the person chooses — and there are three states, not two:

| State | What `current()` answers | What it means |
|---|---|---|
| a named workspace | that workspace | somebody chose it, and it overrides whatever the address would say |
| all workspaces | `null` | the scope is omitted; offered only to somebody holding every workspace |
| automatic | whatever your resolver decides | nobody has chosen; the request decides, by host or however else |

Automatic is not the same as the workspace it currently answers: a choice
outlives the request that made it and an automatic answer does not. Because a
choice is sticky and cannot be un-chosen by choosing, `ActiveWorkspace` has
`automatic()` and the switcher offers "Automatic (Cyklobazar.cz)" as its first
entry. A host that resolves its own workspace implements `ActiveWorkspace`
instead of using `SessionWorkspace`, and the three states are what it has to
be able to express:

```php
public function current(?AuthUser $user): ?WorkspaceDefinition
{
    return match (true) {
        $this->chosen !== null => $this->workspaces->get($this->chosen),  // named
        $this->acrossAll => null,                                          // all
        default => $this->fromHost($this->request->host()),               // automatic
    };
}
```

## What this does not give you

- **A `Connection`.** The way to write is `WriteHandler`, and that is what
  makes the audit row and rule 7 true rather than advisory.
- **The gate, the CSRF check or the `when` re-check.** Those are the parts a
  project must not be able to get wrong, so they are not yours to replace.
- **A route of your own.** An operation is the inside of a write that RockAdmin
  already routes, gates and audits. A screen that is not a write is a page, and
  pages are configuration.
