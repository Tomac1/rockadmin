<?php

declare(strict_types=1);

namespace RockAdmin\Http;

use Closure;
use LogicException;
use RockAdmin\Auth\AuditLog;
use RockAdmin\Auth\AuthUser;
use RockAdmin\Auth\NoWorkspaceSwitch;
use RockAdmin\Auth\RequestScope;
use RockAdmin\Auth\RowWorkspace;
use RockAdmin\Auth\WorkspaceContext;
use RockAdmin\Auth\WorkspaceDefinition;
use RockAdmin\Config\HandlerReference;
use RockAdmin\Config\Placeholder;
use RockAdmin\Data\OperationContext;
use RockAdmin\Data\OperationRefused;
use RockAdmin\Data\Operations;
use RockAdmin\Db\Connection;
use RockAdmin\Db\DbException;
use RockAdmin\Db\NoSuchRowException;
use RockAdmin\Db\RowReader;
use RockAdmin\Db\WriteHandler;
use RockAdmin\Db\WriteResult;
use RockAdmin\Form\DefaultValue;
use RockAdmin\Form\FieldValidator;
use RockAdmin\Form\FileWriteResult;
use RockAdmin\Form\FileWrites;
use RockAdmin\Form\FormFields;
use RockAdmin\Form\FormRegion;
use RockAdmin\Form\Submission;
use RockAdmin\Form\ValidationError;
use RockAdmin\Page\ActionDefinition;
use RockAdmin\Page\FormDefinition;
use RockAdmin\Page\PageDefinition;
use RockAdmin\Page\PageRepository;
use RockAdmin\Page\RegionDefinition;
use RockAdmin\View\FlashBag;
use Throwable;

/**
 * `POST /a/{page}/{action}` — the only way anything in this admin changes.
 *
 * The verbs this milestone ships are `create`, `update` and `delete`. Spec
 * 8.5's `link`, `open` and `post` actions, bulk actions and inline cell
 * editing are milestone 8's and v1.1's; all of them will arrive here, because
 * rule 7 allows exactly one write path.
 *
 * The order of the checks is the design, and every one of them answers with
 * something chosen against the obvious alternative:
 *
 * 1. the CSRF token, answered with 403 rather than a redirect — a redirect on
 *    a failed check looks like a successful save that quietly did nothing,
 *    and the person is sent away from the form that would have shown them why
 * 2. the page and the verb, answered with 404 — a POST to a page that does
 *    not exist is not "forbidden", it is addressed at nothing
 * 3. validation, answered with 422 and the form redrawn — a redirect here
 *    would lose the submission, which is the one thing the person cannot get
 *    back
 * 4. the write, whose integrity failures (SQLSTATE class 23) become form
 *    errors and whose other failures propagate as faults — the database is
 *    the second validator and knows things a form definition cannot
 * 5. a flash and a redirect to the return address, or to the page
 *
 * The class-23 catch sits *outside* `WriteHandler`'s transaction rather than
 * inside it, and that is not a stylistic choice: on PostgreSQL a failed
 * statement poisons the whole transaction until it is rolled back, where
 * MySQL carries on. Catching inside the callback would leave the connection
 * in a state where every subsequent statement fails on one server and
 * succeeds on the other, from the same code.
 */
final class ActionHandler implements Handler
{
    /**
     * The `$field` of a `ValidationError` that belongs to the form as a whole
     * rather than to any one control. A field key can never be the empty
     * string, so `FormRegion::reject()` finds no field to attach it to and
     * puts it at the top of the form, which is exactly where "the database
     * refused this" belongs.
     */
    private const string FORM_LEVEL = '';

    /** @var list<string> the verbs this route accepts */
    private const array VERBS = ['create', 'update', 'delete'];

    /**
     * How many skipped rows a flash message names before it stops.
     *
     * Naming them is the point — "3 were skipped" says something is wrong and
     * not which rows, and a skipped row is precisely one that needs attention
     * of its own. But a selection can be a whole page, so there is a limit,
     * and past it the message says how many more there were.
     */
    private const int SKIPPED_NAMED = 5;

    public function __construct(
        private readonly PageRepository $pages,
        private readonly FormRegion $region,
        private readonly FieldValidator $validator,
        private readonly WriteHandler $writes,
        /**
         * Reading the row an action is about to change, which happens only
         * when the action carries `when` conditions to re-check. The write
         * itself goes through `WriteHandler` as every write does; this never
         * writes anything.
         */
        private readonly RowReader $reader,
        /**
         * The workspace boundary this request writes inside.
         *
         * Every write below passes it. Specification 9.3 puts the scope on
         * update and delete as well as on reads, for the reason it gives
         * about filters: a boundary that can be edited out of a request is
         * not a boundary, and `_id` in a posted body is exactly that kind of
         * edit.
         *
         * Optional only because a great many tests build this handler to
         * exercise one write and have no workspaces at all; `NoScope` is the
         * admin that never had any, and it answers `[]`.
         */
        private readonly ?RequestScope $scope,
        /**
         * The seam milestone 7 recorded and declined to open.
         *
         * A bulk action wraps its loop in one `Connection::transaction()`, so
         * forty rows succeed or none do. It is depth-counted and joins
         * whatever transaction the caller already has, which is why this does
         * not fight a host that wraps the whole request in one.
         *
         * Optional, because everything but a bulk action works without it and
         * a great many tests build this handler to exercise one write. A bulk
         * request reaching a handler with no connection fails loudly rather
         * than quietly applying forty rows one at a time.
         */
        private readonly ?Connection $connection,
        private readonly FormHandler $forms,
        private readonly Csrf $csrf,
        private readonly FlashBag $flashes,
        private readonly UrlGenerator $urls,
        private readonly ?AuditLog $audit = null,
        private readonly ?AuthUser $actor = null,
        /**
         * The project's own write logic, by the name a page file gave it.
         *
         * Optional, because an admin whose pages name none never needs it and
         * a great many tests build this handler to exercise one write. A page
         * that does name one and reaches a handler built without this fails
         * loudly rather than quietly doing the default -- which would be the
         * project's logic silently not running, the single worst way for this
         * seam to fail.
         */
        private readonly ?Operations $operations = null,
        /**
         * Which workspace the row being written is in -- *its* workspace, not
         * the one the person is looking at. Under "all workspaces" those are
         * not the same thing, and the write has to happen in the first.
         */
        private readonly ?RowWorkspace $rowWorkspace = null,
        /**
         * How the host is told, for the half of the work its own code does.
         * Optional, and null behaves as `NoWorkspaceSwitch`: an admin whose
         * host has no tenants of its own needs nothing switched.
         */
        private readonly ?WorkspaceContext $workspaces = null,
        /**
         * What becomes of the files a form carries.
         *
         * Optional, because a page with no `file` field never needs one and
         * a great many tests build this handler to exercise one write. A page
         * that does declare one and reaches a handler built without this
         * saves the row and silently drops the upload, which is why `save()`
         * refuses that combination rather than letting it happen.
         */
        private readonly ?FileWrites $files = null,
    ) {
    }

