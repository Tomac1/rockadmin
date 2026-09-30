<?php

declare(strict_types=1);

namespace RockAdmin\Http;

use RockAdmin\Db\DbException;
use RockAdmin\Db\WriteHandler;
use RockAdmin\Form\DefaultValue;
use RockAdmin\Form\FieldValidator;
use RockAdmin\Form\FormFields;
use RockAdmin\Form\FormRegion;
use RockAdmin\Form\Submission;
use RockAdmin\Form\ValidationError;
use RockAdmin\Page\FormDefinition;
use RockAdmin\Page\PageDefinition;
use RockAdmin\Page\PageRepository;
use RockAdmin\Page\RegionDefinition;
use RockAdmin\View\FlashBag;

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

    public function __construct(
        private readonly PageRepository $pages,
        private readonly FormRegion $region,
        private readonly FieldValidator $validator,
        private readonly WriteHandler $writes,
        private readonly FormHandler $forms,
        private readonly Csrf $csrf,
        private readonly FlashBag $flashes,
        private readonly UrlGenerator $urls,
    ) {
    }

    public function handle(Route $route, Request $request): Response
    {
        $this->requireToken($request);

        $name = $route->param('page');

        if (!$this->pages->has($name)) {
            throw new NotFoundException("Page '{$name}' not found.");
        }

        $action = $route->param('action');

        if (!\in_array($action, self::VERBS, true)) {
            throw new NotFoundException(
                "Page '{$name}' has no action '{$action}'. This route accepts "
                . implode(', ', self::VERBS) . '.',
            );
        }

        $page = $this->pages->get($name);

        // Read once and passed down. `ActionHandler` must redraw a rejection
        // from the region the submission came from -- a rejection drawn from
        // another of the page's form regions would silently change the form
        // under the person filling it in.
        $region = FormHandler::formRegionOf($page);
        $returnTo = FormHandler::returnTo($request->input(FormFields::RETURN_TO), $this->urls)
            ?? $this->urls->route('page.index', ['page' => $page->name]);

        if ($action === 'delete') {
            return $this->delete($page, $returnTo, $this->requireKey($request, $page));
        }

        return $this->save($page, $region, $request, $action, $returnTo);
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
            $this->writes->delete($page->entity, $key);
        } catch (DbException $e) {
            if ($e->sqlStateClass() !== '23') {
                throw $e;
            }

            $this->flashes->danger($this->integrityMessage($e, 'deleted'));

            return Response::redirect($returnTo);
        }

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
                : $this->writes->update($page->entity, (string) $key, $values);
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
