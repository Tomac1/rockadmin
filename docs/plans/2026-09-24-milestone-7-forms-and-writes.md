# Milestone 7 — Forms and writes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a page described in configuration create, edit, copy and delete a
row — with server-side validation, one write path, and a transaction around
every change.

**Architecture:** A page's `form` block declares fields the same way its
regions declare columns, and `PageRepository` loads it through the same
pipeline. `FormRegion` turns a definition plus a row into a `FormView` the
templates render. On submit, `FieldValidator` checks what arrived against the
same definitions that drew the form — one source of truth for what a field
accepts — and on success a `WriteHandler` runs the change inside a
transaction. Everything that mutates goes through `POST /a/{page}/{action}`,
carries a CSRF token, and ends in a redirect back to where the user came from,
with a flash message waiting.

**Tech Stack:** PHP 8.4, plain PHP templates, Bootstrap 5.3 (vendored), PDO
over MySQL and PostgreSQL, PHPUnit 11, PHPStan level max.

**Spec:** [`docs/design/2026-09-21-rockadmin-design.md`](../design/2026-09-21-rockadmin-design.md) — 6.2 (a page's `form` block), 7.6 (defaults for new rows and `copy.reset`), 7.7 (writes through a `WriteHandler`, in a transaction), 8.11 (`_ret`, validated as an internal path), 8.13 (flash after a redirect), 5.1 and 5.5 (the action route and CSRF).

## Global Constraints

Copied verbatim from the spec and `CLAUDE.md`. Every task's requirements
implicitly include this section.

- **No runtime dependencies.** `composer.json` requires exactly `php`,
  `ext-pdo`, `ext-json`, `ext-mbstring`. Never add a package.
- **No framework coupling.** The core never mentions Laravel, Symfony or any
  other framework. Host integration goes through interfaces.
- **MySQL and PostgreSQL both work, from the same configuration.** Database
  differences live in `Dialect` and never surface in configuration.
- **No layer reaches two levels down.** Templates receive a prepared view
  object, never the database and never the configuration. A `*Definition` is
  configuration; an enum is a value.
- **One write path.** Inline editing, forms and bulk actions all go through the
  same `WriteHandler` and the same transaction. There must be no second way to
  change a row.
- **GET is always safe.** `/p/` and `/r/` render and never mutate. Everything
  that changes data is `POST /a/{page}/{action}`, with a CSRF token.
- **Every configuration key exists in the schema**, with type, default,
  description and example, and appears in `docs/reference/pages.md`.
  `ReferenceIsCurrentTest` fails the build otherwise.
- **The template standard**, enforced by `TemplateStandardsTest`: `ra-`
  structural and identity classes, no `<script>` with a body, no hardcoded
  URLs, every `<?=` escaped, every `href`/`src`/`action` through `$href(`.
- **PHP 8.4**, `declare(strict_types=1)`, PHPStan level max clean, coding
  standards clean. The gate is `composer run check`, and a commit with a red
  gate is never the end of a task.
- **English** for all code, comments, documentation and commit messages.
- config keys `snake_case`; classes `PascalCase`; methods and variables
  `camelCase`; CSS classes `kebab-case` with an `ra-` prefix; data attributes
  `kebab-case`.

## Decisions this plan makes

Where the specification is silent, these are choices rather than discoveries.

**A field is declared like a column and validated like one.** The `form` block
holds `fields`, keyed by column name, each with a `type` from a closed set.
The same definition draws the control and checks the submission, so a form can
never accept something it did not offer.

**Validation is a list of errors, never an exception.** A person filling in a
form gets every problem at once, against the form they submitted, with what
they typed still in the inputs. Throwing on the first failure would show them
one mistake at a time.

**The database is the second validator and the honest one.** A `NOT NULL` or a
unique index will refuse things this layer cannot know about, so a write that
fails is caught and turned into a form error rather than a 500 — using the
SQLSTATE class that milestone 6 taught `DbException` to carry. Class 23 is an
integrity violation and belongs on the form; anything else is still a fault.

**Defaults apply twice, deliberately.** Once when a create form is rendered, so
the user sees what will be saved, and again on save for fields the form did not
offer — hidden ones and ones the user may not edit — so a tampered submission
cannot drop a workspace scope. Spec 7.6 requires exactly this.

**`@now` and `@uuid` are the only tokens.** More would be a small language, and
a small language in configuration becomes a large one. Anything else is a
placeholder, which resolves through the machinery that already exists.

**Delete is a POST with a confirmation, and nothing else.** No GET, because
crawlers and prefetchers follow links; no soft-delete flag, because that is a
column a project can write itself.

**Uploads are not in this milestone.** `file` is in the specification's v1
list, but where an uploaded file goes — the document root, a private path, an
object store — is a policy this project has not yet decided, and guessing it
would bake a wrong answer into a schema key. Everything else in the field list
ships.

**The audit log is not in this milestone either.** Spec 9.4 records every write
with a value diff, and a diff needs a user to attribute it to. `WriteHandler`
is given the shape to carry one — the before and after rows are already in
hand — and milestone 5 fills it in.

## Test environment

Integration tests use the fixture tables `DatabaseTestCase::createFixtures()`
creates, and **write to them**, which nothing in this project has done before.
Every test that mutates must restore what it changed, or create its own table
and drop it, so a failing test cannot leave the fixtures wrong for the next
one. Say in each test which of the two it does.

Local development uses MySQL on localhost, database `rockadmin_test`, user
`root`, password `root`, and PostgreSQL on 5432 with user `postgres`.

## File Structure

**Created:**

```
src/Page/FieldType.php           text, textarea, number, select, multiselect,
                                 checkbox, radio, date, datetime, hidden, password
src/Page/FieldSchema.php         one field's keys
src/Page/FormSchema.php          the form block: fields, copy
src/Page/FormDefinition.php      a page's form, as the rest of the code reads it
src/Page/FieldDefinition.php     one field: key, label, type, default, rules
src/Form/DefaultValue.php        a literal, a placeholder, @now or @uuid
src/Form/Submission.php          what arrived, before it is trusted
src/Form/FieldValidator.php      definition + submission -> list of errors
src/Form/ValidationError.php     one field, one message
src/Form/FormRegion.php          builds the view, for create, edit and copy
src/Form/FormView.php            what the form templates read
src/Form/FormFieldView.php       one control, its value, its error
src/Db/WriteHandler.php          the interface a project may replace
src/Db/SqlWriteHandler.php       INSERT, UPDATE and DELETE from a definition
src/Db/WriteResult.php           what changed, for the flash and the audit log
src/Http/FormHandler.php         GET p/{page}/create, /{id}/edit, /{id}/copy
src/Http/ActionHandler.php       POST a/{page}/{action}
src/Http/ReturnAddress.php       validates and carries _ret
templates/region/form/region.php field.php errors.php actions.php
templates/region/form/field/     text.php textarea.php number.php select.php
                                 multiselect.php checkbox.php radio.php
                                 date.php datetime.php hidden.php password.php
```

**Modified:**

- `src/Db/Connection.php` — `transaction(Closure)`
- `src/Page/RegionDefinition.php` — the form it carries when it is one
- `src/Db/DbException.php` — nothing; milestone 6 already added the SQLSTATE
- `src/Page/PageSchema.php`, `PageRepository.php`, `PageDefinition.php` — the `form` block and the region lookups
- `src/Page/RegionType.php` — the `form` case
- `templates/region/list/` — a row's edit link, and the page header's create button
- `assets/js/core.js` — submitting a form through the action route
- `demo/` — a working form over the fixture ads
- `bin/reference-targets.php` — the form keys in the page reference

---

### Task 1: What a field may say

**Files:**
- Create: `src/Page/FieldType.php`, `src/Page/FieldSchema.php`, `src/Page/FormSchema.php`
- Modify: `src/Page/PageSchema.php` (the `form` block), `src/Page/RegionType.php` (the `form` case)
- Test: `tests/Unit/Page/FieldTypeTest.php`, `tests/Unit/Page/FormSchemaTest.php`

**Interfaces:**
- Consumes: `RockAdmin\Config\{Schema, SchemaKey, ValueType}`, `RockAdmin\Page\PageException`. **Read `src/Page/ColumnSchema.php` and `ColumnType.php` first** — this task is their sibling and must read as one.
- Produces:
  ```php
  namespace RockAdmin\Page;

  enum FieldType: string {
      case Text = 'text'; case Textarea = 'textarea'; case Number = 'number';
      case Select = 'select'; case Multiselect = 'multiselect';
      case Checkbox = 'checkbox'; case Radio = 'radio';
      case Date = 'date'; case Datetime = 'datetime';
      case Hidden = 'hidden'; case Password = 'password';

      public static function parse(string $value): self;   // PageException, names the nearest
      public function takesOptions(): bool;                // select, multiselect, radio
      public function isMultiple(): bool;                  // multiselect
      public function template(): string;                  // field/{type}.php
  }

  final class FieldSchema { public static function create(): Schema; }
  final class FormSchema  { public static function create(): Schema; }
  ```

**`FieldSchema`'s keys.** Each needs a description written for somebody
deciding whether to set it, and an example:

| Key | Type | Default | Notes |
|---|---|---|---|
| `type` | string | `text` | One of the eleven. |
| `label` | string | — | Defaults at load to the key, first letter upper, underscores to spaces. |
| `default` | mixed | — | A literal, a `{{placeholder}}`, `@now` or `@uuid`. Applies to new rows only. |
| `required` | bool | `false` | Empty is refused. |
| `readonly` | bool | `false` | Rendered disabled, and never read from a submission. |
| `hidden` | bool | `false` | No control; its default is still applied on save. |
| `help` | string | — | Shown under the control. |
| `placeholder` | string | — | Shown in an empty text-like control. |
| `options` | mixed | — | `select`, `multiselect` and `radio`: an `@enum:` reference or a literal map. |
| `min` | int | — | `number`: the smallest accepted value. `text`: the shortest accepted length. |
| `max` | int | — | The largest, or the longest. |
| `step` | string | — | `number`: the HTML step, e.g. `0.01`. |
| `rows` | int | — | `textarea`: how tall. |
| `pattern` | string | — | A regular expression the value must match, without delimiters. |

**`FormSchema`** declares `fields` (array, `each: FieldSchema`) and `copy`
(array with `reset`, a list of field keys that fall back to their defaults
instead of being carried over).

**`RegionType` gains `Form`.** It already refuses an unknown region type by
name; adding a case is the whole change, which is what that enum was built for.

- [ ] **Step 1: Write the failing tests**

`FieldTypeTest` mirrors `ColumnTypeTest`: every case parses, an unknown one is
refused naming the nearest, `takesOptions()` is true for exactly select,
multiselect and radio, `isMultiple()` for exactly multiselect, and
`template()` returns `field/{value}` for every case — assert that last one
over `FieldType::cases()` rather than case by case, so a new type cannot be
added without a template name.

`FormSchemaTest` asserts the shape: `fields` uses `each`, `copy.reset` is a
list, every key in both schemas carries a non-empty description, and every
non-bool key carries an example. Write it in the style of `PageSchemaTest`.

- [ ] **Step 2-6: Fail, implement, `composer run docs:reference`, gate, commit**

```bash
git commit -m "Declare what a form field may say" -- src/Page tests/Unit/Page docs/reference
```

---

### Task 2: Loading a form

**Files:**
- Create: `src/Page/FormDefinition.php`, `src/Page/FieldDefinition.php`
- Modify: `src/Page/PageRepository.php`, `src/Page/RegionDefinition.php`, `src/Page/PageDefinition.php`, `src/Http/DetailHandler.php`
- Test: `tests/Unit/Page/PageRepositoryFormTest.php`

**Interfaces:**
- Consumes: Task 1's schemas; the existing loading pipeline. **Read `PageRepository::buildRegion()` and `buildColumn()` first** — a field is built the same way a column is, and the two should read as siblings.
- Produces:
  ```php
  final class FormDefinition
  {
      /** @var array<string, FieldDefinition> */
      public readonly array $fields;
      /** @var list<string> keys that fall back to their default when copying */
      public readonly array $resetOnCopy;
      public function field(string $key): FieldDefinition;
      public function hasField(string $key): bool;
      /**
       * The fields a submission may set: neither readonly nor hidden. A
       * hidden field's value comes from its default when the row is saved,
       * never from the wire, which is what stops a tampered form dropping a
       * workspace scope.
       *
       * @return array<string, FieldDefinition>
       */
      public function editable(): array;
  }

  final class FieldDefinition
  {
      public readonly string $key;
      public readonly string $label;
      public readonly FieldType $type;
      public readonly mixed $default;        // a scalar, a Placeholder, or a DefaultValue token
      public readonly bool $required;
      public readonly bool $readonly;
      public readonly bool $hidden;
      public readonly string $help;
      public readonly string $placeholder;
      /** @var array<string, EnumOption> */
      public readonly array $options;
      public readonly ?int $min;
      public readonly ?int $max;
      public readonly ?string $step;
      public readonly ?int $rows;
      public readonly ?string $pattern;
  }
  ```
  `RegionDefinition` gains `public readonly ?FormDefinition $form` — non-null
  only for a region whose type is `form`.

  **A form is a region, not a page-level block.** Specification 6.2 writes
  `'form' => [...]` beside `regions`, but 8.5 then says a preview is "only
  shorthand" for a region plus an action and that "nothing in the core treats
  preview specially" — and milestone 6 built exactly that: a preview is a
  region and the page-level shorthand was never implemented. A form is the
  same shape for the same reason, and milestone 8 will want to open one by
  `@region:` like anything else. The shorthand that expands `'form' => [...]`
  into a region named `form` can be added later, for both, in one place.

  `PageDefinition` gains `firstFormRegion(): ?RegionDefinition`, matching the
  `firstPreviewRegion()` that `DetailHandler` already carries privately — and
  moving that one onto `PageDefinition` too, so there is one lookup rather
  than a growing set of private copies in handlers.

**What is refused at load**, in the style of the refusals already there, each
naming the page and the field:

- a type outside the eleven, naming the nearest
- `options` on a type that takes none, or a type that takes them with none
- a `pattern` that is not a valid regular expression — check it with
  `preg_match` and a `@` guard rather than trusting it at request time
- `min` greater than `max`
- a `copy.reset` entry naming a field the form does not declare
- `required` together with `readonly`, which asks for a value the form will
  never send

- [ ] **Step 1: Write the failing test**

Cover at least: a form loads with its fields keyed by name; a field's label
defaults from its key; `editable()` excludes both readonly and hidden
fields; an `@enum:` reference resolves; a placeholder default survives as
a `Placeholder` object rather than a string; `copy.reset` is read; a region that is not a
form has a null form; a page with no form region answers null from
`firstFormRegion()`; and `DetailHandler` still finds its preview after the
lookup moved; and one test per refusal above.

- [ ] **Step 2-6: Fail, implement, regenerate the reference, gate, commit**

---

### Task 3: What a new row starts with

**Files:**
- Create: `src/Form/DefaultValue.php`
- Test: `tests/Unit/Form/DefaultValueTest.php`

**Interfaces:**
- Consumes: `RockAdmin\Config\Placeholder`, `FieldDefinition`.
- Produces:
  ```php
  namespace RockAdmin\Form;

  final class DefaultValue
  {
      /**
       * Resolves a field's declared default into the value a new row starts
       * with. A Placeholder is returned untouched, for the data layer to bind.
       *
       * $now is injected rather than read from the clock so a test can pin
       * what `@now` produces; null means the current time.
       */
      public static function for(FieldDefinition $field, ?\DateTimeImmutable $now = null): mixed;
      public static function isToken(mixed $value): bool;
  }
  ```

**The tokens, and why only two.** `@now` is the current time in the format the
field's type wants — a `date` gets a date, a `datetime` gets both. `@uuid` is
a version 4 UUID, generated with `random_bytes`, because a project needs a key
before it has a row far more often than it needs anything else. Anything else
starting with `@` is refused at load, naming both tokens: a silent
pass-through would let `@nwo` reach the database as a literal string.

**A placeholder is not resolved here.** `{{workspace.site_id}}` arrives as a
`Placeholder` and leaves as one; binding it is milestone 5's business, and
turning it into text here is exactly what milestone 3 exists to prevent.

- [ ] **Step 1: Write the failing test**

```php
public function testALiteralIsItself(): void
public function testNowForADateFieldIsADateWithoutATime(): void
public function testNowForADatetimeFieldCarriesTheTime(): void
public function testNowIsTakenFromTheInjectedClockSoATestCanPinIt(): void
public function testUuidLooksLikeAVersionFourUuid(): void
public function testTwoUuidsDiffer(): void
public function testAPlaceholderSurvivesUntouched(): void
public function testAFieldWithNoDefaultHasNone(): void
public function testAnUnknownTokenIsRefusedNamingBoth(): void
public function testAStringThatMerelyStartsWithAnAtIsStillRefused(): void
```

- [ ] **Step 2-6: Fail, implement, gate, commit**

---

### Task 4: Checking what arrived

**Files:**
- Create: `src/Form/Submission.php`, `src/Form/FieldValidator.php`, `src/Form/ValidationError.php`
- Test: `tests/Unit/Form/FieldValidatorTest.php`

**Interfaces:**
- Consumes: `FormDefinition`, `FieldDefinition`, `FieldType`.
- Produces:
  ```php
  namespace RockAdmin\Form;

  final class Submission
  {
      /** @param array<array-key, mixed> $body the POST body, untrusted */
      public static function fromBody(array $body, FormDefinition $form): self;

      /** @return array<string, mixed> only keys the form declares as editable */
      public function values(): array;
      public function value(string $field): mixed;
      public function has(string $field): bool;
  }

  final class ValidationError
  {
      public readonly string $field;
      public readonly string $message;
  }

  final class FieldValidator
  {
      /** @return list<ValidationError> empty when everything passed */
      public function validate(FormDefinition $form, Submission $submission): array;

      /** The coerced values, safe to hand a WriteHandler. Call after validate(). */
      public function values(FormDefinition $form, Submission $submission): array;
  }
  ```

**What `Submission` refuses to carry.** A key the form does not declare, a key
declared `readonly`, and a key declared `hidden` — all three are dropped
silently, because they arrive from a form anybody can edit in their browser,
and a hidden field's value comes from its default on save, not from the wire.
This is the same discard rule as the grid's, and for the same reason.

**What `FieldValidator` checks**, per field, collecting every failure:

- `required` and the value is empty — for a `checkbox`, absent means false and
  is not empty; for everything else, an empty string is empty and `'0'` is not
- `number`: numeric, and within `min`/`max` when declared
- `text`, `textarea`, `password`: length within `min`/`max` when declared
- `pattern`: matches. A pattern that is syntactically valid can still be
  catastrophic — `(a+)+$` against a long non-matching string backtracks for
  ever — and the load-time check cannot see that, because it runs the pattern
  against an empty string and returns instantly whatever the behaviour. So
  treat `preg_match()` returning `false` as a validation failure naming the
  field, rather than letting it propagate: PCRE has already given up at its
  backtrack limit, and the honest answer to the person filling in the form is
  that the value could not be checked.
- `select`, `radio`: the value is one of the declared options
- `multiselect`: a list, every entry one of the declared options
- `date`, `datetime`: parses as the format the field renders, refusing a value
  that merely parses — the same rule and the same trap milestone 6's
  `CellFormatter` was corrected for twice
- `checkbox`: coerces present-to-true and absent-to-false, and nothing else

**Every message names the field's label, not its key.** The person reading it
sees "Price must be a number", not "price_cents must be a number".

- [ ] **Step 1: Write the failing test**

One test per rule above, plus:

```php
public function testEveryFailureIsReportedNotJustTheFirst(): void
public function testAKeyTheFormDoesNotDeclareIsDropped(): void
public function testAReadonlyFieldCannotBeSetFromTheWire(): void
public function testAHiddenFieldCannotBeSetFromTheWire(): void
public function testZeroIsNotEmpty(): void
public function testAnUncheckedCheckboxIsFalseNotMissing(): void
public function testAMessageNamesTheLabelRatherThanTheKey(): void
public function testValuesAreCoercedToTheTypeTheFieldDeclared(): void
```

`testEveryFailureIsReportedNotJustTheFirst` is the one that matters: submit a
form with four different problems and assert four errors come back. An
implementation that returns early passes every other test in this file.

- [ ] **Step 2-6: Fail, implement, gate, commit**

---

### Task 5: Writing

The one place a row changes. Everything else in this milestone exists to
decide what to hand it.

**Files:**
- Create: `src/Db/WriteHandler.php`, `src/Db/SqlWriteHandler.php`, `src/Db/WriteResult.php`
- Modify: `src/Db/Connection.php` — `transaction()`
- Test: `tests/Unit/Db/SqlWriteHandlerTest.php`, `tests/Integration/Db/WriteTest.php`

**Interfaces:**
- Consumes: `Connection`, `Sql`, `Dialect`, `DbException`, `Entity`, `Filter`, `FilterOperator`. **Read `src/Db/QueryBuilder.php` first** — this builds statements the same way and must quote identifiers through the same `Dialect`.
- Produces:
  ```php
  namespace RockAdmin\Db;

  interface WriteHandler
  {
      /** @param array<string, mixed> $values */
      public function insert(Entity $entity, array $values): WriteResult;
      /** @param array<string, mixed> $values */
      public function update(Entity $entity, string $key, array $values): WriteResult;
      public function delete(Entity $entity, string $key): WriteResult;
  }

  final class WriteResult
  {
      public readonly string $key;          // the row's key, after an insert
      /** @var array<string, mixed> the row as it was, empty for an insert */
      public readonly array $before;
      /** @var array<string, mixed> the row as it is, empty for a delete */
      public readonly array $after;
      public function changed(): array;     // the columns that actually differ
  }

  final class SqlWriteHandler implements WriteHandler
  {
      public function __construct(private readonly Connection $connection);
  }
  ```

  `Connection::transaction(\Closure $work): mixed` begins, calls, commits, and
  rolls back on any throwable before rethrowing. It must refuse to nest —
  PDO's own nesting is a lie on both servers — and say so rather than
  silently joining the outer one.

**Why `WriteResult` carries the row before and after.** The flash message wants
to name what changed, and spec 9.4's audit log wants a value diff. Both need
the same two arrays, and fetching them twice would be two reads per write. The
audit log itself is milestone 5's, because a diff needs a user to attribute it
to; this is the shape it will read.

**An update reads before it writes**, inside the same transaction, so `before`
is the row the change actually applied to rather than whatever it was when the
form was drawn. That is also what makes `changed()` honest: a form that
submits every field unchanged must produce an empty diff, not a full one.

**Integrity errors belong to the caller.** A `NOT NULL`, a unique index, a
foreign key — the database knows things the form definition cannot. Let
`DbException` propagate with its SQLSTATE; Task 8 turns class 23 into a form
error and anything else into a fault. Do not catch here.

- [ ] **Step 1: Write the failing tests**

Unit tests build against a fake connection and assert the SQL and its
bindings: an insert names only the columns given; an update sets only those
columns and filters by the key; a delete filters by the key; identifiers are
quoted through the dialect; a value that is a `Placeholder` is bound, never
interpolated.

`tests/Integration/Db/WriteTest.php` runs against both drivers and is the
first thing in this project to mutate the fixtures. **It creates its own
table and drops it in `tearDown()`** rather than writing to the shared ones —
say so in a comment, because the next person will copy this file.

```php
public function testAnInsertReturnsTheNewRowsKey(): void
public function testAnInsertedRowIsReadBackWithTheValuesGiven(): void
public function testAnUpdateChangesOnlyTheColumnsGiven(): void
public function testAnUpdateReportsWhatActuallyChanged(): void
public function testAnUpdateThatChangesNothingReportsNothing(): void
public function testADeleteRemovesTheRow(): void
public function testADeleteReturnsTheRowThatWasThere(): void
public function testATransactionRollsBackEverythingWhenTheWorkThrows(): void
public function testANestedTransactionIsRefusedRatherThanSilentlyJoined(): void
public function testAUniqueViolationPropagatesWithItsSqlState(): void
```

`testATransactionRollsBackEverythingWhenTheWorkThrows` matters most: insert
two rows and throw between them, then assert neither is there.

- [ ] **Step 2-6: Fail, implement, verify on both drivers, gate, commit**

---

### Task 6: The form region

**Files:**
- Create: `src/Form/FormRegion.php`, `src/Form/FormView.php`, `src/Form/FormFieldView.php`
- Test: `tests/Unit/Form/FormRegionTest.php`

**Interfaces:**
- Consumes: `FormDefinition`, `FieldDefinition`, `DefaultValue`, `RowSource`, `UrlGenerator`, `Csrf`, `RockAdmin\View\Classes`. **Read `src/Grid/PreviewRegion.php`** — fetching one row is a solved problem here and must not be solved a second way. Note it takes no `QueryFactory`: that class builds a query from a `GridState`, and a form has no state. Milestone 6 shipped that dependency, found it dead, and removed it; do not reintroduce it.
- Produces:
  ```php
  namespace RockAdmin\Form;

  final class FormRegion
  {
      public function __construct(
          private readonly RowSource $rows,
          private readonly UrlGenerator $urls,
          private readonly Csrf $csrf,
      );

      /** A create form: every field at its default. */
      public function create(PageDefinition $page): FormView;
      /** An edit form: null when no row has that key, so the caller 404s. */
      public function edit(PageDefinition $page, string $id): ?FormView;
      /** A copy form: the row's values, except the fields copy.reset names. */
      public function copy(PageDefinition $page, string $id): ?FormView;
      /**
       * Redrawing a rejected submission, with what was typed and why it
       * failed. $id is null for a create, the row's key for an edit.
       *
       * @param list<ValidationError> $errors
       */
      public function reject(PageDefinition $page, Submission $submission, array $errors, ?string $id): FormView;
  }
  ```
  `FormView` carries the page name, a title, the action URL, the CSRF token,
  the return address, whether this is a create or an edit, a
  `list<FormFieldView>` and a `list<string>` of form-level errors.
  `FormFieldView` carries the key, label, type, the value as it should appear
  in the control, the options, the help text, its error message or null, and
  its `ra-` classes.

**Redrawing a rejection is the same view, not a different one.** `reject()`
produces exactly what `create()` or `edit()` produces, with the submitted
values in place of the stored ones and the errors attached. A second code path
would eventually disagree with the first about what a control looks like.

**A hidden field has no control but still travels.** It is rendered as
`<input type="hidden">` only when its value comes from the row — never when it
comes from a default, because the default is reapplied on save and putting it
in the page invites tampering with it.

- [ ] **Step 1: Write the failing test**

```php
public function testACreateFormStartsEveryFieldAtItsDefault(): void
public function testAnEditFormStartsEveryFieldAtTheRowsValue(): void
public function testAnEditFormForAMissingRowIsNull(): void
public function testACopyFormCarriesTheRowExceptTheFieldsResetNames(): void
public function testARejectedSubmissionKeepsWhatWasTyped(): void
public function testARejectedSubmissionAttachesEachErrorToItsField(): void
public function testARejectedCreateAndAFreshCreateDrawTheSameControls(): void
public function testTheFormCarriesACsrfToken(): void
public function testTheActionUrlIsThePagesActionRoute(): void
public function testAReadonlyFieldIsRenderedDisabled(): void
public function testNoViewObjectCarriesAFieldDefinition(): void
public function testOneStatementFetchesTheRowForAnEditForm(): void
```

- [ ] **Step 2-6: Fail, implement, gate, commit**

---

### Task 7: The form templates

**Files:**
- Create: `templates/region/form/region.php`, `field.php`, `errors.php`, `actions.php`
- Create: `templates/region/form/field/{text,textarea,number,select,multiselect,checkbox,radio,date,datetime,hidden,password}.php`
- Modify: `assets/css/rockadmin.css`
- Test: `tests/Unit/Form/FormTemplatesTest.php`

**Interfaces:**
- Consumes: `FormView`, `FormFieldView`. **Read `templates/region/list/` and `templates/region/preview/` first** for the house style, including the `@var` docblock with full closure signatures that PHPStan at level max requires.

**The control template is chosen by the field's type**, in one place, the way
`CellPartial` does it for cells — and the filename is the type, so a project
restyling every date input knows which file to copy. That is spec 6.3's rule
applied to the other half of the admin.

**A form works without JavaScript.** It is an ordinary `<form method="post">`
to the action route, carrying its CSRF token and its return address as hidden
inputs. `core.js` may later submit it in the background; nothing depends on
that.

**An error is shown twice**: against the field, where the person is looking,
and once at the top, so a long form does not hide a failure below the fold.
The top summary links to each field.

**Every control is labelled**, with `for` pointing at a real `id`, and carries
`aria-invalid` and `aria-describedby` when it has an error. Milestone 6
shipped a range filter whose label pointed at nothing; do not repeat it.

- [ ] **Step 1: Write the failing test**

Render the real templates over hand-built views. Cover every field type, a
field with an error, a form with three errors, a required field's marker, a
readonly field, a hidden field, a select with options, a multiselect with two
selected, a checkbox checked and unchecked, and a value containing `<script>`
and quotes. Assert structure and escaping, never whitespace.

Check `TemplateStandardsTest`'s vacuity guard still asserts a count at or
below the real number and raise it.

- [ ] **Step 2-6: Fail, implement, gate, commit**

---

### Task 8: Serving it

**Files:**
- Create: `src/Http/FormHandler.php`, `src/Http/ActionHandler.php`, `src/Http/ReturnAddress.php`
- Modify: `templates/region/list/` (an edit link per row, a create button in the header), `assets/js/core.js`
- Test: `tests/Unit/Http/FormHandlerTest.php`, `tests/Unit/Http/ActionHandlerTest.php`, `tests/Unit/Http/ReturnAddressTest.php`, `tests/Integration/Form/WriteFlowTest.php`

**Interfaces:**
- Consumes: everything above, plus `Handler`, `Request`, `Response`, `Route`, `NotFoundException`, `ForbiddenException`, `Csrf`, `FlashBag`. **Read `src/Http/PageHandler.php` and `DetailHandler.php`** — these sit beside them.
- Produces: `FormHandler` (routes `page.create`, `page.edit`, `page.copy`), `ActionHandler` (route `action`), and:
  ```php
  namespace RockAdmin\Http;

  final class ReturnAddress
  {
      /** Null when the value is absent or not an internal admin path. */
      public static function from(mixed $raw): ?self;
      public function path(): string;
      public function url(UrlGenerator $urls): string;
  }
  ```

**`_ret` is the open-redirect surface of this milestone.** Spec 8.11: anything
with a scheme or a host is discarded. Refuse a value that is not a string, one
beginning `//`, one containing a scheme, a backslash, a null byte or a control
character, and one that does not begin with the admin's own path. Test each.
When it is missing or refused, fall back to the page's index — never to
whatever was sent.

**The action route's shape.** `POST /a/{page}/{action}` with `action` one of
`create`, `update` or `delete`. Every one of them:

1. checks the CSRF token, and answers 403 without it — not 302, because a
   redirect on a failed CSRF check hides the failure
2. loads the page, 404 if it names nothing
3. builds a `Submission`, validates it, and on failure **re-renders the form**
   with the errors and what was typed, at 422, rather than redirecting — a
   redirect would lose the submission
4. on success runs the write in a transaction, catches a `DbException` of
   SQLSTATE class 23 and turns it into a form-level error at 422, and lets
   anything else propagate
5. flashes what happened and redirects to the return address, or to the page

**A delete confirms in the browser and re-checks on the server.** The link
carries `data-ra-confirm`; the handler does not trust that it did.

- [ ] **Step 1: Write the failing tests**

`ReturnAddressTest` is a small adversarial suite on its own: `/admin/p/ads`,
`p/ads`, `//evil.com`, `https://evil.com`, `/\evil.com`, `javascript:alert(1)`,
`/admin/p/ads%0d%0aSet-Cookie:x`, an array, an integer, `''`, a 10,000-character
string, and `/admin/../etc/passwd`. Say for each what comes back.

`WriteFlowTest` runs the whole thing against both drivers: create a row
through the action route and read it back; edit it; submit something invalid
and assert a 422 carrying the typed value; delete it; and assert a failed
write leaves nothing behind. It creates and drops its own table.

- [ ] **Step 2-6: Fail, implement, verify on both drivers, gate, commit**

---

### Task 9: Looking at it

**Files:**
- Modify: `demo/config/pages/ads.php`, `demo/index.php`, `demo/README.md`, `README.md`
- Test: `tests/Integration/Form/DemoFormTest.php`

**What to build.** The demo's ads page gets a form over the fixture table
using every field type it honestly can — text, textarea, number, select from
an enum, checkbox, date — plus a hidden field with a `@now` default, and a
`copy.reset` listing the field that should not be carried over. Wire the three
form routes and the action route in `demo/index.php`.

**Then drive it in a browser** and report what you measured, not what you
expect:

- create a row: the form draws, the defaults are visible, saving redirects to
  the grid and the new row is in it, with a toast
- edit it: the form opens with the stored values, saving returns to the grid
  page you came from, filters intact
- submit something invalid: the page comes back with the value still typed,
  the error against the field and in the summary, and no row written
- copy it: the reset field is at its default, everything else carried
- delete it: confirm, the row is gone, a toast says so
- with JavaScript disabled, every one of those still works
- light and dark both read properly, contrast measured

Milestone 4 shipped an invisible toast and milestone 6 shipped a grid with no
links, both because nobody opened the page. This is where that is caught.

- [ ] **Step 1-6: Build, browser-check, gate, commit**

---

## Milestone acceptance

1. `composer run check` is green with both databases exported.
2. `composer show --tree` lists no runtime dependency beyond PHP extensions.
3. A row is created, edited, copied and deleted from configuration alone, with
   no PHP written outside it.
4. Every write runs inside a transaction, and a failure leaves nothing behind.
5. An invalid submission comes back with every error at once and what was
   typed still in the inputs.
6. `_ret` cannot be made to leave the admin, proved by an adversarial test.
7. A missing or wrong CSRF token is a 403.
8. Everything works with JavaScript disabled.
9. Every new configuration key is in `docs/reference/pages.md`.

## What this milestone deliberately leaves out

- **File uploads.** Where an uploaded file goes is a policy this project has
  not decided, and a schema key guessing it would be hard to take back.
- **The audit log.** Spec 9.4 needs a user to attribute a diff to;
  `WriteResult` carries the shape it will read.
- **Permissions.** Milestone 5. Every handler here has one obvious place to
  add the check, and none of them pretends to.
- **Actions** beyond create, update and delete — spec 8.5's `link`, `open` and
  `post`, the `actions` column type, bulk actions, and the header's action
  buttons. Milestone 8.
- **Inline cell editing**, reserved for v1.1 by spec 8.12, which this
  milestone's single write path is what makes possible.

## Amendments made during execution

Recorded as they were decided, so the plan and the code do not disagree.

**A field's `pattern` is anchored, and this changes behaviour for anyone
upgrading.** It was compiled unanchored, which made it a substring match: the
schema's own example `[A-Z]{2}\d{4}` accepted
`'; DROP TABLE users; -- AB1234`. It now compiles as `~^(?:…)$~D`. The
`(?:…)` matters because a top-level alternation would otherwise anchor only
its first branch, and the `D` matters because `$` admits a trailing newline
without it. **Release note:** a project whose pattern relied on substring
matching — `[A-Z]{2}` meaning "contains two capitals" — starts refusing
submissions on upgrade. This is the right direction, but it must be announced
rather than discovered. The HTML `pattern` attribute this mirrors is
implicitly anchored by every browser, so the old behaviour made the
server-side check strictly weaker than the client-side hint.

**Validation has one entry point.** `validate()` returns a `ValidationResult`
carrying both the errors and the coerced values, and asking it for values
while any error stands raises. The previous shape was two calls with a
docblock saying "call after validate()", which returned a value the
configuration never offered when a caller got the order wrong. There were no
callers outside `src/Form/` at the time, so this was the cheapest it would
ever be to change.

**`required` on a checkbox means "must be ticked".** The plan originally said
an unchecked one passes, which makes the flag do nothing on the one type where
"you must accept the terms" is the commonest reason to set it.

**An insert may let the database assign the key.** The plan assumed a key is
always known before the write, which `@uuid` supports but autoincrement and
identity columns do not — and every table in the first real project this runs
against uses a generated key. The server difference lives in `Dialect`:
`lastInsertId()` on MySQL, `INSERT … RETURNING` on PostgreSQL, whose
`lastInsertId()` needs a sequence name and is fragile.

**`transaction()` joins a transaction it opened itself.** Nesting was refused
outright, which is right for a transaction RockAdmin knows nothing about but
forbids the atomic bulk action rule 7 requires. It is now depth-counted with
no savepoints: the outermost call commits, inner calls neither commit nor roll
back, and a foreign transaction is still refused.

**Values are bound by inferred type.** `PDOStatement::execute(array)` binds
everything as a string and PHP stringifies `false` to `''`, so the one write
path could not store a `false` — which is what every unchecked checkbox
produces. This was invisible locally because the development MySQL runs
without `STRICT_TRANS_TABLES`, silently coercing `''` to `0`; the test suite
now sets a strict `sql_mode` per session so it tests the world it deploys
into.

**A field key may not begin with an underscore.** Those names are reserved for
the body keys a form carries but an entity does not — `_csrf`, `_id`, `_ret`,
named in `RockAdmin\Form\FormFields`. Without the refusal, a field named `_id`
would render a control colliding with the hidden input that says which row to
write, so whichever the browser sent last would win.

**The form block lives inside a region, not beside `regions`.** Specification
6.2 writes it at page level, but 8.5 calls a preview "only shorthand" for a
region, and milestone 6 built exactly that. A form is the same shape for the
same reason, and milestone 8 will want to open one by `@region:`.

**A return address is a parameter of every `FormRegion` method.** It was left
to Task 8, which would have made the milestone's own acceptance criterion —
saving returns to the grid page you came from, filters intact — unreachable,
because `_ret` is lost the moment somebody enters the form.

**A return address is validated in two places, because one of them cannot see
the mount point.** The brief's `ReturnAddress::from(mixed): ?self` has no
access to a `UrlGenerator`, so it cannot answer "does this begin with the
admin's own path". It therefore answers everything that is context-free — a
string, absolute, not `//`, no scheme, no backslash, no control character, no
percent-escape decoding to one, no dot segment, at most 2048 bytes — and
`url(UrlGenerator)` asks the remaining question at the moment the answer is
needed, falling back to the admin's own root. It reads the mount point as
`$urls->to('')` rather than through a new accessor, so the one piece of
knowledge about where the admin lives stays in the one class that has it.

**A delete's integrity failure is a flash and a redirect, not a 422.** Steps 3
and 4 of the action route's shape both say "redraw the form", and a delete has
no form and no submission: a 422 carrying a form the person was not filling in
would be a fiction, and redrawing the edit form for the row that would not
delete puts them back on a page whose Save button is not what they wanted. The
honest answer is the message on the grid they were going back to. The 422 shape
still holds for create and update.

**A default is reapplied on save for a create, not for an update.** Spec 7.6
says a default applies again on save "for fields the form did not offer", which
is what stops a tampered submission dropping a workspace scope. Doing the same
on an update would overwrite a row's own `created_at` with `@now` every time
somebody fixed a typo. The scope-dropping attack it exists to stop is an insert
of a row into somebody else's workspace; an update cannot move a row it was
already allowed to address.

**The grid's create button and edit links are `ListView`/`RowView` data, and
`Grid` now imports `Form\FormFields`.** A template has no `UrlGenerator` and
must not build a URL, and the return address these links carry is the grid's
current filters, sort and page — which only `ListRegion` knows. So `ListView`
gained `createUrl` (null when the page declares no form) and `RowView` gained
`editUrl`, and the actions column exists exactly when
`ListView::hasRowActions()` says so, because a `<th>` and a `<td>` that decided
it separately would disagree. Writing `_ret` through `FormFields::RETURN_TO`
rather than as a literal adds a `Grid` → `Form` edge to the dependency note
below; `FormFields` is static leaf vocabulary and moves with the rest of it.

**Delete is a submit button with `formaction`, and `FormView` gained
`deleteAction`.** A delete needs the token, the row's key and the return
address, all of which the edit form already carries, and HTML forbids a nested
`<form>`. `formaction` on a submit button redirects that one submission to
`POST /a/{page}/delete` with no script and no duplicated inputs;
`formnovalidate` is what lets a row with an empty required field still be
deleted. It carries `data-ra-confirm`, which `core.js` binds in the capture
phase; the handler looks for no confirmation flag at all, because an attribute
in an editable document is not evidence that anybody agreed.

### Carried forward, deliberately not done here

**`Page` must not depend on `Form`.** The two now import each other:
`PageRepository` reaches for `DefaultValue::isToken()` and
`FormFields::reserved()`, while `Form` reads `FieldDefinition` and `FieldType`.
A configuration loader has no business reaching into the runtime that consumes
its output, and a cycle is what makes a layer diagram stop explaining
anything. Both things `Page` needs are static leaf vocabulary and move
cheaply. Left until the milestone's agents are out of those files; do it
before milestone 8 adds callers.