    /**
     * What operations queued for after the commit, in the order they queued
     * it.
     *
     * The one piece of state on this handler, and it is here because the
     * commit is not where the operation is: a bulk action's forty rows run
     * inside one transaction and each may queue something, so the list
     * outlives the call that filled it. It is per request, like the handler.
     *
     * @var list<Closure():void>
     */
    private array $pending = [];

    public function handle(Route $route, Request $request): Response
    {
        // Before the token, because the token is part of what went missing.
        // A POST over `post_max_size` reaches here with its whole body
        // discarded by PHP -- no fields, no files, nothing raised -- and
        // checking the token first answers "this form was open too long" to
        // somebody whose only mistake was a large photograph.
        if ($request->bodyWasDiscarded()) {
            throw new PayloadTooLargeException(
                'That upload was larger than this server accepts, so none of the form arrived. Try '
                . 'a smaller file, or ask whoever administers this installation to raise '
                . 'post_max_size and upload_max_filesize.',
            );
        }

        $this->requireToken($request);

        $name = $route->param('page');

        if (!$this->pages->has($name)) {
            throw new NotFoundException("Page '{$name}' not found.");
        }

        $page = $this->pages->get($name);
        $verb = $route->param('action');
        $performed = $this->resolve($page, $verb);

        $returnTo = FormHandler::returnTo($request->input(FormFields::RETURN_TO), $this->urls)
            ?? $this->urls->route('page.index', ['page' => $page->name]);

        if ($performed->isSubmission()) {
            // Resolved here rather than above, because only a submission has
            // one. `formRegionOf()` refuses a page with no form region, and a
            // page of a grid and some actions is an ordinary page that has
            // none -- resolving it for every verb made a delete on such a page
            // answer "no form region" when nothing had asked for a form.
            //
            // Read once and passed down. `ActionHandler` must redraw a
            // rejection from the region the submission came from; a rejection
            // drawn from another of the page's form regions would silently
            // change the form under the person filling it in.
            return $this->save($page, FormHandler::formRegionOf($page), $request, $performed->verb, $returnTo);
        }

        // A selection, if the body carries one. It is read before the single
        // key, because the two are different requests and only one of them
        // names `_id` -- a bulk form submits `_ids[]` and nothing else.
        $selection = $this->selection($request);

        if ($selection !== null) {
            return $this->performBulk($page, $performed->verb, $selection, $returnTo);
        }

        $key = $this->requireKey($request, $page);

        if ($performed->action !== null) {
            return $this->perform($performed, $performed->action, $key, $returnTo);
        }

        // The built-in delete, which has no action to carry a handler -- so
        // the page's form is asked for one. A form block's `handler` covers
        // create, update *and* delete, which is what the reference says it
        // does; without this line it covered two of the three and the
        // omission would only show up as a row that went away without the
        // project's own rule being asked.
        return $this->delete($page, $returnTo, $key, $page->writeHandler());
    }

    /**
     * The selected keys, or null when this is not a bulk request at all.
     *
     * An empty array and a missing key are different things and both arrive
     * here: a bulk form submitted with nothing ticked sends no `_ids` at all,
     * because an unchecked checkbox sends nothing. So "a bulk request with an
     * empty selection" cannot be told from "not a bulk request" by the body
     * alone — which is why the form carries a marker the browser always
     * sends, and why that marker is what this reads.
     *
     * @return list<string>|null
     */
    private function selection(Request $request): ?array
    {
        $raw = $request->input(FormFields::IDS);

        if ($raw === null) {
            return $request->input(FormFields::BULK) === null ? null : [];
        }

        if (!\is_array($raw)) {
            return [];
        }

        $keys = [];

        foreach ($raw as $value) {
            if (\is_scalar($value) && (string) $value !== '') {
                $keys[] = (string) $value;
            }
        }

        // Duplicates are dropped rather than applied twice. A selection is a
        // set of rows, and a body can repeat a key -- by a script, by a form
        // submitted twice into one request, or by somebody editing it. Two
        // audit rows for one row's one change would make the log lie about
        // what happened.
        return array_values(array_unique($keys));
    }

    /**
     * The scope every write on this page happens inside.
     *
     * One method, so the five write call sites below cannot disagree -- and
     * so that an admin with no workspaces gets `[]` from the same place
     * rather than from five separate conditions.
     *
     * @return array<string, mixed>
     */
    private function scopeFor(PageDefinition $page): array
    {
        // Unwired, the page's own scope goes through untouched rather than
        // being dropped. A literal scope still applies, and an unbound
        // `Placeholder` is refused here rather than ignored -- because a
        // missing collaborator turning into an unscoped UPDATE is the exact
        // failure this subsystem exists to prevent.
        if ($this->scope !== null) {
            return $this->scope->forPage($page);
        }

        foreach ($page->scope as $column => $value) {
            if ($value instanceof Placeholder) {
                // Said here rather than left to the driver. An INSERT hands
                // the placeholder's text to the database and comes back with
                // "incorrect integer value '{{workspace.tenant_id}}'", which
                // is loud but names nothing anybody can act on.
                throw new \LogicException(\sprintf(
                    "Page '%s' scopes '%s' to %s, and this handler was built without a RequestScope, "
                    . 'so there is nothing to bind it from. Pass one, or the write would be unscoped.',
                    $page->name,
                    (string) $column,
                    (string) $value,
                ));
            }
        }

        return $page->scope;
    }

