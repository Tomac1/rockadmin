# Milestone 4 — View layer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn prepared view objects into HTML — a plain-PHP renderer, an
overridable template cascade, escaping that cannot be forgotten, flash
messages, vendored assets served with cache headers, and the default templates
and theme that every later region renders into.

**Architecture:** Three classes do the work and none of them knows anything
about the admin's subject matter. `TemplateResolver` turns a template *name*
into a *file*, searching project paths before the SDK's own. `Renderer`
executes that file in an isolated scope holding one prepared view object and a
handful of helper closures — never the database, never the configuration.
`Escaper` is what those closures call. Above them sit small immutable view
objects (`PageView`, `ShellView`, `ButtonView`, `FlashView`) that later
milestones fill; below them sit `Assets` and the `/_assets/` handler. The
default templates in `templates/` are themselves the proof the machinery
works, and a CI test enforces the template standard on every one of them.

**Tech Stack:** PHP 8.4, plain PHP templates, Bootstrap 5.3 (vendored, no
build step), PHPUnit 11, PHPStan level max.

**Spec:** [`docs/design/2026-09-21-rockadmin-design.md`](../design/2026-09-21-rockadmin-design.md) — section 8 (8.1–8.3, 8.6–8.10, 8.13), with 8.4/8.5 read for context only; sections 12 and 14 for what this milestone must make possible later.

## Global Constraints

Copied verbatim from the spec and `CLAUDE.md`. Every task's requirements
implicitly include this section.

- **No runtime dependencies.** `composer.json` requires exactly `php`,
  `ext-pdo`, `ext-json`, `ext-mbstring`. Never add a package.
  `ComposerConstraintsTest` enforces this and must keep passing.
- **No framework coupling.** The core never mentions Laravel, Symfony or any
  other framework.
- **No layer reaches two levels down.** Templates receive a prepared view
  object, never the database, the configuration, the `Config` object, a
  `Connection`, or the `Renderer` itself.
- **Every element carries its `ra-` classes** — a structural class
  (`ra-grid-cell`) and, where it has an identity, an identity class
  (`ra-grid-cell-price`). A template without `ra-` classes violates the
  standard.
- **No `<script>` tags in templates.** Behaviour is declared with `data-ra-*`
  attributes.
- **No hardcoded URLs in templates.** Every URL comes from `UrlGenerator`,
  because the admin also runs in query-string mode.
- **Escape everything with `$e()`.** `$raw()` is the explicit, greppable
  exception.
- **PHP 8.4**, `declare(strict_types=1)` in every file, PHPStan level max
  clean, coding standards clean. The gate is `composer run check`.
- **English** for all code, comments, documentation and commit messages.
- config keys `snake_case`; classes `PascalCase`; methods and variables
  `camelCase`; CSS classes `kebab-case` with an `ra-` prefix; data attributes
  `kebab-case`.

## Decisions this plan makes

Places where the spec is silent and a choice had to be made. Each is a
decision, not a discovery — a later milestone may revisit it, but not
silently.

**A template name is not a file path.** Names are slash-separated, extension
free and relative: `region/list/table`, `ui/button`. The resolver appends
`.php` and refuses anything containing `..`, a backslash, a leading slash or a
drive letter. The spec's per-page override syntax writes the extension
(`'row' => 'ads/row.php'`), so the resolver strips a trailing `.php` from an
override value and then treats it as an ordinary name resolved through the
same cascade. An override is therefore never a way out of the cascade, and a
project cannot name a file outside its template directories.

**Templates receive one view object, not extracted variables.** `extract()`
would let a view key named `e` silently replace the escaper. A template sees
`$view` plus the fixed helper set, nothing else.

**Helpers are closures, not an object.** `$e($x)` is what the spec writes and
what the CI test greps for. They are built once per `Renderer` and shared by
every template it renders.

**The renderer catches nothing.** A throwing template cleans up its output
buffer and rethrows. Swallowing the error would produce a half-rendered page
that looks like a styling bug.

**Assets are versioned by content, not by modification time.** A deploy that
copies files changes every mtime and would bust the whole cache; a content
hash changes only what changed.

**Dark mode is a configuration key, not a user preference.** `theme.dark`
accepts `auto` (follow the operating system, the default), `on` and `off`.
A per-user toggle needs a user, which is milestone 5.

**The shell's menu is a view object this milestone renders but does not
build.** `MenuItemView` and the navbar template ship here; the code that turns
configuration and permissions into menu items belongs to milestones 5 and 11.
The demo builds two items by hand, which is enough to prove the template.

## Test environment

No database is required by any task in this milestone. Template tests render
against fixture directories the test creates under the system temporary
directory, never against `templates/` — except Tasks 7 and 8, whose subject
*is* `templates/`.

## File Structure

**Created:**

```
src/View/Escaper.php             escaping, attribute building, URL vetting
src/View/TemplateResolver.php    name -> file, cascade, overrides, resolution log
src/View/Renderer.php            executes a template in an isolated scope
src/View/ViewException.php       every failure this namespace raises
src/View/PageView.php            title, description, buttons, body classes, slots
src/View/ShellView.php           brand, menu, user, workspace, flashes, assets
src/View/MenuItemView.php        one menu entry, possibly with children
src/View/ButtonView.php          label, icon, url, style, attributes
src/View/FlashView.php           one flash message, prepared for a toast
src/View/FlashBag.php            session-backed, drained on read
src/View/Classes.php             ra- structural + identity class assembly
src/View/Assets.php              asset list, content hashes, URLs
src/View/AssetHandler.php        serves /_assets/{path...}
src/View/TemplateErrorPage.php   renders error/{status} for the error handler
src/Http/ErrorPage.php           the interface that lets Http ask for one
templates/layout/base.php        <html>, navbar, menu, shared modal, toasts
templates/layout/single.php      one slot
templates/layout/two-column.php  two slots side by side
templates/layout/sidebar-detail.php  list left, detail right
templates/page/header.php        title, description, buttons
templates/ui/button.php          badge.php, icon.php, toast.php, modal.php
templates/error/403.php          404.php, 500.php, config-error.php
assets/css/rockadmin.css         the default theme, tokens and dark mode
assets/js/core.js                delegation dispatcher and behaviour registry
assets/vendor/bootstrap/         bootstrap.min.css, bootstrap.bundle.min.js
demo/index.php                   the design surface: front controller
demo/config/rockadmin.php        its configuration
```

**Modified:**

- `src/Config/RootSchema.php` — `template_paths` (Task 2) and `theme` (Task 7)
- `src/Http/ErrorHandler.php` — take an optional `ErrorPage` and use it when
  it renders, keep the built-in fallback (Task 7)
- `README.md` — how to run the demo (Task 8)

---

### Task 1: Escaping

The one class every template depends on. It exists so that "escape everything"
is a call the CI test can grep for, not a discipline.

**Files:**
- Create: `src/View/Escaper.php`
- Create: `src/View/ViewException.php`
- Test: `tests/Unit/View/EscaperTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  ```php
  namespace RockAdmin\View;

  final class ViewException extends \RuntimeException {}

  final class Escaper
  {
      public function text(mixed $value): string;
      public function attr(mixed $value): string;
      public function url(mixed $value): string;
      public function raw(mixed $value): string;
      /** @param array<string, scalar|null> $attributes */
      public function attributes(array $attributes): string;
  }
  ```

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/View/EscaperTest.php`:

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\View\Escaper;
use RockAdmin\View\ViewException;

#[CoversClass(Escaper::class)]
final class EscaperTest extends TestCase
{
    private Escaper $escaper;

    protected function setUp(): void
    {
        $this->escaper = new Escaper();
    }

    public function testTextEscapesEveryCharacterThatEndsAnElementOrAnAttribute(): void
    {
        $this->assertSame(
            '&lt;script&gt;alert(&#039;x&#039;)&lt;/script&gt;',
            $this->escaper->text("<script>alert('x')</script>"),
        );
        $this->assertSame('a &amp;amp; b', $this->escaper->text('a &amp; b'));
        $this->assertSame('&quot;quoted&quot;', $this->escaper->text('"quoted"'));
    }

    public function testTextLeavesAccentedCharactersAlone(): void
    {
        // The admin is used in Czech. Escaping must not mangle what it does
        // not need to touch, or every label arrives as entities.
        $this->assertSame('uživatelé', $this->escaper->text('uživatelé'));
    }

    public function testTextReplacesInvalidUtf8RatherThanReturningNothing(): void
    {
        // htmlspecialchars() without ENT_SUBSTITUTE returns '' on malformed
        // input, which silently deletes a label instead of showing it broken.
        // The lone 0xC3 is replaced; the '(' after it is valid and survives.
        $this->assertSame("\u{FFFD}(", $this->escaper->text("\xC3\x28"));
    }

    /** @return array<string, array{mixed, string}> */
    public static function scalars(): array
    {
        return [
            'int' => [42, '42'],
            'float' => [1.5, '1.5'],
            'true' => [true, '1'],
            'false' => [false, ''],
            'null' => [null, ''],
            'stringable' => [new class () implements \Stringable {
                public function __toString(): string
                {
                    return '<b>';
                }
            }, '&lt;b&gt;'],
        ];
    }

    #[DataProvider('scalars')]
    public function testTextAcceptsAnythingATemplateMightHold(mixed $value, string $expected): void
    {
        $this->assertSame($expected, $this->escaper->text($value));
    }

    public function testTextRefusesAnArray(): void
    {
        // An array reaching $e() means the view object is wrong. Printing
        // "Array" would hide that until someone reads the page carefully.
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('array');

        $this->escaper->text(['a']);
    }

    public function testUrlPassesOrdinaryLinksThrough(): void
    {
        $this->assertSame('/admin/p/ads?page=2', $this->escaper->url('/admin/p/ads?page=2'));
        $this->assertSame('https://example.com/x', $this->escaper->url('https://example.com/x'));
        $this->assertSame('mailto:a@example.com', $this->escaper->url('mailto:a@example.com'));
    }

    public function testUrlEscapesWhatWouldEndTheAttribute(): void
    {
        $this->assertSame('/a?q=&quot;x&quot;&amp;y=1', $this->escaper->url('/a?q="x"&y=1'));
    }

