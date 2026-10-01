<?php

declare(strict_types=1);

namespace RockAdmin\Http;

use LogicException;
use RockAdmin\Auth\AuditLog;
use RockAdmin\Auth\AuthUser;
use RockAdmin\Auth\RequestScope;
use RockAdmin\Db\Connection;
use RockAdmin\Db\DbException;
use RockAdmin\Db\NoSuchRowException;
use RockAdmin\Db\RowReader;
use RockAdmin\Db\WriteHandler;
use RockAdmin\Db\WriteResult;
use RockAdmin\Form\DefaultValue;
use RockAdmin\Form\FieldValidator;
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
    ) {
    }

    public function handle(Route $route, Request $request): Response
    {
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

        return $this->delete($page, $returnTo, $key);
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
        // `Placeholder` reaching a write is refused rather than ignored --
        // because a missing collaborator turning into an unscoped UPDATE is
        // the exact failure this subsystem exists to prevent.
        return $this->scope?->forPage($page) ?? $page->scope;
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
        $scope = $this->scopeFor($page);

        try {
            $this->connection->transaction(function () use ($page, $action, $keys, $scope, &$applied, &$skipped): void {
                foreach ($keys as $key) {
                    $refusal = $this->bulkRefusal($page, $action, $key);

                    if ($refusal !== null) {
                        $skipped[] = $refusal;

                        continue;
                    }

                    try {
                        $write = $action->isDelete()
                            ? $this->writes->delete($page->entity, $key, $scope)
                            : $this->writes->update($page->entity, $key, $action->set, $scope);
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
            return $this->delete($page, $returnTo, $key);
        }

        try {
            $write = $this->writes->update($page->entity, $key, $action->set, $this->scopeFor($page));
        } catch (NoSuchRowException $e) {
            throw new NotFoundException($e->getMessage(), previous: $e);
        } catch (DbException $e) {
            if ($e->sqlStateClass() !== '23') {
                throw $e;
            }

            $this->flashes->danger($this->integrityMessage($e, 'saved'));

            return Response::redirect($returnTo);
        }

        $this->record($action->key, $page, $write);

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
    private function delete(PageDefinition $page, string $returnTo, string $key): Response
    {
        try {
            $write = $this->writes->delete($page->entity, $key, $this->scopeFor($page));
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

        $this->record('delete', $page, $write);

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

        $submission = Submission::fromBody($request->body, $form);
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

        try {
            $write = $isCreate
                ? $this->writes->insert($page->entity, $values)
                : $this->writes->update($page->entity, (string) $key, $values, $this->scopeFor($page));
        } catch (NoSuchRowException $e) {
            // The row went away while this form was open. Redrawing the form
            // would offer to save into nothing; the honest answer is that
            // what was being edited no longer exists.
            throw new NotFoundException($e->getMessage(), previous: $e);
        } catch (DbException $e) {
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

        $this->record($isCreate ? 'create' : 'update', $page, $write);

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
    ): Response {
        $view = $this->region->reject($page, $region, $submission, $errors, $key, $returnTo);

        return $this->forms->document($page, $view, 422);
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