    /**
     * Which of the three kinds of thing this POST is.
     *
     * A verb that is neither built in nor declared by one of the page's own
     * `post` actions is a 404, and the message lists both sets rather than
     * only the three literals -- a page that declares `archive` and is posted
     * `archvie` should say so, not imply that `archive` was never an option.
     */
    private function resolve(PageDefinition $page, string $verb): ActionRequest
    {
        if (\in_array($verb, self::VERBS, true)) {
            return ActionRequest::builtIn($page, $verb);
        }

        $declared = ActionRequest::declaredBy($page, $verb);

        if ($declared !== null) {
            return ActionRequest::configured($page, $declared);
        }

        $offered = self::VERBS;

        foreach ($page->actions as $action) {
            $own = $action->type->isWrite() ? $action->verb() : null;

            if ($own !== null && !\in_array($own, $offered, true)) {
                $offered[] = $own;
            }
        }

        throw new NotFoundException(
            "Page '{$page->name}' has no action '{$verb}'. This route accepts "
            . implode(', ', $offered) . '.',
        );
    }

    /**
     * A configured `post`: re-check what it is offered on, then write it.
     *
     * **`when` is re-checked here and that is the point of this method.** It
     * decides whether an action is *offered*, which is a statement about a
     * control that was drawn minutes ago on a row that may have changed since
     * -- or was never drawn at all, by a client that posted the URL anyway. A
     * condition enforced only where the button is drawn is not enforced.
     *
     * The row is read before the write for that check alone. It is the row the
     * audit log would record as `before` in any case, so this costs a read
     * that was already happening one layer down, and it is what lets the
     * refusal say which column disagreed.
     */
    /**
     * The same action, over a selection, in one transaction.
     *
     * **Atomic, and this is the decision the plan asked to have argued rather
     * than assumed.** Forty rows succeed or none do. The alternative — apply
     * what works, report what did not — leaves somebody looking at a grid
     * with no way to tell which thirty-nine of forty went through, and no way
     * to retry without re-applying the ones that did. A refusal at row forty
     * rolling everything back is a thing you can act on: fix row forty, press
     * the button again. A half-applied bulk action is not.
     *
     * It costs the other thirty-nine, and that is the real price. It is paid
     * because the failure here is almost always one of two things — a row
     * that stopped qualifying, or a foreign key — and both are specific
     * enough to name and fix. A bulk action is not a long-running import.
     *
     * **A row that does not qualify is not a failure.** `when` decides what
     * is offered, and a selection is an argument rather than an authorisation:
     * ticking forty boxes says "these ones", not "do it to these whatever
     * they are". So a row the conditions refuse is skipped, counted, and
     * reported — the transaction commits the ones that qualified. Rolling back
     * over a row that was never eligible would make "select all, archive" fail
     * on any page with one sold ad on it.
     *
     * **Every row writes its own audit row.** The log answers "what happened
     * to this row", and a row whose change is recorded only in a summary
     * cannot answer it. Forty rows, forty audit rows.
     *
     * @param list<string> $keys
     */
    private function performBulk(
        PageDefinition $page,
        string $verb,
        array $keys,
        string $returnTo,
    ): Response {
        $action = $page->bulkAction($verb);

        if ($action === null) {
            // Offered on a row and offered over a selection are two different
            // permissions, and a page says which by what it lists in
            // `bulk_actions`. A verb reachable one row at a time is not
            // reachable forty at a time unless the page said so.
            throw new NotFoundException(
                "Page '{$page->name}' offers no bulk action '{$verb}'. Add it to the page's "
                . "'bulk_actions' if a selection of rows should be able to run it.",
            );
        }

        if ($keys === []) {
            $this->flashes->danger('Nothing was selected, so nothing happened.');

            return Response::redirect($returnTo);
        }

        if ($this->connection === null) {
            throw new LogicException(
                'A bulk action applies to several rows and must be one transaction, so ActionHandler '
                . 'needs a Connection. It was built without one.',
            );
        }

        $applied = 0;
        $skipped = [];
        try {
            $this->connection->transaction(function () use ($page, $action, $keys, &$applied, &$skipped): void {
                foreach ($keys as $key) {
                    $refusal = $this->bulkRefusal($page, $action, $key);

                    if ($refusal !== null) {
                        $skipped[] = $refusal;

                        continue;
                    }

                    try {
                        $write = $this->apply(
                            $page,
                            (string) ($action->verb() ?? $action->key),
                            $key,
                            $action->set,
                            $action->handler,
                        );
                    } catch (OperationRefused $e) {
                        // The project's logic saying no to this row, which is
                        // a row that does not qualify -- the same thing
                        // `when` says, decided in code instead of in
                        // configuration. So it is skipped and reported with
                        // its own sentence, and the other thirty-nine stand.
                        $skipped[] = "{$key} ({$e->getMessage()})";

                        continue;
                    } catch (NoSuchRowException) {
                        // A row the scope excludes, or one somebody removed
                        // while this loop was running. Both are "not there"
                        // from this reader's side, and neither is a failure
                        // of the action -- so it is skipped and counted
                        // rather than rolling the other thirty-nine back.
                        //
                        // Selecting a row in another workspace is the first
                        // of those, and it must not be able to cancel a bulk
                        // action over rows the person may perfectly well
                        // touch. A selection is an argument, not an
                        // authorisation, and an argument naming something
                        // unreachable is an argument to ignore.
                        $skipped[] = $key;

                        continue;
                    }

                    if ($write === null) {
                        // The operation decided this row needed nothing. Not
                        // a failure and not an application either, so it is
                        // counted as neither -- inventing a WriteResult to
                        // make the arithmetic tidy would put a change in the
                        // audit log that never happened.
                        continue;
                    }

                    $this->record($action->key, $page, $write);
                    $applied++;
                }
            });
        } catch (NoSuchRowException $e) {
            // A row went away between the page being drawn and the button
            // being pressed. Everything rolls back, including the rows that
            // had already been written, because the selection was a set.
            $this->flashes->danger(\sprintf(
                '%s was applied to nothing: %s. No row was changed.',
                $action->label,
                $e->getMessage(),
            ));

            return Response::redirect($returnTo);
        } catch (DbException $e) {
            if ($e->sqlStateClass() !== '23') {
                throw $e;
            }

            $this->flashes->danger(\sprintf(
                '%s was applied to nothing: %s',
                $action->label,
                $this->integrityMessage($e, $action->isDelete() ? 'deleted' : 'saved'),
            ));

            return Response::redirect($returnTo);
        }

        $this->runPending();
        $this->reportBulk($action, $applied, $skipped);

        return Response::redirect($returnTo);
    }