    /** @return array<string, array{string}> */
    public static function dangerousSchemes(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'javascript cased' => ['JavaScript:alert(1)'],
            'javascript padded' => ["  javascript:alert(1)"],
            'javascript with a tab inside the scheme' => ["java\tscript:alert(1)"],
            'data' => ['data:text/html,<script>alert(1)</script>'],
            'vbscript' => ['vbscript:msgbox(1)'],
        ];
    }

    #[DataProvider('dangerousSchemes')]
    public function testUrlRefusesASchemeThatExecutes(string $url): void
    {
        $this->expectException(ViewException::class);

        $this->escaper->url($url);
    }

    public function testAttributesBuildsALeadingSpacedList(): void
    {
        $this->assertSame(
            ' id="row-42" data-ra-id="42"',
            $this->escaper->attributes(['id' => 'row-42', 'data-ra-id' => 42]),
        );
    }

    public function testAttributesEscapesValues(): void
    {
        $this->assertSame(
            ' title="Ad &quot;42&quot; &amp; co"',
            $this->escaper->attributes(['title' => 'Ad "42" & co']),
        );
    }

    public function testAttributesDropsNullAndFalseAndKeepsEmptyStrings(): void
    {
        // null and false mean "this attribute is not present"; an empty string
        // means "present and empty", which is how value="" reaches a form.
        $this->assertSame(
            ' value=""',
            $this->escaper->attributes(['disabled' => false, 'title' => null, 'value' => '']),
        );
    }

    public function testAttributesWritesTrueAsABareAttribute(): void
    {
        $this->assertSame(' disabled', $this->escaper->attributes(['disabled' => true]));
    }

    public function testAttributesReturnsNothingForAnEmptyList(): void
    {
        $this->assertSame('', $this->escaper->attributes([]));
    }

    /** @return array<string, array{string}> */
    public static function malformedAttributeNames(): array
    {
        return [
            'a space smuggles a second attribute' => ['id onclick=alert(1)'],
            'a quote ends the name' => ['id"'],
            'an angle bracket ends the tag' => ['id>'],
            'an equals sign' => ['id=x'],
            'empty' => [''],
        ];
    }

    #[DataProvider('malformedAttributeNames')]
    public function testAttributesRefusesANameThatCouldSmuggleAnotherAttribute(string $name): void
    {
        // Attribute names come from configuration, which a person writes.
        // Escaping the value is not enough if the name can carry a payload.
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('attribute name');

        $this->escaper->attributes([$name => 'x']);
    }

    public function testRawReturnsItsInputUntouched(): void
    {
        // raw() exists to be visible in a diff and greppable in CI, not to
        // transform anything.
        $this->assertSame('<b>bold</b>', $this->escaper->raw('<b>bold</b>'));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/View/EscaperTest.php`
Expected: FAIL — `RockAdmin\View\Escaper` does not exist.

- [ ] **Step 3: Write the implementation**

Create `src/View/ViewException.php`:

```php
<?php

declare(strict_types=1);

namespace RockAdmin\View;

use RuntimeException;

/**
 * Every failure the view layer raises. A template that cannot be found, a
 * value that cannot be escaped, an attribute name that cannot be written.
 */
final class ViewException extends RuntimeException
{
}
```

Create `src/View/Escaper.php`:

```php
<?php

declare(strict_types=1);

namespace RockAdmin\View;

use Stringable;

/**
 * Escaping, as the templates call it.
 *
 * Everything here is deliberately small and total: given any value a view
 * object might hold, each method either returns something safe to put in HTML
 * or throws. There is no "best effort" path, because a best effort at
 * escaping is a cross-site scripting hole that looks like it works.
 */
final class Escaper
{
    private const FLAGS = ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5;

    /**
     * A URL scheme that executes when a browser follows it. The comparison
     * strips whitespace first, because a browser ignores whitespace inside a
     * scheme and "java\tscript:" is a working link.
     */
    private const EXECUTABLE_SCHEMES = ['javascript', 'data', 'vbscript'];

    /** Escapes for element content and for quoted attribute values alike. */
    public function text(mixed $value): string
    {
        return htmlspecialchars($this->stringify($value), self::FLAGS, 'UTF-8');
    }

    /**
     * The same escaping as text(), named for where it is used. Templates read
     * better for it, and a later change to attribute handling has one place
     * to land.
     */
    public function attr(mixed $value): string
    {
        return $this->text($value);
    }

    /** Escapes a URL, refusing schemes that run code instead of navigating. */
    public function url(mixed $value): string
    {
        $url = $this->stringify($value);
        $scheme = $this->scheme($url);

        if ($scheme !== null && \in_array($scheme, self::EXECUTABLE_SCHEMES, true)) {
            throw new ViewException("Refusing to write a '{$scheme}:' URL into a link.");
        }

        return $this->text($url);
    }

    /**
     * Returns its input unchanged. It exists so that unescaped output is
     * written deliberately, shows up in a diff, and can be found by grep — the
     * CI template check fails on any `<?=` that calls neither this nor text().
     */
    public function raw(mixed $value): string
    {
        return $this->stringify($value);
    }

    /**
     * Builds an attribute list with a leading space, so a template writes
     * `<tr<?= $attrs($view->attributes) ?>>` without worrying about spacing.
     *
     * true writes a bare attribute, null and false omit it entirely, and an
     * empty string writes `attr=""`.
     *
     * @param array<string, scalar|null> $attributes
     */
    public function attributes(array $attributes): string
    {
        $out = '';

        foreach ($attributes as $name => $value) {
            if (preg_match('/^[A-Za-z_:][A-Za-z0-9_:.-]*$/', $name) !== 1) {
                throw new ViewException("Refusing '{$name}' as an attribute name.");
            }

            if ($value === null || $value === false) {
                continue;
            }

            if ($value === true) {
                $out .= ' ' . $name;
                continue;
            }

            $out .= ' ' . $name . '="' . $this->text($value) . '"';
        }

        return $out;
    }

    private function stringify(mixed $value): string
    {
        return match (true) {
            \is_string($value) => $value,
            $value === null, $value === false => '',
            $value === true => '1',
            \is_int($value), \is_float($value) => (string) $value,
            $value instanceof Stringable => (string) $value,
            \is_array($value) => throw new ViewException(
                'Cannot escape an array. A view object handed a template an array where a value belongs.',
            ),
            default => throw new ViewException(
                'Cannot escape a value of type ' . get_debug_type($value) . '.',
            ),
        };
    }

    private function scheme(string $url): ?string
    {
        $candidate = preg_replace('/\s+/', '', $url) ?? $url;
        $colon = strpos($candidate, ':');

        if ($colon === false) {
            return null;
        }

        $scheme = substr($candidate, 0, $colon);

        // A path segment may contain a colon ("/p/ads/a:b"), which is not a
        // scheme. A scheme cannot contain a slash or a question mark.
        if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*$/', $scheme) !== 1) {
            return null;
        }

        return strtolower($scheme);
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/phpunit tests/Unit/View/EscaperTest.php`
Expected: PASS.

- [ ] **Step 5: Run the full gate**

Run: `composer run check`
Expected: coding standards clean, PHPStan level max clean, all tests passing.

- [ ] **Step 6: Commit**

```bash
git add src/View tests/Unit/View
git commit -m "Make escaping something a template cannot forget"
```

---

### Task 2: The template cascade

**Files:**
- Create: `src/View/TemplateResolver.php`
- Modify: `src/Config/RootSchema.php` — add `template_paths`
- Test: `tests/Unit/View/TemplateResolverTest.php`

**Interfaces:**
- Consumes: `RockAdmin\View\ViewException` from Task 1.
- Produces:
  ```php
  namespace RockAdmin\View;

  final class TemplateResolver
  {
      /**
       * @param list<string>          $paths     project directories, highest priority first
       * @param array<string, string> $overrides template name => replacement name
       */
      public function __construct(array $paths, ?string $default = null, array $overrides = []);

      public function resolve(string $name): string;
      public function has(string $name): bool;
      public function withOverrides(array $overrides): self;
      /** @return list<array{name: string, file: string, override: ?string}> */
      public function resolutions(): array;
  }
  ```
  `$default` is the SDK's own `templates/` directory; passing null uses
  `__DIR__ . '/../../templates'`, which is what production does. It is always
  searched last.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/View/TemplateResolverTest.php`:

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\View\TemplateResolver;
use RockAdmin\View\ViewException;

#[CoversClass(TemplateResolver::class)]
final class TemplateResolverTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        // The resolver reports forward slashes, so the expectations must use
        // them too — sys_get_temp_dir() returns backslashes on Windows.
        $this->root = str_replace('\', '/', sys_get_temp_dir()) . '/ra-templates-' . bin2hex(random_bytes(6));
        $this->write('sdk/ui/button.php', 'sdk button');
        $this->write('sdk/layout/base.php', 'sdk base');
        $this->write('project/ui/button.php', 'project button');
        $this->write('theme/ui/badge.php', 'theme badge');
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    private function write(string $relative, string $contents): void
    {
        $file = $this->root . '/' . $relative;
        $directory = \dirname($file);

        if (!is_dir($directory)) {
            mkdir($directory, 0o777, true);
        }

        file_put_contents($file, $contents);
    }

    private function remove(string $path): void
    {
        if (is_file($path)) {
            unlink($path);

            return;
        }

        if (!is_dir($path)) {
            return;
        }

        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            $this->remove($path . '/' . $entry);
        }

        rmdir($path);
    }

    private function resolver(array $overrides = []): TemplateResolver
    {
        return new TemplateResolver(
            [$this->root . '/project', $this->root . '/theme'],
            $this->root . '/sdk',
            $overrides,
        );
    }

    public function testTheFirstPathThatHasTheTemplateWins(): void
    {
        $this->assertSame(
            'project button',
            file_get_contents($this->resolver()->resolve('ui/button')),
        );
    }

    public function testAPathFurtherDownIsSearchedWhenTheFirstDoesNotHaveIt(): void
    {
        $this->assertSame(
            'theme badge',
            file_get_contents($this->resolver()->resolve('ui/badge')),
        );
    }

    public function testTheSdkDefaultIsAlwaysSearchedLast(): void
    {
        $this->assertSame(
            'sdk base',
            file_get_contents($this->resolver()->resolve('layout/base')),
        );
    }

    public function testHasAnswersWithoutThrowing(): void
    {
        $resolver = $this->resolver();

        $this->assertTrue($resolver->has('ui/button'));
        $this->assertFalse($resolver->has('ui/nothing'));
    }

    public function testAMissingTemplateNamesEveryPlaceItWasLookedFor(): void
    {
        // The error a project hits most often. It has to say where to put the
        // file, not merely that one is absent.
        // Asserted by catching rather than with expectExceptionMessage(),
        // because a second call to that method replaces the first and three
        // of these four checks would silently not happen.
        try {
            $this->resolver()->resolve('ui/nothing');
            $this->fail('A missing template should throw.');
        } catch (ViewException $e) {
            $this->assertStringContainsString('ui/nothing', $e->getMessage());
            $this->assertStringContainsString($this->root . '/project', $e->getMessage());
            $this->assertStringContainsString($this->root . '/theme', $e->getMessage());
            $this->assertStringContainsString($this->root . '/sdk', $e->getMessage());
        }
    }

    public function testAnOverrideRedirectsOneNameToAnother(): void
    {
        $this->write('project/ads/button.php', 'ads button');

        $resolver = $this->resolver(['ui/button' => 'ads/button']);

        $this->assertSame('ads button', file_get_contents($resolver->resolve('ui/button')));
    }

    public function testAnOverrideMayBeWrittenWithItsExtension(): void
    {
        // The specification's per-page override syntax writes the extension:
        // 'templates' => ['row' => 'ads/row.php'].
        $this->write('project/ads/button.php', 'ads button');

        $resolver = $this->resolver(['ui/button' => 'ads/button.php']);

        $this->assertSame('ads button', file_get_contents($resolver->resolve('ui/button')));
    }

    public function testAnOverrideStillGoesThroughTheCascade(): void
    {
        // An override names a template, not a file. It cannot reach outside
        // the configured directories, and the SDK default still backs it.
        $resolver = $this->resolver(['ui/button' => 'layout/base']);

        $this->assertSame('sdk base', file_get_contents($resolver->resolve('ui/button')));
    }

    public function testAnOverrideIsNotAppliedTwice(): void
    {
        // ui/button -> ui/badge must not then follow a ui/badge override, or a
        // pair of overrides becomes a loop nobody wrote.
        $resolver = $this->resolver(['ui/button' => 'ui/badge', 'ui/badge' => 'layout/base']);

        $this->assertSame('theme badge', file_get_contents($resolver->resolve('ui/button')));
    }

    public function testWithOverridesLeavesTheOriginalAlone(): void
    {
        $this->write('project/ads/button.php', 'ads button');

        $resolver = $this->resolver();
        $scoped = $resolver->withOverrides(['ui/button' => 'ads/button']);

        $this->assertSame('ads button', file_get_contents($scoped->resolve('ui/button')));
        $this->assertSame('project button', file_get_contents($resolver->resolve('ui/button')));
    }

    /** @return array<string, array{string}> */
    public static function malformedNames(): array
    {
        return [
            'parent directory' => ['../secrets'],
            'parent directory inside' => ['ui/../../secrets'],
            'absolute' => ['/etc/passwd'],
            'windows absolute' => ['C:/windows/win.ini'],
            'backslash' => ['ui\\button'],
            'null byte' => ["ui/button\0.php"],
            'empty' => [''],
            'trailing slash' => ['ui/'],
            'double slash' => ['ui//button'],
        ];
    }

    #[DataProvider('malformedNames')]
    public function testAMalformedNameIsRefused(string $name): void
    {
        $this->expectException(ViewException::class);

        $this->resolver()->resolve($name);
    }

    public function testAMalformedOverrideValueIsRefusedToo(): void
    {
        // The override comes from configuration, so it is the more likely of
        // the two to carry something odd.
        $this->expectException(ViewException::class);

        $this->resolver(['ui/button' => '../../etc/passwd'])->resolve('ui/button');
    }

    public function testResolutionsRecordWhatCameFromWhere(): void
    {
        // The development console shows this, and it is the fastest answer to
        // "why is my override not being used".
        $resolver = $this->resolver();
        $resolver->resolve('ui/button');
        $resolver->resolve('layout/base');

        $this->assertSame(
            [
                ['name' => 'ui/button', 'file' => $this->root . '/project/ui/button.php', 'override' => null],
                ['name' => 'layout/base', 'file' => $this->root . '/sdk/layout/base.php', 'override' => null],
            ],
            $resolver->resolutions(),
        );
    }

    public function testResolutionsRecordAnOverrideThatWasApplied(): void
    {
        $this->write('project/ads/button.php', 'ads button');

        $resolver = $this->resolver(['ui/button' => 'ads/button']);
        $resolver->resolve('ui/button');

        $this->assertSame(
            [['name' => 'ui/button', 'file' => $this->root . '/project/ads/button.php', 'override' => 'ads/button']],
            $resolver->resolutions(),
        );
    }

    public function testTheSameTemplateIsRecordedOnceHoweverOftenItRenders(): void
    {
        // A grid resolves region/list/row once per row. The console wants the
        // list of templates in play, not a thousand identical lines.
        $resolver = $this->resolver();
        $resolver->resolve('ui/button');
        $resolver->resolve('ui/button');

        $this->assertCount(1, $resolver->resolutions());
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/View/TemplateResolverTest.php`
Expected: FAIL — `RockAdmin\View\TemplateResolver` does not exist.

- [ ] **Step 3: Write the implementation**

Create `src/View/TemplateResolver.php`:

```php
<?php

declare(strict_types=1);

namespace RockAdmin\View;

/**
 * Turns a template name into a file.
 *
 * A name is slash-separated, extension free and relative: 'region/list/table'.
 * The directories given to the constructor are searched in order and the SDK's
 * own templates are searched last, so a project overrides a single template by
 * putting a file with the same name in its own directory — no copying, no
 * inheritance, no registration.
 */
final class TemplateResolver
{
    /** @var list<string> project directories, highest priority first */
    private readonly array $projectPaths;

    private readonly string $default;

    /** @var list<string> everything searched, in order: project paths then the default */
    private readonly array $paths;

    /** @var array<string, string> */
    private readonly array $overrides;

    /** @var array<string, array{name: string, file: string, override: ?string}> */
    private array $resolutions = [];

    /**
     * @param list<string>          $paths     project directories, highest priority first
     * @param string|null           $default   the SDK's templates; null means this package's own
     * @param array<string, string> $overrides template name => the name to use instead
     */
    public function __construct(array $paths, ?string $default = null, array $overrides = [])
    {
        $normalize = static fn (string $path): string => rtrim(str_replace('\\', '/', $path), '/');

        $this->projectPaths = array_values(array_map($normalize, $paths));
        $this->default = $normalize($default ?? \dirname(__DIR__, 2) . '/templates');
        $this->paths = [...$this->projectPaths, $this->default];
        $this->overrides = $overrides;
    }

    /** Returns the absolute path of the file this name resolves to. */
    public function resolve(string $name): string
    {
        $requested = $this->normalize($name, 'template name');
        $override = $this->overrides[$requested] ?? null;
        $effective = $override === null ? $requested : $this->normalize($override, 'template override');

        foreach ($this->paths as $path) {
            $file = $path . '/' . $effective . '.php';

            if (is_file($file)) {
                // Keyed by name: a grid resolves the same row template once per
                // row, and the development console wants the set, not the log.
                $this->resolutions[$requested] ??= [
                    'name' => $requested,
                    'file' => $file,
                    'override' => $override === null ? null : $effective,
                ];

                return $file;
            }
        }

        $searched = implode(', ', array_map(
            static fn (string $path): string => $path . '/' . $effective . '.php',
            $this->paths,
        ));

        throw new ViewException("No template '{$requested}' in any template path. Looked in: {$searched}.");
    }

    public function has(string $name): bool
    {
        try {
            $this->resolve($name);

            return true;
        } catch (ViewException) {
            return false;
        }
    }

    /**
     * A copy carrying page-level template overrides. Overrides are per page,
     * the resolver is per request, so the page scopes rather than mutates.
     *
     * @param array<string, string> $overrides
     */
    public function withOverrides(array $overrides): self
    {
        return new self($this->projectPaths, $this->default, [...$this->overrides, ...$overrides]);
    }

    /**
     * Which template came from which file, in resolution order. The
     * development console renders this.
     *
     * @return list<array{name: string, file: string, override: ?string}>
     */
    public function resolutions(): array
    {
        return array_values($this->resolutions);
    }

    private function normalize(string $name, string $what): string
    {
        $clean = str_ends_with($name, '.php') ? substr($name, 0, -4) : $name;

        if ($clean === '') {
            throw new ViewException("An empty {$what} cannot resolve to a file.");
        }

        if (str_contains($clean, "\0") || str_contains($clean, '\\')) {
            throw new ViewException("Refusing '{$name}' as a {$what}.");
        }

        $segments = explode('/', $clean);

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || str_contains($segment, ':')) {
                throw new ViewException("Refusing '{$name}' as a {$what}.");
            }
        }

        return $clean;
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/phpunit tests/Unit/View/TemplateResolverTest.php`
Expected: PASS.

- [ ] **Step 5: Add `template_paths` to the root schema**

In `src/Config/RootSchema.php`, add this key to the array returned by
`create()`, directly after `paths`:

```php
'template_paths' => new SchemaKey(
    ValueType::Array,
    default: [],
    description: 'Directories searched for templates before the SDK’s own, highest priority '
        . 'first. A file with the same name as an SDK template replaces it; nothing needs '
        . 'copying or registering.',
    example: ['resources/rockadmin', 'vendor/company/admin-theme'],
),
```

- [ ] **Step 6: Assert the new key in the existing root schema test**

Find the test covering `RootSchema` (`tests/Unit/Config/`) and add an
assertion in the same style the file already uses, proving `template_paths`
exists, is an array and defaults to `[]`. Match the surrounding style rather
than introducing a new one.

- [ ] **Step 7: Run the full gate**

Run: `composer run check`
Expected: everything passes. The generated-reference test, if one exists for
the schema, must also still pass — an undeclared key fails CI by design.

- [ ] **Step 8: Commit**

```bash
git add src/View src/Config tests
git commit -m "Let a project replace one template without copying the rest"
```

---

### Task 3: The renderer

**Files:**
- Create: `src/View/Renderer.php`
- Test: `tests/Unit/View/RendererTest.php`

**Interfaces:**
- Consumes: `Escaper`, `ViewException` (Task 1), `TemplateResolver` (Task 2),
  and `RockAdmin\Http\UrlGenerator`, whose signatures are:
  ```php
  public function to(string $path, array $query = []): string;
  public function route(string $name, array $params = [], array $query = []): string;
  ```
- Produces:
  ```php
  namespace RockAdmin\View;

  final class Renderer
  {
      public function __construct(
          private readonly TemplateResolver $templates,
          private readonly Escaper $escaper,
          private readonly UrlGenerator $urls,
      );

      public function render(string $template, mixed $view = null): string;
      public function templates(): TemplateResolver;
      public function withOverrides(array $overrides): self;
  }
  ```

**What a template sees.** Exactly these nine variables and nothing else:

| Variable | Type | What it does |
|---|---|---|
| `$view` | `mixed` | the prepared view object passed to `render()` |
| `$e` | `Closure(mixed): string` | escapes for content and attribute values |
| `$raw` | `Closure(mixed): string` | writes unescaped, deliberately |
| `$attr` | `Closure(mixed): string` | escapes one attribute value |
| `$attrs` | `Closure(array): string` | builds a whole attribute list |
| `$href` | `Closure(mixed): string` | escapes a URL for an `href`, `src` or `action`, refusing a scheme that executes |
| `$url` | `Closure(string, array): string` | a URL from a path |
| `$route` | `Closure(string, array, array): string` | a URL from a route name |
| `$partial` | `Closure(string, mixed): string` | renders another template |

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/View/RendererTest.php`:

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\View\Escaper;
use RockAdmin\View\Renderer;
use RockAdmin\View\TemplateResolver;
use RockAdmin\View\ViewException;

#[CoversClass(Renderer::class)]
final class RendererTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/ra-render-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/sdk', 0o777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . '/sdk/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->root . '/sdk');
        rmdir($this->root);
    }

    private function template(string $name, string $contents): void
    {
        file_put_contents($this->root . '/sdk/' . $name . '.php', $contents);
    }

    private function renderer(): Renderer
    {
        return new Renderer(
            new TemplateResolver([], $this->root . '/sdk'),
            new Escaper(),
            new UrlGenerator('/admin'),
        );
    }

    public function testATemplateSeesItsViewObject(): void
    {
        $this->template('hello', '<p><?= $e($view->name) ?></p>');

        $view = new class () {
            public string $name = 'Tomáš';
        };

        $this->assertSame('<p>Tomáš</p>', $this->renderer()->render('hello', $view));
    }

    public function testOutputIsReturnedNotPrinted(): void
    {
        $this->template('hello', 'hi');

        ob_start();
        $out = $this->renderer()->render('hello');
        $leaked = (string) ob_get_clean();

        $this->assertSame('hi', $out);
        $this->assertSame('', $leaked);
    }

    public function testTheEscaperIsReachableAsE(): void
    {
        $this->template('x', '<?= $e("<b>") ?>');

        $this->assertSame('&lt;b&gt;', $this->renderer()->render('x'));
    }

    public function testRawWritesUnescaped(): void
    {
        $this->template('x', '<?= $raw("<b>bold</b>") ?>');

        $this->assertSame('<b>bold</b>', $this->renderer()->render('x'));
    }

    public function testAttrsBuildsAnAttributeList(): void
    {
        $this->template('x', '<tr<?= $attrs(["data-id" => 42, "hidden" => true]) ?>>');

        $this->assertSame('<tr data-id="42" hidden>', $this->renderer()->render('x'));
    }

    public function testUrlAndRouteGoThroughTheGenerator(): void
    {
        $this->template('x', '<?= $e($url("p/ads", ["page" => 2])) ?>|<?= $e($route("page.detail", ["page" => "ads", "id" => 7])) ?>');

        $this->assertSame('/admin/p/ads?page=2|/admin/p/ads/7', $this->renderer()->render('x'));
    }

    public function testPartialRendersAnotherTemplateWithItsOwnView(): void
    {
        $this->template('outer', 'a<?= $partial("inner", "B") ?>c');
        $this->template('inner', '<?= $e($view) ?>');

        $this->assertSame('aBc', $this->renderer()->render('outer'));
    }

    public function testAPartialDoesNotSeeTheOuterView(): void
    {
        // Leaking the caller's $view is how a partial silently keeps working
        // after someone forgets to pass it one.
        $this->template('outer', '<?= $partial("inner") ?>');
        $this->template('inner', '<?= $view === null ? "none" : "leaked" ?>');

        $this->assertSame('none', $this->renderer()->render('outer', 'outer view'));
    }

    public function testATemplateCannotSeeTheRendererOrTheResolver(): void
    {
        // Rule 4 of the project: no layer reaches two levels down. If $this or
        // a resolver is in scope, a template can build a query eventually.
        $this->template('x', '<?= isset($this) ? "this" : "no-this" ?>|<?= get_defined_vars() === [] ? "none" : implode(",", array_keys(get_defined_vars())) ?>');

        $out = $this->renderer()->render('x');

        $this->assertStringStartsWith('no-this|', $out);
        $this->assertSame(
            ['attr', 'attrs', 'e', 'href', 'partial', 'raw', 'route', 'url', 'view'],
            $this->definedVariables($out),
        );
    }

    /** @return list<string> */
    private function definedVariables(string $output): array
    {
        $names = explode(',', explode('|', $output)[1]);
        sort($names);

        return $names;
    }

    public function testAThrowingTemplateLeavesNoOutputBufferBehind(): void
    {
        $this->template('boom', 'partial output<?php throw new \RuntimeException("boom"); ?>');

        $depth = ob_get_level();

        try {
            $this->renderer()->render('boom');
            $this->fail('The exception should have propagated.');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertSame($depth, ob_get_level(), 'The renderer left an output buffer open.');
    }

    public function testAThrowingPartialAlsoUnwindsCleanly(): void
    {
        $this->template('outer', 'a<?= $partial("boom") ?>');
        $this->template('boom', '<?php throw new \RuntimeException("inner"); ?>');

        $depth = ob_get_level();

        try {
            $this->renderer()->render('outer');
            $this->fail('The exception should have propagated.');
        } catch (\RuntimeException $e) {
            $this->assertSame('inner', $e->getMessage());
        }

        $this->assertSame($depth, ob_get_level());
    }

    public function testAMissingTemplateThrows(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('nothing');

        $this->renderer()->render('nothing');
    }

    public function testRenderingTheSameTemplateTwiceIsIndependent(): void
    {
        // A template that declared a function or leaked state would fail the
        // second time. A grid renders one row template per row.
        $this->template('row', '<?= $e($view) ?>');

        $renderer = $this->renderer();

        $this->assertSame('1', $renderer->render('row', 1));
        $this->assertSame('2', $renderer->render('row', 2));
    }

    public function testWithOverridesScopesTheResolver(): void
    {
        mkdir($this->root . '/sdk/ui', 0o777, true);
        $this->template('ui/button', 'default');
        mkdir($this->root . '/sdk/ads', 0o777, true);
        file_put_contents($this->root . '/sdk/ads/button.php', 'ads');

        $renderer = $this->renderer();
        $scoped = $renderer->withOverrides(['ui/button' => 'ads/button']);

        $this->assertSame('ads', $scoped->render('ui/button'));
        $this->assertSame('default', $renderer->render('ui/button'));

        unlink($this->root . '/sdk/ads/button.php');
        rmdir($this->root . '/sdk/ads');
        unlink($this->root . '/sdk/ui/button.php');
        rmdir($this->root . '/sdk/ui');
    }
}
```

**Note for the implementer:** `tearDown()` as written removes flat files only.
Two tests create `sdk/ui/` and `sdk/ads/` subdirectories; the tests above clean
up after themselves where they do. If you find this fragile, replace the
cleanup with a recursive remove like the one in `TemplateResolverTest` — that
is an improvement, not a deviation. Say so in your report either way.

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/View/RendererTest.php`
Expected: FAIL — `RockAdmin\View\Renderer` does not exist.

- [ ] **Step 3: Write the implementation**

Create `src/View/Renderer.php`:

```php
<?php

declare(strict_types=1);

namespace RockAdmin\View;

use Closure;
use RockAdmin\Http\UrlGenerator;
use Throwable;

/**
 * Executes a plain PHP template and returns what it printed.
 *
 * Templates are plain PHP because every alternative is a dependency with its
 * own major versions, and most of them add a compiled-template directory that
 * has to be writable on deploy. What a template is allowed to see is kept
 * deliberately small: one prepared view object and a fixed set of helper
 * closures. There is no database here, no configuration, and no reference to
 * this renderer — so a template can be overridden by someone who has never
 * read the core, and a query cannot be smuggled into one.
 */
final class Renderer
{
    /** @var array<string, Closure> */
    private readonly array $helpers;

    public function __construct(
        private readonly TemplateResolver $templates,
        private readonly Escaper $escaper,
        private readonly UrlGenerator $urls,
    ) {
        $escaper = $this->escaper;
        $urls = $this->urls;

        $this->helpers = [
            'e' => static fn (mixed $value): string => $escaper->text($value),
            'raw' => static fn (mixed $value): string => $escaper->raw($value),
            'attr' => static fn (mixed $value): string => $escaper->attr($value),
            // Every href, src and action goes through this rather than $e():
            // it is the only helper that checks the scheme, and a URL that
            // reaches a template from a data column is not something the
            // admin built.
            'href' => static fn (mixed $value): string => $escaper->url($value),
            /** @param array<string, scalar|null> $attributes */
            'attrs' => static fn (array $attributes): string => $escaper->attributes($attributes),
            /** @param array<string, scalar|null> $query */
            'url' => static fn (string $path, array $query = []): string => $urls->to($path, $query),
            'route' => static fn (string $name, array $params = [], array $query = []): string
                => $urls->route($name, $params, $query),
            'partial' => fn (string $template, mixed $view = null): string => $this->render($template, $view),
        ];
    }

    public function render(string $template, mixed $view = null): string
    {
        return $this->execute($this->templates->resolve($template), $view);
    }

    public function templates(): TemplateResolver
    {
        return $this->templates;
    }

    /**
     * A copy whose resolver carries page-level template overrides.
     *
     * @param array<string, string> $overrides
     */
    public function withOverrides(array $overrides): self
    {
        return new self($this->templates->withOverrides($overrides), $this->escaper, $this->urls);
    }

    /**
     * The template runs inside a closure bound to nothing, so `$this` is not
     * available to it and the only variables in scope are the ones extracted
     * here. `Closure::bind(..., null)` is what makes that true; running the
     * template in an ordinary private method would hand it this object.
     */
    private function execute(string $file, mixed $view): string
    {
        // Declared with no parameters on purpose. A named parameter would be
        // a variable in the template's scope, and the whole point of this
        // closure is that the template sees exactly the nine documented
        // names and nothing else — func_get_arg() leaves no variable behind.
        $run = Closure::bind(
            static function (): void {
                extract(func_get_arg(1), EXTR_OVERWRITE);

                require func_get_arg(0);
            },
            null,
            null,
        );

        $depth = ob_get_level();
        ob_start();

        try {
            $run($file, ['view' => $view, ...$this->helpers]);

            return (string) ob_get_clean();
        } catch (Throwable $e) {
            // Discard whatever the template printed before it failed: half a
            // page rendered under an error page reads as a styling bug.
            while (ob_get_level() > $depth) {
                ob_end_clean();
            }

            throw $e;
        }
    }
}
```

**Why `func_get_arg()` and not parameters.** Any parameter name would be a
variable the template can see, and `testATemplateCannotSeeTheRendererOrTheResolver`
enumerates that scope. `extract()` before the `require` is what puts the nine
documented names there; nothing else may join them. This is the one place in
the project where `func_get_arg()` is the clearer choice, so it carries the
comment explaining why.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/phpunit tests/Unit/View/RendererTest.php`
Expected: PASS. In particular `testATemplateCannotSeeTheRendererOrTheResolver`
must report exactly the nine documented variables.

Add one more test while you are here: that `$href` refuses a URL whose scheme
executes, so a link built from a data column cannot carry one into a page.

```php
public function testHrefRefusesASchemeThatExecutes(): void
{
    $this->template('x', '<a href="<?= $href($view) ?>">x</a>');

    $this->expectException(\RockAdmin\View\ViewException::class);

    $this->renderer()->render('x', 'javascript:alert(1)');
}
```

- [ ] **Step 5: Run the full gate**

Run: `composer run check`

- [ ] **Step 6: Commit**

```bash
git add src/View tests/Unit/View
git commit -m "Run a template with a view object and nothing else in scope"
```

---

### Task 4: View objects and `ra-` classes

The small immutable objects templates receive. They hold no behaviour beyond
assembling their own classes, because everything a template needs must already
have been decided by the time rendering starts.

**Files:**
- Create: `src/View/Classes.php`
- Create: `src/View/PageView.php`
- Create: `src/View/ShellView.php`
- Create: `src/View/MenuItemView.php`
- Create: `src/View/ButtonView.php`
- Create: `src/View/FlashView.php`
- Test: `tests/Unit/View/ClassesTest.php`
- Test: `tests/Unit/View/ViewObjectsTest.php`

**Interfaces:**
- Consumes: `ViewException` from Task 1.
- Produces:
  ```php
  namespace RockAdmin\View;

  final class Classes
  {
      /** @param list<string> $extra */
      public static function of(string $structural, ?string $identity = null, array $extra = []): string;
      /** One class: 'ra-<structural>-<identity>', validated the same way. */
      public static function identity(string $structural, string $identity): string;
  }

  final class MenuItemView
  {
      /** @param list<MenuItemView> $children */
      public function __construct(
          public readonly string $label,
          public readonly string $url,
          public readonly ?string $icon = null,
          public readonly bool $active = false,
          public readonly array $children = [],
      );
      public function classes(): string;
  }

  final class ButtonView
  {
      /** @param array<string, scalar|null> $attributes */
      public function __construct(
          public readonly string $key,
          public readonly string $label,
          public readonly string $url = '#',
          public readonly ?string $icon = null,
          public readonly string $style = 'secondary',
          public readonly array $attributes = [],
      );
      public function classes(): string;
  }

  final class FlashView
  {
      public function __construct(
          public readonly string $level,   // success, info, warning, danger
          public readonly string $message,
      );
      public function classes(): string;
  }

  final class ShellView
  {
      /**
       * @param list<MenuItemView> $menu
       * @param list<FlashView>    $flashes
       * @param list<string>       $styles  stylesheet URLs, in order
       * @param list<string>       $scripts script URLs, in order
       */
      public function __construct(
          public readonly string $brand,
          public readonly array $menu = [],
          public readonly array $flashes = [],
          public readonly array $styles = [],
          public readonly array $scripts = [],
          public readonly ?string $userName = null,
          public readonly ?string $profileUrl = null,
          public readonly ?string $logoutUrl = null,
          public readonly string $darkMode = 'auto',
      );
      public function themeAttribute(): ?string;
  }

  final class PageView
  {
      /**
       * @param list<ButtonView>      $buttons
       * @param array<string, string> $slots   rendered HTML per layout slot
       */
      public function __construct(
          public readonly string $key,
          public readonly string $title,
          public readonly ShellView $shell,
          public readonly string $description = '',
          public readonly string $type = 'list',
          public readonly array $buttons = [],
          public readonly array $slots = [],
      );
      public function slot(string $name): string;
      public function hasSlot(string $name): bool;
      public function bodyClasses(): string;
  }
  ```

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/View/ClassesTest.php`:

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\View\Classes;
use RockAdmin\View\ViewException;

#[CoversClass(Classes::class)]
final class ClassesTest extends TestCase
{
    public function testAStructuralClassAlone(): void
    {
        $this->assertSame('ra-grid-row', Classes::of('grid-row'));
    }

    public function testAStructuralClassAndAnIdentityClass(): void
    {
        // The pair the specification asks for: one selector restyles every
        // grid cell, another restyles only the price cell.
        $this->assertSame('ra-grid-cell ra-grid-cell-price', Classes::of('grid-cell', 'price'));
    }

    public function testExtraClassesComeLast(): void
    {
        // Bootstrap classes and configuration's own 'class' key are appended,
        // so they win where specificity ties.
        $this->assertSame(
            'ra-btn ra-btn-create btn btn-primary',
            Classes::of('btn', 'create', ['btn', 'btn-primary']),
        );
    }

    public function testEmptyExtrasAreDropped(): void
    {
        $this->assertSame('ra-btn', Classes::of('btn', null, ['', '  ']));
    }

    public function testDuplicatesAreCollapsed(): void
    {
        $this->assertSame('ra-btn btn', Classes::of('btn', null, ['btn', 'btn']));
    }

    /** @return array<string, array{string}> */
    public static function malformedNames(): array
    {
        return [
            'a quote would end the attribute' => ['grid"cell'],
            'a space would add a class nobody wrote' => ['grid cell'],
            'an angle bracket would end the tag' => ['grid<cell'],
            'empty' => [''],
        ];
    }

    #[DataProvider('malformedNames')]
    public function testAMalformedNameIsRefused(string $name): void
    {
        // These names come from configuration keys — a column named by a
        // person. Refusing beats escaping, because a class attribute full of
        // entities is not a class anyone can target.
        $this->expectException(ViewException::class);

        Classes::of($name);
    }
}
```

Create `tests/Unit/View/ViewObjectsTest.php`:

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\View\ButtonView;
use RockAdmin\View\FlashView;
use RockAdmin\View\MenuItemView;
use RockAdmin\View\PageView;
use RockAdmin\View\ShellView;
use RockAdmin\View\ViewException;

#[CoversClass(ButtonView::class)]
#[CoversClass(FlashView::class)]
#[CoversClass(MenuItemView::class)]
#[CoversClass(PageView::class)]
#[CoversClass(ShellView::class)]
final class ViewObjectsTest extends TestCase
{
    private function shell(): ShellView
    {
        return new ShellView('RockAdmin');
    }

    public function testAPageCarriesItsIdentityIntoTheBodyClasses(): void
    {
        $page = new PageView('users', 'Users', $this->shell(), type: 'list');

        $this->assertSame('ra-page ra-page-users ra-page-type-list', $page->bodyClasses());
    }

    public function testASlotReturnsWhatWasRenderedIntoIt(): void
    {
        $page = new PageView('users', 'Users', $this->shell(), slots: ['main' => '<div>grid</div>']);

        $this->assertTrue($page->hasSlot('main'));
        $this->assertSame('<div>grid</div>', $page->slot('main'));
    }

    public function testAnAbsentSlotRendersAsNothing(): void
    {
        // A two-column layout used with one region should leave the other
        // column empty rather than fail: layouts do not know their regions.
        $page = new PageView('users', 'Users', $this->shell());

        $this->assertFalse($page->hasSlot('side'));
        $this->assertSame('', $page->slot('side'));
    }

    public function testAButtonCarriesStructuralAndIdentityClasses(): void
    {
        $button = new ButtonView('create', 'New ad', '/admin/p/ads/create', style: 'primary');

        $this->assertSame('ra-btn ra-btn-create btn btn-primary', $button->classes());
    }

    public function testAMenuItemKnowsWhetherItIsActive(): void
    {
        $plain = new MenuItemView('Ads', '/admin/p/ads');
        $active = new MenuItemView('Ads', '/admin/p/ads', active: true);

        $this->assertSame('ra-menu-item nav-link', $plain->classes());
        $this->assertSame('ra-menu-item ra-menu-item-active nav-link active', $active->classes());
    }

    /** @return array<string, array{string, string}> */
    public static function flashLevels(): array
    {
        return [
            'success' => ['success', 'ra-flash ra-flash-success text-bg-success'],
            'info' => ['info', 'ra-flash ra-flash-info text-bg-info'],
            'warning' => ['warning', 'ra-flash ra-flash-warning text-bg-warning'],
            'danger' => ['danger', 'ra-flash ra-flash-danger text-bg-danger'],
        ];
    }

    #[DataProvider('flashLevels')]
    public function testAFlashMapsItsLevelToClasses(string $level, string $expected): void
    {
        $this->assertSame($expected, (new FlashView($level, 'Saved.'))->classes());
    }

    public function testAnUnknownFlashLevelIsRefused(): void
    {
        // Silently rendering an unstyled toast is how a warning ends up
        // looking like a confirmation.
        $this->expectException(ViewException::class);

        new FlashView('purple', 'Saved.');
    }

    /** @return array<string, array{string, ?string}> */
    public static function darkModes(): array
    {
        return [
            'auto follows the operating system' => ['auto', null],
            'on' => ['on', 'dark'],
            'off' => ['off', 'light'],
        ];
    }

    #[DataProvider('darkModes')]
    public function testDarkModeBecomesBootstrapsThemeAttribute(string $mode, ?string $expected): void
    {
        // Bootstrap 5.3 reads data-bs-theme. 'auto' writes no attribute,
        // leaving the media query in rockadmin.css to decide.
        $this->assertSame($expected, (new ShellView('X', darkMode: $mode))->themeAttribute());
    }

    public function testAnUnknownDarkModeIsRefused(): void
    {
        $this->expectException(ViewException::class);

        new ShellView('X', darkMode: 'sometimes');
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit tests/Unit/View/ClassesTest.php tests/Unit/View/ViewObjectsTest.php`
Expected: FAIL — none of these classes exist.

- [ ] **Step 3: Write the implementation**

Create `src/View/Classes.php`:

```php
<?php

declare(strict_types=1);

namespace RockAdmin\View;

/**
 * Assembles the class attribute every element in this admin carries.
 *
 * The convention is a structural class saying what a thing is, an optional
 * identity class saying which one it is, and whatever appearance classes come
 * from Bootstrap or from configuration. One selector then restyles every grid
 * cell in the application; another restyles the price cell on one page.
 */
final class Classes
{
    /** @param list<string> $extra appearance classes, appended in order */
    public static function of(string $structural, ?string $identity = null, array $extra = []): string
    {
        $names = [self::name($structural, 'structural class')];

        if ($identity !== null) {
            $names[] = $names[0] . '-' . self::segment($identity);
        }

        foreach ($extra as $class) {
            $class = trim($class);

            if ($class !== '') {
                $names[] = $class;
            }
        }

        return implode(' ', array_values(array_unique($names)));
    }

    private static function name(string $value, string $what): string
    {
        return 'ra-' . self::segment($value, $what);
    }

    private static function segment(string $value, string $what = 'class name'): string
    {
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) !== 1) {
            throw new ViewException("Refusing '{$value}' as a {$what}: expected kebab-case.");
        }

        return $value;
    }
}
```

Create the five view objects. Each is a `final class` of readonly promoted
properties with the signature given under **Interfaces** above, plus:

- `MenuItemView::classes()` returns
  `Classes::of('menu-item', null, ['nav-link'])`, and when `$active` is true
  `'ra-menu-item ra-menu-item-active nav-link active'`. Build the active form
  explicitly rather than through `Classes::of()`'s identity argument — the
  identity slot means "which item", not "what state".
- `ButtonView::classes()` returns
  `Classes::of('btn', $this->key, ['btn', 'btn-' . $this->style])`.
- `FlashView`'s constructor refuses any level outside
  `['success', 'info', 'warning', 'danger']` with a `ViewException` naming the
  level and listing the four that are allowed; `classes()` returns
  `Classes::of('flash', $this->level, ['text-bg-' . $this->level])`.
- `ShellView`'s constructor refuses any `$darkMode` outside
  `['auto', 'on', 'off']`; `themeAttribute()` returns `null`, `'dark'` or
  `'light'` respectively.
- `PageView::bodyClasses()` returns
  `Classes::of('page', $this->key) . ' ' . Classes::identity('page-type', $this->type)`
  — exactly the three classes `ra-page ra-page-<key> ra-page-type-<type>` the
  specification writes. `Classes::identity()` is the second method on
  `Classes`: it returns one class rather than a pair, and validates both
  halves with the same kebab-case rule `of()` uses. Add a test for it in
  `ClassesTest` asserting `ra-page-type-list` and that a malformed identity is
  refused.
- `PageView::slot()` returns `$this->slots[$name] ?? ''`, and `hasSlot()`
  reports whether the key is present.

Every one of these classes gets a short class-level docblock saying what it is
for. None of them gets a setter, a static factory or a `toArray()`: they are
what a template reads, nothing else.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/phpunit tests/Unit/View/`
Expected: PASS.

- [ ] **Step 5: Run the full gate**

Run: `composer run check`

- [ ] **Step 6: Commit**

```bash
git add src/View tests/Unit/View
git commit -m "Give templates prepared objects that carry their own classes"
```

---

### Task 5: Flash messages

**Files:**
- Create: `src/View/FlashBag.php`
- Test: `tests/Unit/View/FlashBagTest.php`

**Interfaces:**
- Consumes: `FlashView` (Task 4), and `RockAdmin\Http\SessionStore`:
  ```php
  public function get(string $key, mixed $default = null): mixed;
  public function set(string $key, mixed $value): void;
  public function forget(string $key): void;
  public function regenerate(): void;
  ```
  `RockAdmin\Http\ArraySessionStore` implements it and is what the test uses.
- Produces:
  ```php
  namespace RockAdmin\View;

  final class FlashBag
  {
      public function __construct(private readonly SessionStore $session);

      public function add(string $level, string $message): void;
      public function success(string $message): void;
      public function info(string $message): void;
      public function warning(string $message): void;
      public function danger(string $message): void;
      /** @return list<FlashView> */
      public function take(): array;
      public function isEmpty(): bool;
  }
  ```

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/View/FlashBagTest.php`:

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Http\ArraySessionStore;
use RockAdmin\View\FlashBag;
use RockAdmin\View\FlashView;
use RockAdmin\View\ViewException;

#[CoversClass(FlashBag::class)]
final class FlashBagTest extends TestCase
{
    public function testAMessageSurvivesUntilItIsTaken(): void
    {
        $session = new ArraySessionStore();

        (new FlashBag($session))->success('Saved.');

        // A different FlashBag over the same session: this is what a redirect
        // is, one request writing and the next one reading.
        $taken = (new FlashBag($session))->take();

        $this->assertCount(1, $taken);
        $this->assertSame('success', $taken[0]->level);
        $this->assertSame('Saved.', $taken[0]->message);
    }

    public function testTakingDrainsTheBag(): void
    {
        // A toast that reappears on the next page is worse than no toast.
        $session = new ArraySessionStore();
        $bag = new FlashBag($session);
        $bag->info('Hello.');

        $this->assertCount(1, $bag->take());
        $this->assertSame([], $bag->take());
        $this->assertSame([], (new FlashBag($session))->take());
    }

    public function testMessagesKeepTheOrderTheyWereAddedIn(): void
    {
        $bag = new FlashBag(new ArraySessionStore());
        $bag->warning('First.');
        $bag->danger('Second.');

        $taken = $bag->take();

        $this->assertSame(['First.', 'Second.'], array_map(
            static fn (FlashView $flash): string => $flash->message,
            $taken,
        ));
    }

    public function testIsEmptyDoesNotDrain(): void
    {
        $bag = new FlashBag(new ArraySessionStore());

        $this->assertTrue($bag->isEmpty());

        $bag->info('Hello.');

        $this->assertFalse($bag->isEmpty());
        $this->assertCount(1, $bag->take());
    }

    public function testAnUnknownLevelIsRefusedWhenItIsAdded(): void
    {
        // Refuse at the point of writing, where the stack trace names the
        // caller — not one request later while rendering.
        $this->expectException(ViewException::class);

        (new FlashBag(new ArraySessionStore()))->add('purple', 'Saved.');
    }

    public function testRubbishInTheSessionIsDiscardedRatherThanRendered(): void
    {
        // The session is data from outside: another application sharing the
        // cookie, an old format after an upgrade, a hand-edited file.
        $session = new ArraySessionStore(['rockadmin.flashes' => 'not an array']);

        $this->assertSame([], (new FlashBag($session))->take());
    }

    public function testAMalformedEntryIsSkippedAndTheRestSurvive(): void
    {
        $session = new ArraySessionStore(['rockadmin.flashes' => [
            ['level' => 'success', 'message' => 'Kept.'],
            ['level' => 'purple', 'message' => 'Dropped.'],
            ['message' => 'Also dropped.'],
            'not an entry',
            ['level' => 'info', 'message' => 'Kept too.'],
        ]]);

        $taken = (new FlashBag($session))->take();

        $this->assertSame(['Kept.', 'Kept too.'], array_map(
            static fn (FlashView $flash): string => $flash->message,
            $taken,
        ));
    }

    public function testTheSessionKeyIsRemovedOnceTheBagIsEmpty(): void
    {
        // Leaving an empty array behind grows every session file by a key that
        // will never be read again.
        $session = new ArraySessionStore();
        $bag = new FlashBag($session);
        $bag->info('Hello.');
        $bag->take();

        $this->assertNull($session->get('rockadmin.flashes'));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/View/FlashBagTest.php`
Expected: FAIL — `RockAdmin\View\FlashBag` does not exist.

- [ ] **Step 3: Write the implementation**

Create `src/View/FlashBag.php`. The shape:

- The session key is the constant `private const KEY = 'rockadmin.flashes'`.
- `add()` validates the level against the same four levels `FlashView`
  accepts — construct a `FlashView` and let it do the validating, then store
  the plain `['level' => ..., 'message' => ...]` array. Storing objects in a
  session means a class the next request may not be able to unserialise.
- `take()` reads the key, discards anything that is not a list, builds a
  `FlashView` per well-formed entry, skips entries that are not arrays or
  whose level `FlashView` refuses, and calls `$this->session->forget(self::KEY)`
  before returning.
- `isEmpty()` reads without forgetting.
- `success()`, `info()`, `warning()` and `danger()` each delegate to `add()`.

Write it with the same commenting density as `src/Http/Csrf.php` — a
class-level docblock explaining why flashes live in the session and are
drained on read, and a comment only where the code would otherwise raise a
question.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/phpunit tests/Unit/View/FlashBagTest.php`
Expected: PASS.

- [ ] **Step 5: Run the full gate**

Run: `composer run check`

- [ ] **Step 6: Commit**

```bash
git add src/View tests/Unit/View
git commit -m "Carry a message across a redirect and show it once"
```

---

### Task 6: Assets

Bootstrap is vendored rather than loaded from a CDN: the version is pinned to
the RockAdmin version and cannot change underfoot, the admin works offline and
on intranets, and there are no CSP exceptions and no third-party outage.

**Files:**
- Create: `src/View/Assets.php`
- Create: `src/View/AssetHandler.php`
- Create: `assets/css/rockadmin.css` (a placeholder in this task; Task 7 writes the theme)
- Create: `assets/js/core.js` (likewise)
- Create: `assets/vendor/bootstrap/bootstrap.min.css`
- Create: `assets/vendor/bootstrap/bootstrap.bundle.min.js`
- Test: `tests/Unit/View/AssetsTest.php`
- Test: `tests/Unit/View/AssetHandlerTest.php`

**Interfaces:**
- Consumes: `UrlGenerator`, `Request`, `Response`, `Route`, `Handler`,
  `NotFoundException` from `RockAdmin\Http`. The route is already in the
  router as `['GET', '_assets/{path...}', 'assets']`, and `{path...}` is the
  one wildcard parameter, so `$route->param('path')` returns the rest of the
  path with its slashes intact.
- Produces:
  ```php
  namespace RockAdmin\View;

  final class Assets
  {
      /**
       * @param list<string> $projectStyles  URLs from configuration, loaded after the SDK's
       * @param list<string> $projectScripts
       */
      public function __construct(
          private readonly UrlGenerator $urls,
          private readonly array $projectStyles = [],
          private readonly array $projectScripts = [],
          private readonly ?string $root = null,
      );

      /** @return list<string> */
      public function styles(): array;
      /** @return list<string> */
      public function scripts(): array;
      public function url(string $path): string;
  }

  final class AssetHandler implements Handler
  {
      public function __construct(private readonly ?string $root = null);
      public function handle(Route $route, Request $request): Response;
  }
  ```
  `$root` is the `assets/` directory; null means this package's own
  (`\dirname(__DIR__, 2) . '/assets'`).

**Vendoring Bootstrap.** Download Bootstrap 5.3.3's `bootstrap.min.css` and
`bootstrap.bundle.min.js` into `assets/vendor/bootstrap/`, and write
`assets/vendor/bootstrap/VERSION` containing the single line `5.3.3`. If you
have no network access, create the two files containing a single CSS/JS
comment naming the version and say so plainly in your report — the tests in
this task assert behaviour about *serving* files, not about their contents,
and the real files can be vendored in a follow-up. Do not add a Composer
package, an npm dependency or a CDN link under any circumstances.

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/View/AssetsTest.php`:

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\View\Assets;
use RockAdmin\View\ViewException;

#[CoversClass(Assets::class)]
final class AssetsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/ra-assets-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/css', 0o777, true);
        mkdir($this->root . '/js', 0o777, true);
        mkdir($this->root . '/vendor/bootstrap', 0o777, true);
        file_put_contents($this->root . '/css/rockadmin.css', ':root{}');
        file_put_contents($this->root . '/js/core.js', '// core');
        file_put_contents($this->root . '/vendor/bootstrap/bootstrap.min.css', '.btn{}');
        file_put_contents($this->root . '/vendor/bootstrap/bootstrap.bundle.min.js', '// bs');
    }

    protected function tearDown(): void
    {
        foreach (['css/rockadmin.css', 'js/core.js', 'vendor/bootstrap/bootstrap.min.css', 'vendor/bootstrap/bootstrap.bundle.min.js'] as $file) {
            @unlink($this->root . '/' . $file);
        }

        foreach (['vendor/bootstrap', 'vendor', 'css', 'js', ''] as $directory) {
            @rmdir(rtrim($this->root . '/' . $directory, '/'));
        }
    }

    private function assets(array $css = [], array $js = []): Assets
    {
        return new Assets(new UrlGenerator('/admin'), $css, $js, $this->root);
    }

    public function testBootstrapComesBeforeTheThemeSoTheThemeOverridesIt(): void
    {
        $styles = $this->assets()->styles();

        $this->assertCount(2, $styles);
        $this->assertStringContainsString('bootstrap.min.css', $styles[0]);
        $this->assertStringContainsString('rockadmin.css', $styles[1]);
    }

    public function testProjectStylesComeLastSoTheyOverrideWithoutImportant(): void
    {
        $styles = $this->assets(['/css/admin-theme.css'])->styles();

        $this->assertCount(3, $styles);
        $this->assertSame('/css/admin-theme.css', $styles[2]);
    }

    public function testBootstrapsBundleComesBeforeCoreJs(): void
    {
        $scripts = $this->assets()->scripts();

        $this->assertCount(2, $scripts);
        $this->assertStringContainsString('bootstrap.bundle.min.js', $scripts[0]);
        $this->assertStringContainsString('core.js', $scripts[1]);
    }

    public function testProjectScriptsComeLast(): void
    {
        $scripts = $this->assets([], ['/js/admin-extra.js'])->scripts();

        $this->assertSame('/js/admin-extra.js', $scripts[2]);
    }

    public function testAnAssetUrlGoesThroughTheAssetsRoute(): void
    {
        $url = $this->assets()->url('css/rockadmin.css');

        $this->assertStringStartsWith('/admin/_assets/css/rockadmin.css?v=', $url);
    }

    public function testTheVersionIsTheContentHashSoATouchedFileKeepsItsUrl(): void
    {
        // A deploy that copies files changes every modification time. Hashing
        // the content means only what actually changed is re-fetched.
        $before = $this->assets()->url('css/rockadmin.css');
        touch($this->root . '/css/rockadmin.css', time() + 60);
        $after = $this->assets()->url('css/rockadmin.css');

        $this->assertSame($before, $after);

        file_put_contents($this->root . '/css/rockadmin.css', ':root{--x:1}');

        $this->assertNotSame($before, $this->assets()->url('css/rockadmin.css'));
    }

    public function testAnUnknownAssetIsRefusedRatherThanLinkedTo(): void
    {
        // A dead stylesheet link is a page that renders unstyled and looks
        // like a CSS bug. Failing here names the file instead.
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('css/nothing.css');

        $this->assets()->url('css/nothing.css');
    }
}
```

Create `tests/Unit/View/AssetHandlerTest.php`:

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RockAdmin\Http\NotFoundException;
use RockAdmin\Http\Request;
use RockAdmin\Http\Route;
use RockAdmin\View\AssetHandler;

#[CoversClass(AssetHandler::class)]
final class AssetHandlerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/ra-serve-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/css', 0o777, true);
        file_put_contents($this->root . '/css/rockadmin.css', ':root{--ra-x:1}');
        file_put_contents($this->root . '/secret.txt', 'not an asset');
    }

    protected function tearDown(): void
    {
        @unlink($this->root . '/css/rockadmin.css');
        @unlink($this->root . '/secret.txt');
        @rmdir($this->root . '/css');
        @rmdir($this->root);
    }

    private function serve(string $path): \RockAdmin\Http\Response
    {
        return (new AssetHandler($this->root))->handle(
            new Route('assets', ['path' => $path]),
            new Request('GET', '_assets/' . $path),
        );
    }

    public function testAKnownFileIsServedWithItsContentType(): void
    {
        $response = $this->serve('css/rockadmin.css');

        $this->assertSame(200, $response->status);
        $this->assertSame(':root{--ra-x:1}', $response->body);
        $this->assertSame('text/css; charset=utf-8', $response->headers['content-type']);
    }

    public function testAnAssetIsCachedForeverBecauseItsUrlCarriesItsHash(): void
    {
        $response = $this->serve('css/rockadmin.css');

        $this->assertSame('public, max-age=31536000, immutable', $response->headers['cache-control']);
    }

    public function testASniffedContentTypeIsRefused(): void
    {
        $this->assertSame('nosniff', $this->serve('css/rockadmin.css')->headers['x-content-type-options']);
    }

    /** @return array<string, array{string}> */
    public static function traversals(): array
    {
        return [
            'parent' => ['../secret.txt'],
            'parent inside' => ['css/../../secret.txt'],
            'absolute' => ['/etc/passwd'],
            'null byte' => ["css/rockadmin.css\0.png"],
            'backslash' => ['css\\..\\secret.txt'],
        ];
    }

    #[DataProvider('traversals')]
    public function testAPathLeavingTheAssetDirectoryIsNotFound(string $path): void
    {
        // The route's wildcard hands this handler whatever the URL held, so
        // this is the only thing between a visitor and the file system.
        $this->expectException(NotFoundException::class);

        $this->serve($path);
    }

    public function testAFileOutsideTheKnownTypesIsNotServed(): void
    {
        // An allowed-extension list, not a denied one: a new file type is a
        // decision, not an accident.
        $this->expectException(NotFoundException::class);

        $this->serve('secret.txt');
    }

    public function testAMissingFileIsNotFound(): void
    {
        $this->expectException(NotFoundException::class);

        $this->serve('css/nothing.css');
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit tests/Unit/View/AssetsTest.php tests/Unit/View/AssetHandlerTest.php`
Expected: FAIL — neither class exists.

- [ ] **Step 3: Write the implementation**

`Assets`:

- `styles()` returns
  `[$this->url('vendor/bootstrap/bootstrap.min.css'), $this->url('css/rockadmin.css'), ...$this->projectStyles]`.
- `scripts()` returns
  `[$this->url('vendor/bootstrap/bootstrap.bundle.min.js'), $this->url('js/core.js'), ...$this->projectScripts]`.
- `url()` validates the path with the same segment rules `TemplateResolver`
  uses (no `..`, no backslash, no leading slash, no null byte), throws a
  `ViewException` naming the path when the file does not exist, and otherwise
  returns `$this->urls->route('assets', ['path' => $path], ['v' => $hash])`
  where `$hash` is `substr(hash_file('xxh128', $file), 0, 12)`.
- Memoise the hash per path in a private array: a page links the same asset
  once, but a fragment request may build the list again.

`AssetHandler`:

- Validates the path the same way and throws `NotFoundException` — not
  `ViewException` — for anything it refuses, because this is a request
  handler and 404 is the honest answer.
- Resolves the file with `realpath()` and checks `str_starts_with()` against
  the `realpath()` of the root, so a symlink cannot lead out either.
- Maps extension to content type from a `private const TYPES` array covering
  `css`, `js`, `svg`, `png`, `jpg`, `jpeg`, `gif`, `webp`, `woff`, `woff2`,
  `ico`, `map`. Anything else is a `NotFoundException`.
- Returns `new Response(200, $contents, [...])` with `content-type`,
  `cache-control: public, max-age=31536000, immutable`, and
  `x-content-type-options: nosniff`.

Create `assets/css/rockadmin.css` containing `:root { }` and
`assets/js/core.js` containing `// RockAdmin core behaviour — see milestone 4, task 7.`
so the tests have something to hash; Task 7 replaces both.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/phpunit tests/Unit/View/`
Expected: PASS.

- [ ] **Step 5: Run the full gate**

Run: `composer run check`

- [ ] **Step 6: Commit**

```bash
git add src/View assets tests/Unit/View
git commit -m "Serve vendored assets with a hash in the URL and a year of cache"
```

---

### Task 7: The default templates and the theme

The first thing anyone sees. Every template here is also the worked example a
project copies when it overrides one, so the standard has to hold in all of
them: `ra-` classes on every element, no script tag, no hardcoded URL, every
`<?=` escaped.

**Files:**
- Create: `templates/layout/base.php`, `templates/layout/single.php`,
  `templates/layout/two-column.php`, `templates/layout/sidebar-detail.php`
- Create: `templates/page/header.php`
- Create: `templates/ui/button.php`, `badge.php`, `icon.php`, `toast.php`, `modal.php`
- Create: `templates/error/403.php`, `404.php`, `500.php`, `config-error.php`
- Rewrite: `assets/css/rockadmin.css`
- Rewrite: `assets/js/core.js`
- Modify: `src/Config/RootSchema.php` — add `theme`
- Modify: `src/Http/ErrorHandler.php` — render templates when a renderer is given
- Test: `tests/Unit/View/DefaultTemplatesTest.php`
- Test: extend the existing `tests/Unit/Http/ErrorHandlerTest.php`

**Interfaces:**
- Consumes: everything from Tasks 1–6.
- Produces: one interface and one implementation of it.

  `src/Http/ErrorPage.php`:
  ```php
  namespace RockAdmin\Http;

  interface ErrorPage
  {
      /** The rendered page, or null to leave the built-in HTML in place. */
      public function render(\Throwable $error, int $status, bool $debug): ?string;
  }
  ```

  `src/View/TemplateErrorPage.php`:
  ```php
  namespace RockAdmin\View;

  final class TemplateErrorPage implements ErrorPage
  {
      public function __construct(private readonly Renderer $renderer);
      public function render(\Throwable $error, int $status, bool $debug): ?string;
  }
  ```

  and `ErrorHandler` gains an optional third constructor parameter
  `private readonly ?ErrorPage $page = null`.

  **Why an interface rather than passing the `Renderer` straight in.** `Http`
  sits below `View`: the kernel, the router and the error handler must keep
  working for a project that renders nothing at all, and a `Renderer`
  parameter in `src/Http` would point the dependency the wrong way up. The
  interface lives with the thing that needs it, the implementation with the
  thing that can do it.

  `TemplateErrorPage::render()` resolves `error/{$status}`, falls back to
  `error/500`, and returns null when neither exists. `ErrorHandler` uses the
  returned HTML when it is a string and its own built-in HTML otherwise — and
  catches anything the page throws, because an error page that cannot render
  is still an error page, and a second exception here would replace the first
  one nobody has read yet.

**What each layout does.**

- `layout/base.php` receives a `PageView`. It writes the document: `<html>`
  with `lang` and, when `themeAttribute()` is not null, `data-bs-theme`; the
  stylesheets from `$view->shell->styles`; the navbar with the brand, the menu
  and the user dropdown; `<main class="ra-main">` holding
  `$raw($view->slot('main'))`; the shared modal and offcanvas containers; the
  toast container holding one `ui/toast` partial per flash; and the scripts
  from `$view->shell->scripts`. `<body>` carries `$view->bodyClasses()`.
- `layout/single.php`, `two-column.php` and `sidebar-detail.php` each render
  slots and nothing else. They know the slot names — `main`; `main` and
  `side`; `list` and `detail` — and they know their own `ra-layout-*` classes.
  They do not know what a region is.
- `page/header.php` receives the `PageView` and writes the title, the
  description when it is non-empty, and one `ui/button` partial per button.

**The modal and offcanvas containers.** One of each, in `base.php`, empty.
`core.js` fills them from a fragment's root attributes (`data-ra-title`,
`data-ra-size`, `data-ra-class`). This is why regions do not each carry their
own modal markup, and why an action can open any region anywhere.

**`core.js` in this milestone.** Only the parts that need no fragment:

1. The delegated `click` listener on `document`, dispatching on
   `[data-ra-action]` to a registry of action kinds. The kinds themselves
   arrive with the regions that use them, so the registry starts empty and
   an unknown kind logs one console warning and does nothing.
2. `RockAdmin.behavior(name, {attach, detach})`, `RockAdmin.attach(container)`
   and `RockAdmin.detach(container)`, with the marker attribute that stops an
   element initialising twice.
3. `RockAdmin.attach(document)` on `DOMContentLoaded`.

Everything that inserts HTML — fragment loading, region refresh, modal filling
— belongs to milestone 6, where there are fragments. Say so in a comment at
the top of the file rather than leaving a reader to wonder.

**The theme.** `assets/css/rockadmin.css` redefines Bootstrap's custom
properties rather than overriding its component classes, so there is no fork
to maintain:

- a `:root` block setting `--bs-body-font-size` (0.9rem), a system font stack,
  `--bs-border-radius` (0.25rem), a palette of `--bs-primary` and friends of
  its own rather than Bootstrap's blue, and `--ra-*` tokens for the shell:
  sidebar width, header height, grid row padding
- a density block: grid cell padding, table line height, form control height —
  the single biggest difference between something that looks like a website
  and something that looks like a tool
- `@media (prefers-color-scheme: dark)` guarded so it applies only when the
  document has no explicit `data-bs-theme`, plus an explicit
  `[data-bs-theme="dark"]` block. Both set the same `--bs-*` tokens.
- no `@import`, no font from a CDN, no image URL pointing outside `/_assets/`

Keep it plain and white, as the brief asks. Restrained is not the same as
default.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/View/DefaultTemplatesTest.php`. It renders the *real*
templates through a real `Renderer` — the SDK's own `templates/` directory,
not a fixture — and asserts what the specification promises. Start from this
file and add the rest:

```php
<?php

declare(strict_types=1);

namespace RockAdmin\Tests\Unit\View;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RockAdmin\Http\UrlGenerator;
use RockAdmin\View\ButtonView;
use RockAdmin\View\Escaper;
use RockAdmin\View\FlashView;
use RockAdmin\View\MenuItemView;
use RockAdmin\View\PageView;
use RockAdmin\View\Renderer;
use RockAdmin\View\ShellView;
use RockAdmin\View\TemplateResolver;

/**
 * The default templates, rendered as they ship. This test is about the
 * promises the specification makes — identity classes, escaping, slot
 * behaviour, dark mode — not about markup, so it never asserts on whitespace
 * or on an exact tag. A styling change should not break it; a broken promise
 * should.
 */
#[CoversNothing]
final class DefaultTemplatesTest extends TestCase
{
    private function renderer(): Renderer
    {
        return new Renderer(new TemplateResolver([]), new Escaper(), new UrlGenerator('/admin'));
    }

    private function page(
        string $key = 'users',
        array $slots = ['main' => '<div class="ra-region">grid</div>'],
        ?ShellView $shell = null,
        array $buttons = [],
        string $description = '',
    ): PageView {
        return new PageView(
            $key,
            'Users',
            $shell ?? new ShellView('RockAdmin'),
            description: $description,
            buttons: $buttons,
            slots: $slots,
        );
    }

    public function testTheBaseLayoutWritesADocumentAroundItsMainSlot(): void
    {
        $html = $this->renderer()->render('layout/base', $this->page());

        $this->assertStringContainsString('<!doctype html>', strtolower($html));
        $this->assertStringContainsString('<div class="ra-region">grid</div>', $html);
    }

    public function testTheBodyCarriesThePageIdentityClasses(): void
    {
        // .ra-page-users .ra-grid-cell { } has to work without every element
        // carrying an identity class of its own.
        $html = $this->renderer()->render('layout/base', $this->page());

        $this->assertStringContainsString('ra-page ra-page-users ra-page-type-list', $html);
    }

    public function testTheBrandIsEscaped(): void
    {
        $html = $this->renderer()->render(
            'layout/base',
            $this->page(shell: new ShellView('<b>Brand</b>')),
        );

        $this->assertStringContainsString('&lt;b&gt;Brand&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>Brand</b>', $html);
    }

    public function testStylesheetsAppearInTheOrderAssetsGaveThem(): void
    {
        // Project CSS loads last so it overrides without !important.
        $shell = new ShellView('RockAdmin', styles: ['/a.css', '/b.css'], scripts: ['/c.js']);

        $html = $this->renderer()->render('layout/base', $this->page(shell: $shell));

        $this->assertLessThan(strpos($html, '/b.css'), (int) strpos($html, '/a.css'));
        $this->assertStringContainsString('/c.js', $html);
    }

    public function testDarkModeOnWritesBootstrapsThemeAttribute(): void
    {
        $html = $this->renderer()->render(
            'layout/base',
            $this->page(shell: new ShellView('RockAdmin', darkMode: 'on')),
        );

        $this->assertStringContainsString('data-bs-theme="dark"', $html);
    }

    public function testDarkModeAutoLeavesTheDecisionToTheStylesheet(): void
    {
        $html = $this->renderer()->render('layout/base', $this->page());

        $this->assertStringNotContainsString('data-bs-theme', $html);
    }

    public function testTheMenuMarksTheActiveItem(): void
    {
        $shell = new ShellView('RockAdmin', menu: [
            new MenuItemView('Ads', '/admin/p/ads'),
            new MenuItemView('Users', '/admin/p/users', active: true),
        ]);

        $html = $this->renderer()->render('layout/base', $this->page(shell: $shell));

        $this->assertStringContainsString('ra-menu-item-active', $html);
        $this->assertSame(1, substr_count($html, 'ra-menu-item-active'));
    }

    public function testAFlashRendersAsAToast(): void
    {
        $shell = new ShellView('RockAdmin', flashes: [new FlashView('success', 'Saved.')]);

        $html = $this->renderer()->render('layout/base', $this->page(shell: $shell));

        $this->assertStringContainsString('ra-flash ra-flash-success', $html);
        $this->assertStringContainsString('Saved.', $html);
    }

    public function testTwoColumnsToleratesAMissingSlot(): void
    {
        // Layouts do not know their regions, so a layout used with one region
        // renders an empty column rather than failing.
        $html = $this->renderer()->render('layout/two-column', $this->page(slots: ['main' => 'left']));

        $this->assertStringContainsString('left', $html);
        $this->assertStringContainsString('ra-layout-two-column', $html);
    }

    public function testThePageHeaderRendersOneButtonPerButton(): void
    {
        $page = $this->page(buttons: [
            new ButtonView('create', 'New', '/admin/p/users/create', style: 'primary'),
            new ButtonView('export', 'Export', '/admin/p/users/export'),
        ]);

        $html = $this->renderer()->render('page/header', $page);

        $this->assertStringContainsString('ra-btn-create', $html);
        $this->assertStringContainsString('ra-btn-export', $html);
    }

    public function testThePageHeaderOmitsTheDescriptionWhenItIsEmpty(): void
    {
        // An empty <p> in the header pushes the grid down on every page that
        // has no description, which is most of them.
        $html = $this->renderer()->render('page/header', $this->page());

        $this->assertStringNotContainsString('ra-page-description', $html);
    }
}
```

Add, in the same style: one test per remaining layout (`single`,
`sidebar-detail`), one per error template asserting it names its status, and
one asserting that no flashes leave the toast container empty rather than
absent. Every assertion names what it protects.

Extend `tests/Unit/Http/ErrorHandlerTest.php` with:

```php
public function testAnErrorPageUsesTheTemplateWhenOneIsAvailable(): void
public function testAnErrorPageFallsBackToTheBuiltInHtmlWithoutAnErrorPage(): void
public function testAnErrorPageThatThrowsStillProducesTheOriginalErrorPage(): void
```

The third one matters most: pass a `TemplateErrorPage` over a renderer whose
resolver points at an empty directory, and assert the response still carries
the original status and the built-in body. Read the existing file first and
match its style.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit tests/Unit/View/DefaultTemplatesTest.php tests/Unit/Http/ErrorHandlerTest.php`
Expected: FAIL — no templates exist yet.

- [ ] **Step 3: Write the templates, the theme, `core.js` and the schema key**

Add to `RootSchema::create()`, after `assets`:

```php
'theme' => new SchemaKey(
    ValueType::Array,
    description: 'The default look. Replace it wholesale with a stylesheet in assets.css.',
    children: new Schema([
        'dark' => new SchemaKey(
            ValueType::String,
            default: 'auto',
            description: "Dark mode: 'auto' follows the operating system, 'on' and 'off' decide.",
            example: 'auto',
        ),
    ]),
),
```

Write the templates. A worked example of the standard, for `ui/button.php`:

```php
<?php

/**
 * A button or a link, depending on whether it navigates.
 *
 * @var \RockAdmin\View\ButtonView $view
 * @var \Closure $e
 * @var \Closure $href
 * @var \Closure $attrs
 * @var \Closure $partial
 */
?>
<a class="<?= $e($view->classes()) ?>" href="<?= $href($view->url) ?>"<?= $attrs($view->attributes) ?>>
    <?php if ($view->icon !== null) { ?>
        <?= $partial('ui/icon', $view->icon) ?>
    <?php } ?>
    <span class="ra-btn-label"><?= $e($view->label) ?></span>
</a>
```

Note what it does: a docblock declaring every variable it uses so PHPStan can
see the types, `ra-` classes on both elements, escaping on every echo, `$href`
rather than `$e` for the URL — it is the only helper that checks the scheme —
and no URL it built itself.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `composer run test`

- [ ] **Step 5: Run the full gate**

Run: `composer run check`
PHPStan analyses `templates/` too — check `phpstan.neon` and add the directory
if it is not already there. A template whose `@var` docblock is wrong will
fail here, which is the point of writing them.

- [ ] **Step 6: Commit**

```bash
git add templates assets src tests
git commit -m "Give the admin a shell, a theme and an error page of its own"
```

---

### Task 8: The template standard, enforced — and somewhere to look at it

Section 14 of the specification promises: *no template contains a script tag,
a hardcoded URL, or unescaped output; CI fails otherwise.* This task makes
that true, and builds the demo the theme will be tuned against.

**Files:**
- Create: `tests/Unit/View/TemplateStandardsTest.php`
- Create: `demo/index.php`
- Create: `demo/config/rockadmin.php`
- Create: `demo/README.md`
- Modify: `README.md` — a short "Looking at it" section pointing at the demo

**Interfaces:**
- Consumes: everything from Tasks 1–7.
- Produces: no new class.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/View/TemplateStandardsTest.php`. It walks every `.php` file
under `templates/` with a `RecursiveDirectoryIterator`, and runs one test per
file through a data provider so a failure names the file. The rules:

1. **No script tag that carries behaviour.** No `<script>` with a body, in any
   casing, anywhere. A `<script src="...">` with an empty body is allowed in
   `templates/layout/base.php` and nowhere else — the page has to load its
   assets somewhere, and that is the one template that writes a document.
   Every other template can be returned as a fragment and inserted into a
   live page, where a script tag either does not run or runs twice.
2. **Every `<?=` escapes.** Every short echo tag is immediately followed
   (ignoring whitespace) by a call to `$e(`, `$raw(`, `$attr(`, `$attrs(`,
   `$href(` or `$partial(`. Anything else fails, naming the offending line.
2b. **Every URL attribute uses `$href`.** An `href=`, `src=` or `action=`
   attribute whose value is a short echo tag must call `$href(`, not `$e(`.
   `$e()` escapes the characters that would end the attribute but says
   nothing about the scheme, so `$e()` on a URL from a data column is how a
   `javascript:` link reaches a page.
3. **No hardcoded URL.** No `href="/`, `src="/`, `action="/`, `http://` or
   `https://` outside a comment. Every URL comes from `$url()`, `$route()` or
   a view object.
4. **`ra-` classes.** The file contains at least one `ra-` class. A template
   with no elements at all is not a thing this project has, so there is no
   exemption.
5. **A declared view.** The file's docblock declares `@var` for `$view` unless
   the template uses no `$view` at all.

Also assert the suite is not vacuous:

```php
public function testTheStandardIsCheckedAgainstEveryTemplate(): void
{
    // A provider that silently found nothing would make every rule above
    // pass. Milestone 4 ships fourteen templates; this fails loudly if a
    // future refactor moves the directory.
    $this->assertGreaterThanOrEqual(14, \count(self::templates()));
}
```

And prove each rule bites, by running it against a string rather than a file:
extract the checks into private methods taking a file's contents, and add four
small tests feeding them a violating snippet and asserting the failure. A
standards test that has never seen a violation is a standards test nobody
knows the shape of.

- [ ] **Step 2: Run the test to verify it fails**

Write one rule, run it against the real templates, watch it pass; then feed
the violating snippet, watch it fail. Report both in your report.

- [ ] **Step 3: Write the demo**

`demo/index.php` is a front controller of maybe forty lines. It:

- requires `../vendor/autoload.php`
- builds a `Request` from the superglobals
- builds `UrlGenerator`, `TemplateResolver`, `Escaper`, `Renderer`,
  `Assets`, `FlashBag` over an `ArraySessionStore` or the native one
- registers `AssetHandler` for the `assets` route and one small inline
  `Handler` for `dashboard` that builds a `ShellView` with two menu items and
  a `PageView` with a `main` slot holding a paragraph, renders
  `layout/single` inside `layout/base`, and returns `Response::html()`
- sends the response

`demo/config/rockadmin.php` holds the configuration the demo loads, small but
real — `brand`, `url_mode`, `theme.dark`, `paths.logs`.

`demo/README.md` says how to run it in two lines:

```bash
php -S localhost:8080 -t demo demo/index.php
```

and what it is for: the design surface that anyone who clones the repository
can open, kept honest to the general case, as opposed to a real project mounted
through a Composer path repository, which says where the general case is not
enough.

There is no grid yet, so the demo shows the shell, the menu, a header with
buttons, a toast and both dark modes. That is exactly what this milestone
built, and it is the first thing worth looking at.

- [ ] **Step 4: Check the demo actually runs**

Run: `php -S localhost:8080 -t demo demo/index.php` in the background, then
`curl -s http://localhost:8080/ | head -40`, then stop the server. Paste the
first twenty lines of output into your report. A demo that does not boot is
worse than no demo, and nothing else in the suite would catch it.

- [ ] **Step 5: Run the full gate**

Run: `composer run check`
The demo is PHP the standards apply to as well — if `phpstan.neon` does not
cover `demo/`, add it.

- [ ] **Step 6: Commit**

```bash
git add tests demo README.md
git commit -m "Enforce the template standard and give the theme somewhere to live"
```

---

## Milestone acceptance

1. `composer run check` is green: coding standards, PHPStan level max, the
   whole suite.
2. `composer show --tree` lists no runtime dependency beyond PHP extensions.
3. A template cannot reach the renderer, the resolver, the configuration or a
   database connection — proved by a test that enumerates every variable in a
   template's scope.
4. A project replaces one template by putting a file of the same name in its
   own directory, with nothing copied and nothing registered.
5. Every template in `templates/` passes the standard, and each rule of that
   standard has a test showing it fails when violated.
6. An asset URL carries a content hash, and the handler refuses every path
   that leaves the asset directory.
7. A flash message survives a redirect, renders once and is gone.
8. `php -S localhost:8080 -t demo demo/index.php` serves a themed admin shell
   in light and dark.

## What this milestone deliberately leaves out

- **Regions.** `list`, `form`, `preview`, `nav` and `stat` arrive in
  milestones 6 and 7. This milestone builds the slots they render into and the
  layouts that arrange them, and nothing that knows what a region contains.
- **Fragment loading and region refresh.** The half of `core.js` that inserts
  HTML needs fragments to insert; milestone 6.
- **The menu's contents.** `MenuItemView` ships here, the code that builds
  menu items from configuration and filters them by permission does not —
  that needs users and roles, which is milestone 5.
- **The development console.** `TemplateResolver::resolutions()` exists for
  it and is tested; the panel that renders it, along with the SQL log, is
  milestone 10.
- **Editable cells**, reserved for v1.1 by specification 8.12.
- **Localisation.** Every string this milestone writes is English, and the
  templates carry no translation call. Section 13 puts UI localisation out of
  v1 deliberately; adding a lookup now would be designing against an
  imagined translator.

## Amendments made during execution

Written after the fact. The plan above is what was dispatched; this section
records where reality differed.

**The standard banned a script tag no page could do without.** Rule 1 forbade
every `<script` in a template, but `layout/base.php` has to reference the
stylesheets and the bundle somewhere — no page could exist and pass. The ban
was always about behaviour surviving AJAX insertion, so it now forbids a
script with a body anywhere and a `src`-only tag everywhere except that one
template. Amended before Task 8 was dispatched.

**Templates needed a ninth helper.** The plan escaped URLs with `$e()`, which
closes the attribute and says nothing about the scheme — so the escaper's
scheme allow-list, added during Task 1, would have been bypassed by every link
in the admin. `$href` was added to the helper set and to the template standard
before Task 3 was dispatched.

**Five defects in the plan's own test code** were fixed before Task 1 ran: an
invalid-UTF-8 expectation that dropped a surviving character, four consecutive
`expectExceptionMessage()` calls of which PHPUnit honours only the last, a
Windows path comparison against forward slashes, a fixture written into a
directory the test never created, and a scope assertion contradicted by the
implementation the same plan specified.

**The renderer's scope is described, not counted.** The plan asked for exactly
nine variables in a template's scope and specified `func_get_arg()` to achieve
it, which cost six PHPStan errors. The property that matters is that nothing
in scope is an object a template could work backwards from; the closure takes
named parameters again and the template's own path stays visible.

**The escaper became an allow-list.** The plan specified a deny-list of
executable schemes. A deny-list fails open — a leading C0 control byte hid the
scheme entirely — so it allows `http`, `https`, `mailto`, `tel` and a relative
URL, and refuses everything else including a scheme-relative one.

**`Classes` translates rather than refuses.** An identity comes from a
configuration key, and this project writes those in snake_case; the plan held
it to the CSS kebab-case rule, so a page keyed `user_accounts` threw and took
the page down.

**The error page went behind an interface.** The plan passed a `Renderer` into
`ErrorHandler`. `Http` sits below `View`, so `ErrorPage` is an interface there
and `TemplateErrorPage` implements it.

**Three schema keys were decorative.** `template_paths`, `assets.css`/`assets.js`
and `theme.dark` were declared and read by nothing, so a project could not
switch on this milestone's headline feature. `ViewFactory` wires them.

**The demo existed so a visual defect could not ship, and one did.** Flash
messages rendered invisible — Bootstrap hides a toast without `.show` — while
a passing test asserted their classes and their text. The navbar's menu was
black on near-black, the primary button stayed Bootstrap blue because
`.btn-primary` does not read `--bs-primary`, and dark mode under `auto`
re-tinted seven variables and stopped. All four were found by the final review
opening the page. Nobody had looked at it.