    /**
     * Why this selected row was skipped, or null when it qualifies.
     *
     * Each key is gated and condition-tested on its own. A selection is an
     * argument, not an authorisation: forty keys in a body is forty claims
     * about which rows, and none of them is a claim about what may be done to
     * them.
     */
    private function bulkRefusal(PageDefinition $page, ActionDefinition $action, string $key): ?string
    {
        if ($action->when === []) {
            return null;
        }

        $row = $this->reader->byKey(
            $page->entity,
            $key,
            array_map(strval(...), array_keys($action->when)),
            $this->scopeFor($page),
        );

        if ($row === null) {
            return "{$key} (no longer there)";
        }

        return $this->conditionRefusal($action, $row) === null ? null : $key;
    }

    /**
     * What happened, in one sentence a person can act on.
     *
     * The skipped rows are named rather than counted, up to a point: "3 were
     * skipped" tells somebody that something is wrong and not which rows, and
     * the whole reason a row is skipped is that it needs attention of its own.
     *
     * @param list<string> $skipped
     */
    private function reportBulk(ActionDefinition $action, int $applied, array $skipped): void
    {
        if ($applied === 0) {
            $this->flashes->danger(\sprintf(
                '%s applied to no rows: none of the %d selected qualified (%s).',
                $action->label,
                \count($skipped),
                $this->listOf($skipped),
            ));

            return;
        }

        if ($skipped === []) {
            $this->flashes->success(\sprintf(
                '%s applied to %d %s.',
                $action->label,
                $applied,
                $applied === 1 ? 'row' : 'rows',
            ));

            return;
        }

        $this->flashes->warning(\sprintf(
            '%s applied to %d of %d rows. Skipped: %s.',
            $action->label,
            $applied,
            $applied + \count($skipped),
            $this->listOf($skipped),
        ));
    }

    /**
     * A readable list, truncated so one bad filter cannot write a paragraph
     * into a flash message.
     *
     * @param list<string> $items
     */
    private function listOf(array $items): string
    {
        $shown = \array_slice($items, 0, self::SKIPPED_NAMED);
        $rest = \count($items) - \count($shown);

        return implode(', ', $shown) . ($rest > 0 ? ", and {$rest} more" : '');
    }

    private function perform(
        ActionRequest $performed,
        ActionDefinition $action,
        string $key,
        string $returnTo,
    ): Response {
        $page = $performed->page;

        if ($action->when !== []) {
            $row = $this->reader->byKey(
                $page->entity,
                $key,
                array_map(strval(...), array_keys($action->when)),
                $this->scopeFor($page),
            );

            if ($row === null) {
                throw new NotFoundException(
                    "No {$page->title} with key '{$key}' to {$action->key}.",
                );
            }

            $refusal = $this->conditionRefusal($action, $row);

            if ($refusal !== null) {
                $this->flashes->danger($refusal);

                return Response::redirect($returnTo);
            }
        }

        if ($performed->isDelete()) {
            return $this->delete($page, $returnTo, $key, $action->handler);
        }

        try {
            $write = $this->apply($page, $performed->verb, $key, $action->set, $action->handler);
        } catch (OperationRefused $e) {
            $this->flashes->danger($e->getMessage());

            return Response::redirect($returnTo);
        } catch (NoSuchRowException $e) {
            throw new NotFoundException($e->getMessage(), previous: $e);
        } catch (DbException $e) {
            if ($e->sqlStateClass() !== '23') {
                throw $e;
            }

            $this->flashes->danger($this->integrityMessage($e, 'saved'));

            return Response::redirect($returnTo);
        }

        if ($write === null) {
            $this->runPending();
            $this->flashes->info("{$action->label}: nothing needed changing.");

            return Response::redirect($returnTo);
        }

        $this->record($action->key, $page, $write);
        $this->runPending();

        $this->flashes->success("{$action->label}: {$page->title} {$write->key}.");

        return Response::redirect($returnTo);
    }

    /**
     * Why this row does not qualify, or null when it does.
     *
     * Compared as text, the same way `ActionBuilder` compares them when it
     * decides whether to draw the control. A page file writes `'state' =>
     * 'active'` and a driver may hand back `1` where another hands back
     * `'1'`; an action offered by the renderer and refused by the handler on a
     * row nobody touched would be unexplainable from either side.
     *
     * @param array<string, mixed> $row
     */
    private function conditionRefusal(ActionDefinition $action, array $row): ?string
    {
        foreach ($action->when as $column => $expected) {
            $actual = $row[$column] ?? null;

            if (\is_scalar($actual) || $actual === null) {
                if ((string) $actual === (string) $expected) {
                    continue;
                }
            }

            return \sprintf(
                '%s is only offered while %s is %s, and it is %s now. The row changed since the page '
                . 'was drawn.',
                $action->label,
                $column,
                var_export($expected, true),
                \is_scalar($actual) || $actual === null ? var_export($actual, true) : get_debug_type($actual),
            );
        }

        return null;
    }

    /**
     * The token, from the body for an ordinary form post and from the header
     * for one `core.js` sent. Absent or wrong is 403, before the page name is
     * so much as looked at: a request that cannot prove where it came from
     * gets no information about what exists.
     */
    private function requireToken(Request $request): void
    {
        $body = $request->input(Csrf::FIELD);
        $token = \is_string($body) ? $body : $request->header(Csrf::HEADER);

        if (!$this->csrf->isValid($token)) {
            throw new ForbiddenException(
                'This request carried no valid CSRF token. Reload the form and try again.',
            );
        }
    }

    /**
     * The row's key, from the body rather than the URL: `POST /a/{page}/update`
     * names the page and the verb, so the key travels as `FormFields::ID`.
     *
     * A missing key is a 404 rather than a 422, because there is no form to
     * redraw and no value to keep: the request named no row at all.
     */
    private function requireKey(Request $request, PageDefinition $page): string
    {
        $raw = $request->input(FormFields::ID);

        if (!\is_scalar($raw) || (string) $raw === '') {
            throw new NotFoundException(
                "This request names no row of '{$page->name}' to write: it carried no '"
                . FormFields::ID . "'.",
            );
        }

        return (string) $raw;
    }

    /**
     * A delete: no submission, so nothing to validate and nothing to redraw.
     *
     * An integrity failure here is almost always a foreign key still pointing
     * at the row, and the honest answer is a message on the page the person
     * is going back to rather than a 422 carrying a form they were not
     * filling in. This is the one place the five-step shape above does not
     * apply, because steps 3 and 4's "redraw the form" has no form to mean.
     */
    private function delete(
        PageDefinition $page,
        string $returnTo,
        string $key,
        ?HandlerReference $handler = null,
    ): Response {
        // Read before the row goes, because afterwards there is nothing to
        // read it from -- and queued rather than deleted, because the
        // transaction can still take the row back and a file cannot be
        // un-deleted.
        $orphans = $this->filesOfRow($page, $key);

        try {
            $write = $this->apply($page, 'delete', $key, [], $handler);
        } catch (OperationRefused $e) {
            $this->flashes->danger($e->getMessage());

            return Response::redirect($returnTo);
        } catch (NoSuchRowException $e) {
            // Somebody deleted it between this page being drawn and the
            // button being pressed. That is a row that is gone, not a server
            // that broke, so it answers the way any missing row does.
            throw new NotFoundException($e->getMessage(), previous: $e);
        } catch (DbException $e) {
            if ($e->sqlStateClass() !== '23') {
                throw $e;
            }

            $this->flashes->danger($this->integrityMessage($e, 'deleted'));

            return Response::redirect($returnTo);
        }

        if ($write === null) {
            $this->runPending();
            $this->flashes->info("Nothing was deleted: {$page->title} {$key} was left as it is.");

            return Response::redirect($returnTo);
        }

        $this->forgetAfterCommit($orphans);
        $this->record('delete', $page, $write);
        $this->runPending();

        $this->flashes->success("Deleted {$page->title} {$key}.");

        return Response::redirect($returnTo);
    }

    /** A create or an update: validate, write, flash, redirect. */
    private function save(
        PageDefinition $page,
        RegionDefinition $region,
        Request $request,
        string $action,
        string $returnTo,
    ): Response {
        $form = $region->form ?? throw new NotFoundException(
            "Region '{$region->key}' of page '{$page->name}' is not a form.",
        );

        $isCreate = $action === 'create';
        $key = $isCreate ? null : $this->requireKey($request, $page);

        $submission = Submission::fromBody($request->body, $form, $request->files);
        $result = $this->validator->validate($form, $submission);

        if ($result->failed()) {
            return $this->reject($page, $region, $submission, $result->errors(), $key, $returnTo);
        }

        $values = $this->columns($result->values());

        if ($isCreate) {
            // Spec 7.6: a default applies twice, and this is the second time
            // -- for the fields the form never offered, so that a submission
            // edited in a browser cannot drop a workspace scope by leaving
            // its hidden input out. On an update it deliberately does not
            // apply: reapplying an `@now` default would overwrite the row's
            // own created_at every time somebody fixed a typo.
            $values = $this->withUnofferedDefaults($form, $values);
        }

        // The workspace a row is placed in, or kept in. Milestone 7 recorded
        // this as a hole and called it "safe until a row scope check exists";
        // it exists now.
        //
        // On a create the scope's columns are written from the active
        // workspace, *over* anything submitted. A form in a browser is
        // editable and a hidden input carrying `tenant_id` is a suggestion,
        // so the first thing anybody tries is a create into somebody else's
        // workspace.
        //
        // On an update they are stripped rather than overwritten. Moving a
        // row between workspaces is a real operation and this is not it: if
        // it is ever wanted it is an action with its own permission, not a
        // side effect of editing a title. And the `WHERE` the update already
        // carries means the row was in this workspace to begin with.
        $values = $isCreate
            ? [...$values, ...$this->scopeFor($page)]
            : array_diff_key($values, $this->scopeFor($page));

        // The files, stored before the write because a row cannot point at
        // bytes that do not exist yet. Everything that refuses the write from
        // here on hands the keys back to `discard()`, so a save that did not
        // happen leaves nothing behind.
        $files = $this->storeFiles($page, $form, $submission, $request, $key);

        $missing = $this->files?->missing($form, $files->columns, $this->storedFiles($page, $form, $key)) ?? [];

        if ($missing !== []) {
            $this->files?->discard($files->written);

            return $this->reject($page, $region, $submission, $missing, $key, $returnTo);
        }

        $values = [...$values, ...$files->columns];

        try {
            $write = $this->apply($page, $isCreate ? 'create' : 'update', $key, $values, $form->handler);
        } catch (OperationRefused $e) {
            $this->files?->discard($files->written);
            // The project's logic refusing a submission is a refused
            // submission, so it is answered the way validation is: the form
            // again, with what was typed, and the reason above it. A flash and
            // a redirect would throw the submission away over a rule the
            // person can act on.
            return $this->reject(
                $page,
                $region,
                $submission,
                [new ValidationError(self::FORM_LEVEL, $e->getMessage())],
                $key,
                $returnTo,
            );
        } catch (NoSuchRowException $e) {
            // The row went away while this form was open. Redrawing the form
            // would offer to save into nothing; the honest answer is that
            // what was being edited no longer exists.
            $this->files?->discard($files->written);

            throw new NotFoundException($e->getMessage(), previous: $e);
        } catch (DbException $e) {
            $this->files?->discard($files->written);

            if ($e->sqlStateClass() !== '23') {
                throw $e;
            }

            return $this->reject(
                $page,
                $region,
                $submission,
                [new ValidationError(self::FORM_LEVEL, $this->integrityMessage($e, 'saved'))],
                $key,
                $returnTo,
            );
        }

        if ($write === null) {
            // Nothing was written, so nothing points at what was just stored.
            $this->files?->discard($files->written);
            $this->runPending();
            $this->flashes->info("Nothing was saved: {$page->title} needed no change.");

            return Response::redirect($returnTo);
        }

        // Queued, not done: the row is written but the transaction around a
        // bulk action may still roll it back, and a file deleted for a row
        // that stayed exactly as it was is gone for good. An orphan is the
        // better failure, and the only one this ordering can produce.
        $this->forgetAfterCommit($files->superseded);

        $this->record($isCreate ? 'create' : 'update', $page, $write);
        $this->runPending();

        $this->flashes->success(
            $isCreate
                ? "Created {$page->title} {$write->key}."
                : "Saved {$page->title} {$write->key}.",
        );

        return Response::redirect($returnTo);
    }

    /**
     * The form again, with what was typed and why it was refused, at 422.
     *
     * 422 rather than 400: the body was perfectly well-formed, it just said
     * something the form does not accept. And rendered rather than
     * redirected, because a redirect discards the body and the person retypes
     * everything to see the same message.
     *
     * @param list<ValidationError> $errors
     */
    private function reject(
        PageDefinition $page,
        RegionDefinition $region,
        Submission $submission,
        array $errors,
        ?string $key,
        string $returnTo,
        /**
         * 422 for everything the form itself refused, and 413 for the one
         * case where the form never arrived: a body PHP discarded for being
         * too large. The page drawn is the same page; what differs is what a
         * proxy, a log and a browser's own tooling make of the answer.
         */
        int $status = 422,
    ): Response {
        // Inside the row's workspace, because redrawing a form is a render
        // and a render asks the project for things -- the options of a select,
        // the label of a related row. A rejected submission redrawn with
        // another tenant's categories in its dropdown would be a strange and
        // very hard bug to find.
        $response = null;

        $this->runIn($this->rowWorkspace?->forRow($page, $key), function () use (
            $page,
            $region,
            $submission,
            $errors,
            $key,
            $returnTo,
            $status,
            &$response,
        ): void {
            $view = $this->region->reject($page, $region, $submission, $errors, $key, $returnTo);
            $response = $this->forms->document($page, $view, $status);
        });

        return $response ?? throw new LogicException('Redrawing the form produced no response.');
    }

    /**
     * The write itself: the project's own logic when the page named some, and
     * what RockAdmin does when it did not.
     *
     * One method for all five paths, which is the only way the promise in
     * `Operation`'s docblock can hold. A seam that dispatched for actions and
     * wrote directly for forms would be a seam where half the admin quietly
     * ignores the project's logic -- and saving a row is the half a project is
     * most likely to have rules about.
     *
     * Everything around it is unchanged and stays RockAdmin's: this is called
     * after the CSRF check, after the gate, after `when` was re-checked
     * against the row as it actually is, with the scope bound, inside whatever
     * transaction the path opened.
     *
     * @param  array<string, mixed> $values what is to be written
     * @return ?WriteResult         null when the operation decided nothing was worth recording
     */
    private function apply(
        PageDefinition $page,
        string $operation,
        ?string $key,
        array $values,
        ?HandlerReference $handler = null,
    ): ?WriteResult {
        $workspace = $this->rowWorkspace?->forRow($page, $key, $values);

        $context = new OperationContext(
            page: $page,
            operation: $operation,
            key: $key,
            scope: $this->scopeFor($page),
            workspace: $workspace,
            actor: $this->actor,
        );

        if ($handler === null) {
            // Inside the workspace too, even though the default write needs
            // nothing from the host: a project may have a trigger, a listener
            // or a connection of its own behind `WriteHandler`, and "every
            // write happens in the row's workspace" is only a rule if it has
            // no exceptions.
            $default = null;

            $this->runIn($workspace, function () use ($context, $values, &$default): void {
                $default = $context->writeDefault($this->writes, $values);
            });

            return $default;
        }

        if ($this->operations === null) {
            throw new LogicException(\sprintf(
                "Page '%s' names %s for its %s, and this handler was built without an Operations "
                . 'registry, so there is nothing to look it up in. Pass one -- doing the default '
                . "instead would be the project's own logic silently not running.",
                $page->name,
                (string) $handler,
                $operation,
            ));
        }

        if ($this->connection === null) {
            throw new LogicException(\sprintf(
                "Page '%s' names %s for its %s, and an operation is one transaction -- it may write "
                . 'several times and all of it has to roll back together. So ActionHandler needs a '
                . 'Connection, and it was built without one.',
                $page->name,
                (string) $handler,
                $operation,
            ));
        }

        $write = null;
        $row = $this->rowBefore($page, $operation, $key, $values);
        $logic = $this->operations->get($handler);

        // One transaction around the whole operation, not around each of its
        // writes. An operation is free to write three times -- the row, a row
        // in another table, a counter -- and "raising aborts everything" is
        // only true if the three are one unit. `WriteHandler`'s own
        // transaction is depth-counted and joins this one, and so does the
        // outer transaction a bulk action already opened, so the guarantee
        // holds at one row and at forty.
        // The switch is outside the transaction, because it is not a
        // database fact: it tells the host's own code which tenant it is
        // serving, and the whole transaction belongs to one row and therefore
        // to one workspace.
        $this->runIn($workspace, function () use ($context, $logic, $row, $values, &$write): void {
            $this->connection?->transaction(function () use ($context, $logic, $row, $values, &$write): void {
                $write = $logic->perform($context, $row, $values, $this->writes);
            });
        });

        // Queued, not run. Inside a bulk action this is one row of a
        // transaction that thirty-nine later rows can still roll back, and an
        // email about a change that did not happen is the exact thing
        // `afterCommit()` exists to prevent.
        //
        // Each is wrapped in the workspace its own operation ran in. A bulk
        // action over "all workspaces" queues forty side effects belonging to
        // several tenants, and running them all in whichever was last would
        // send Motobazar.cz's mail as Autobazar.cz.
        foreach ($context->queued() as $work) {
            $this->pending[] = function () use ($workspace, $work): void {
                $this->runIn($workspace, $work);
            };
        }

        return $write;
    }

    /**
     * The row as it is now, in the columns this page knows about.
     *
     * Not every column of the table, and deliberately. A `Query` names its
     * columns, so "the whole row" would mean introspecting the table on every
     * write -- and what the page's own files describe is a better definition
     * of the row than whatever a migration happens to have left in the table.
     * A project wanting more reads it with its own code, which it has, over
     * its own tables, which it owns; what it cannot get for itself once the
     * write has happened is the before-value, and that is what this is.
     *
     * So: the columns the write names, and the columns the page's forms
     * manage. The second is what makes it useful rather than circular -- an
     * action replaced by an operation carries no `set`, because the operation
     * decides what is written, and a row consisting only of its own key would
     * answer nothing.
     *
     * Read only when there is an operation to hand it to. The default write
     * never looks at the row, and a query for nobody on every save would be
     * exactly the kind of thing rule 8 is about.
     *
     * Empty for a create, and empty for a row the scope excludes -- the write
     * that follows raises `NoSuchRowException` for that row anyway, from the
     * one place that decides it, rather than this inventing a second refusal.
     *
     * @param  array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function rowBefore(PageDefinition $page, string $operation, ?string $key, array $values): array
    {
        if ($operation === 'create' || $key === null) {
            return [];
        }

        $columns = array_map(strval(...), array_keys($values));

        foreach ($page->regions as $region) {
            foreach (array_keys($region->form->fields ?? []) as $field) {
                $columns[] = (string) $field;
            }
        }

        return $this->reader->byKey(
            $page->entity,
            $key,
            array_values(array_unique($columns)),
            $this->scopeFor($page),
        ) ?? [];
    }

    /**
     * Stores whatever the submission uploaded, or refuses the save if it
     * cannot.
     *
     * A page that declares a `file` field and reaches a handler built with no
     * `FileWrites` is a wiring mistake that would otherwise save the row and
     * drop the photograph, with nothing anywhere saying so. It fails loudly
     * instead, naming the page.
     */
    private function storeFiles(
        PageDefinition $page,
        FormDefinition $form,
        Submission $submission,
        Request $request,
        ?string $key,
    ): FileWriteResult {
        if ($this->files === null) {
            foreach ($form->fields as $field) {
                if ($field->type->isFile()) {
                    throw new LogicException(\sprintf(
                        "Page '%s' has a file field ('%s') and this handler was built without a "
                        . 'FileWrites, so there is nowhere to put what was uploaded. Pass one -- '
                        . 'saving the row without the file would look exactly like it worked.',
                        $page->name,
                        $field->key,
                    ));
                }
            }

            return new FileWriteResult();
        }

        return $this->files->apply(
            $page,
            $form,
            $submission,
            $request->body,
            $this->storedFiles($page, $form, $key),
        );
    }

    /**
     * The file columns of the row being edited, read from the database.
     *
     * Not from the form: what a replacement replaces is whatever is stored,
     * and a hidden input carrying the old key would be a browser deciding
     * which file to delete.
     *
     * @return array<string, mixed>
     */
    private function storedFiles(PageDefinition $page, FormDefinition $form, ?string $key): array
    {
        if ($key === null || $this->files === null) {
            return [];
        }

        $columns = [];

        foreach ($form->fields as $name => $field) {
            if ($field->type->isFile()) {
                $columns[] = (string) $name;
            }
        }

        if ($columns === []) {
            return [];
        }

        return $this->reader->byKey($page->entity, $key, $columns, $this->scopeFor($page)) ?? [];
    }

    /**
     * The files a row holds, across every form this page declares.
     *
     * Read before a delete, for the same reason the superseded key is read
     * before an update: the row is the only record of which files were its
     * own, and once it is gone they are bytes nobody can name.
     *
     * A page with no file field answers an empty list without asking the
     * database anything.
     *
     * @return list<string>
     */
    private function filesOfRow(PageDefinition $page, string $key): array
    {
        if ($this->files === null) {
            return [];
        }

        $columns = [];

        foreach ($page->regions as $region) {
            foreach ($region->form->fields ?? [] as $name => $field) {
                if ($field->type->isFile()) {
                    $columns[] = (string) $name;
                }
            }
        }

        if ($columns === []) {
            return [];
        }

        $row = $this->reader->byKey($page->entity, $key, array_values(array_unique($columns)), $this->scopeFor($page));
        $keys = [];

        foreach ($row ?? [] as $value) {
            if ($this->files->owns($value) && \is_string($value)) {
                $keys[] = $value;
            }
        }

        return $keys;
    }

    /**
     * Queues files for deletion once the write is real.
     *
     * The same queue an operation's `afterCommit()` uses, and for the same
     * reason -- a transaction can take back a row and cannot take back a
     * deletion. A failure here is swallowed: the save succeeded, the person
     * has been told so, and "the old photograph is still in the bucket" is
     * not a sentence that helps them.
     *
     * @param list<string> $keys
     */
    private function forgetAfterCommit(array $keys): void
    {
        if ($keys === [] || $this->files === null) {
            return;
        }

        $files = $this->files;

        $this->pending[] = static function () use ($files, $keys): void {
            $files->discard($keys);
        };
    }

    /**
     * Runs something with a workspace active, through the host if one is
     * wired.
     *
     * Null is `NoWorkspaceSwitch`, not a skipped switch: an admin whose host
     * has no workspaces of its own has nothing to tell it, and that is the
     * same answer as telling it nothing.
     *
     * @template T
     * @param  Closure():T $work
     * @return T
     */
    private function runIn(?WorkspaceDefinition $workspace, Closure $work): mixed
    {
        return ($this->workspaces ?? new NoWorkspaceSwitch())->runIn($workspace, $work);
    }

    /**
     * Everything an operation queued, now that the change is real.
     *
     * Reached only from the paths that got as far as a commit, so a rollback
     * leaves the list unrun -- which is the guarantee `afterCommit()` makes.
     * The list is emptied before anything runs, because a side effect that
     * happened twice is worse than one that did not happen at all.
     *
     * A failure here propagates, and the change stands. That is not the audit
     * log's arrangement, and the difference is whose code it is: an audit row
     * is an optional subsystem of this SDK, and swallowing its failure is a
     * decision this SDK is entitled to make. An operation's side effect is the
     * project's own logic, and deciding on its behalf that a mail that never
     * went out does not matter is not this layer's call.
     */
    private function runPending(): void
    {
        $queued = $this->pending;
        $this->pending = [];

        foreach ($queued as $work) {
            $work();
        }
    }

    /**
     * The audit row (spec 9.4), written after the write it describes and
     * never able to fail it.
     *
     * **Why here and not in `SqlWriteHandler`.** An audit row needs two facts
     * a `WriteHandler` does not have and should not be given: the acting user,
     * which lives in `src/Auth`, and the page, which lives in `src/Page`. A
     * `WriteHandler` is handed an `Entity`, and an entity names a table — two
     * pages may share one, so a table is not a page. Hooking it in the data
     * layer would mean smuggling a mutable "current user, current page" into
     * `src/Db`, which is rule 4's failure mode exactly: a layer reaching two
     * levels up for facts that belong to the request. This is the request
     * layer, it already holds both facts, and rule 7 is satisfied because
     * `ActionHandler` is the one HTTP write path — milestone 8's bulk actions
     * and inline cell editing arrive here, as the class docblock above
     * records. The trade is stated honestly in the task report: a write made
     * by something other than a request would not be recorded here.
     *
     * **After the write, not inside it.** `WriteHandler` commits before it
     * returns, so the change is durable by the time this runs. A log insert
     * inside that transaction could roll the write back with it, and 9.4 says
     * the log can be turned off — a subsystem that is optional must not be
     * able to refuse a save.
     *
     * **A failure is kept, not swallowed.** The write stands and the person
     * who made it is told that the record of it does not. There is no logger
     * in this SDK to write to instead (rule 1 forbids adding one), and
     * `error_log()` goes wherever the host's ini points, which is often
     * nowhere; the person at the keyboard is the one who can report it.
     */
    private function record(string $action, PageDefinition $page, WriteResult $write): void
    {
        if ($this->audit === null) {
            return;
        }

        try {
            $this->audit->record($this->actor, $page->name, $action, $write);
        } catch (Throwable) {
            // Throwable rather than DbException: whatever went wrong, it must
            // not undo a change that has already been committed. The message
            // says nothing about the schema, for the same reason
            // integrityMessage() does not.
            $this->flashes->danger(
                'This change was saved, but it could not be written to the audit log. The change '
                . 'stands; the record of who made it does not. Tell whoever administers this '
                . 'installation.',
            );
        }
    }

    /**
     * A message for an integrity violation, carrying the SQLSTATE and not the
     * driver's own text.
     *
     * The driver's message names tables, columns and constraint names — the
     * schema, to anybody who can reach this form. The SQLSTATE is the part
     * that means the same thing on every server and tells a developer reading
     * a bug report which class of constraint refused the write.
     */
    private function integrityMessage(DbException $e, string $verb): string
    {
        return "This could not be {$verb}: the database refused it (SQLSTATE "
            . ($e->sqlState ?? 'unknown')
            . '). Something it conflicts with is already stored, a value it needs is missing, '
            . 'or another row still refers to it.';
    }

    /**
     * The defaults of the fields a submission never carried — the hidden and
     * readonly ones, which `FormDefinition::editable()` excludes and
     * `Submission` therefore drops.
     *
     * A field with no default contributes nothing, rather than an explicit
     * `null`: writing `null` over a column the form does not manage would
     * make a form that declares one readonly field silently clear it.
     *
     * @param  array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function withUnofferedDefaults(FormDefinition $form, array $values): array
    {
        $editable = $form->editable();

        foreach ($form->fields as $key => $field) {
            if (\array_key_exists($key, $editable)) {
                continue;
            }

            $default = DefaultValue::for($field);

            if ($default === null) {
                continue;
            }

            $values[$key] = $default;
        }

        return $values;
    }

    /**
     * Validated values, keyed by column name.
     *
     * `ValidationResult` hands back `array<array-key, mixed>` because PHP
     * turns a numeric string key such as '123' into the integer 123 on the
     * way into an array and no cast undoes it. A `WriteHandler` quotes
     * identifiers, so a column named '123' is legal; this is where the key
     * type is narrowed back, once, rather than at every call site.
     *
     * @param  array<array-key, mixed> $values
     * @return array<string, mixed>
     */
    private function columns(array $values): array
    {
        $columns = [];

        foreach ($values as $column => $value) {
            $columns[(string) $column] = $value;
        }

        return $columns;
    }
}
