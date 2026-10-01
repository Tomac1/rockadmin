<?php

declare(strict_types=1);

namespace RockAdmin\Config;

use Closure;

/**
 * Turns the strings that carry meaning into values and objects.
 *
 * Two kinds of placeholder, separated by when they can be known:
 *
 * - `{{env.X}}` and `{{config.x}}` are fixed for a deployment, so they resolve
 *   here and are baked into the cache.
 * - `{{workspace.x}}` and `{{user.x}}` differ per request, so they become
 *   Placeholder objects that survive the cache and bind later.
 *
 * A missing environment variable resolves to null rather than raising: the
 * validator then reports it against the key that wanted a string, which names
 * the actual problem instead of naming the variable.
 */
final class Resolver
{
    private const DEFERRED = ['workspace', 'user'];

    private const IMMEDIATE = ['env', 'config'];

    private const SECRET_HINTS = ['KEY', 'SECRET', 'PASSWORD', 'TOKEN'];

    /**
     * The reference namespaces of specification 8.5, claimed by this class.
     *
     * A string beginning with one of these is either a reference or a mistake;
     * it is never ordinary text. That is the whole point of the list: without
     * it `@page:` with nothing after it would quietly stay a string and become
     * a link to nowhere. `@enum:` is matched separately, above, because it
     * predates this and its key pattern is wider.
     */
    private const REFERENCE_NAMESPACES = ['page', 'action', 'region'];

    /**
     * A page name, an action verb, a literal path segment.
     *
     * Deliberately the configuration-key convention (lower-case snake_case,
     * spec 6.7) and nothing more. A page name is a file name, so refusing
     * upper case here refuses a reference that loads on Windows and 404s on
     * Linux; refusing everything else refuses, by construction rather than by
     * a later check, every string that would mean something to a URL — `.`,
     * `..`, `/` where no slash was expected, `?`, `#`, `%`, a space.
     */
    private const NAME = '/^[a-z][a-z0-9_]*$/';

    /**
     * A literal segment after the page, which may also be a number.
     *
     * The page itself may not be: it is a file name. A later segment may,
     * because `@page:settings/1/edit` — a singleton row addressed from
     * configuration — is a real thing somebody writes, and a digit costs
     * nothing that `NAME` was protecting against.
     */
    private const SEGMENT = '/^[a-z0-9][a-z0-9_]*$/';

    /** A row placeholder, `{id}` — substituted per row, not here (spec 8.5). */
    private const ROW_PLACEHOLDER = '/^\{[a-z][a-z0-9_]*}$/';

    /** @var list<string> */
    private array $warnings = [];

    /** @var list<string> environment names already warned about */
    private array $warnedAbout = [];

    /**
     * The {{config.*}} paths currently being resolved, innermost last.
     *
     * A config placeholder may point at another one, so resolution re-enters
     * itself; the chain is what turns an endless loop into a named error.
     *
     * @var list<string>
     */
    private array $chain = [];

    /**
     * @param Closure(string): ?string $env reads the environment
     * @param array<string, mixed>     $raw the merged configuration, for {{config.x}}
     */
    public function __construct(
        private readonly Closure $env,
        private readonly array $raw = [],
    ) {
    }

    /**
     * @param  array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function resolve(array $config): array
    {
        $resolved = [];

        foreach ($config as $name => $value) {
            $resolved[$name] = $this->value($value);
        }

        return $resolved;
    }

    /** @return list<string> */
    public function warnings(): array
    {
        return $this->warnings;
    }

    private function value(mixed $value): mixed
    {
        if (\is_array($value)) {
            /** @var array<string, mixed> $value narrows array<mixed, mixed> — argument.type without it */
            return $this->resolve($value);
        }

        if (!\is_string($value)) {
            return $value;
        }

        if (preg_match('/^@enum:(\w+)$/', $value, $match) === 1) {
            return new EnumReference($match[1]);
        }

        if (preg_match('/^@([A-Za-z]+):(.*)$/s', $value, $match) === 1
            && \in_array(strtolower($match[1]), self::REFERENCE_NAMESPACES, true)
        ) {
            return $this->reference($match[1], $match[2], $value);
        }

        return $this->string($value);
    }

    /**
     * One of the three references specification 8.5 defines.
     *
     * Parsed here and validated elsewhere: the syntax lives in this class, the
     * catalogue of pages does not. What this does decide is whether the string
     * is *well formed*, because that is a property of the syntax, and because
     * this is the string that becomes a URL.
     */
    private function reference(string $namespace, string $key, string $original): PageReference|ActionReference
    {
        // Case is not a spelling choice. The namespaces are three lower-case
        // words, and a reference that works in two spellings is two things to
        // remember -- so '@Page:ads' is refused by name rather than accepted
        // or, worse, left as text because it matched nothing.
        if ($namespace !== strtolower($namespace)) {
            throw new ConfigException(
                "'{$original}' spells the reference namespace '{$namespace}' with an upper-case letter. "
                . "Reference namespaces are lower case: '@" . strtolower($namespace) . ":'.",
            );
        }

        if ($namespace === 'region') {
            throw new ConfigException(
                "'{$original}' addresses a region, which only an 'open' action can display. 'open' actions "
                . 'arrive in a later milestone, with the modal and offcanvas they need; until then an action '
                . "is a 'link' or a 'post'.",
            );
        }

        if ($key === '') {
            throw new ConfigException($namespace === 'page'
                ? "'{$original}' names no page. Write '@page:users', or '@page:users/{id}/edit' to address "
                    . 'one of its rows.'
                : "'{$original}' names no action. Write '@action:archive', naming an action of this page.");
        }

        return $namespace === 'page'
            ? $this->pageReference($key, $original)
            : $this->actionReference($key, $original);
    }

    private function pageReference(string $key, string $original): PageReference
    {
        $segments = explode('/', $key);
        $page = array_shift($segments);

        $valid = preg_match(self::NAME, $page) === 1;

        foreach ($segments as $segment) {
            $valid = $valid && (
                preg_match(self::SEGMENT, $segment) === 1
                || preg_match(self::ROW_PLACEHOLDER, $segment) === 1
            );
        }

        if (!$valid) {
            // A dotted key is not a near miss, it is the other namespace: 8.5
            // writes a page and a region of another page as 'users' and
            // 'users.detail', and the two are one character apart.
            $hint = str_contains($key, '.') && !str_contains($key, '/')
                ? " A dotted key addresses a region of a page, not a page, and that is '@region:'."
                : '';

            throw new ConfigException(
                "'{$original}' is not a valid page reference. The page is a file in pages/, so it is "
                . 'lower-case snake_case, and every further segment is either a lower-case name or a '
                . "{row_placeholder}: '@page:users', '@page:users/create', '@page:users/{id}/edit'." . $hint,
            );
        }

        return new PageReference($page, implode('/', $segments));
    }

    private function actionReference(string $key, string $original): ActionReference
    {
        if (preg_match(self::NAME, $key) !== 1) {
            throw new ConfigException(
                "'{$original}' is not a valid action reference. An action is addressed on the current page, "
                . "so the reference is one lower-case snake_case verb and nothing else: '@action:archive'.",
            );
        }

        return new ActionReference($key);
    }

    private function string(string $value): mixed
    {
        if (preg_match('/^\{\{(\w+)\.([\w.]+)}}$/', $value, $match) === 1) {
            return $this->single($match[1], $match[2], $value);
        }

        return preg_replace_callback(
            '/\{\{(\w+)\.([\w.]+)}}/',
            function (array $match): string {
                if (\in_array($match[1], self::DEFERRED, true)) {
                    throw new ConfigException(
                        "{$match[0]} cannot appear inside a longer string. A "
                        . 'workspace or user placeholder becomes a bound value, '
                        . 'not text, so there is nothing to interpolate it into.',
                    );
                }

                $resolved = $this->immediate($match[1], $match[2], $match[0]);
                if (\is_scalar($resolved) || $resolved === null) {
                    return (string) ($resolved ?? '');
                }

                throw new ConfigException(
                    'Cannot interpolate ' . get_debug_type($resolved) . ' '
                    . "into string at {$match[0]}",
                );
            },
            $value,
        ) ?? $value;
    }

    private function single(string $namespace, string $name, string $original): mixed
    {
        if (\in_array($namespace, self::DEFERRED, true)) {
            return new Placeholder($namespace, $name);
        }

        return $this->immediate($namespace, $name, $original);
    }

    private function immediate(string $namespace, string $name, string $original): mixed
    {
        if (!\in_array($namespace, self::IMMEDIATE, true)) {
            throw new ConfigException(
                "Unknown placeholder namespace '{$namespace}' in {$original}. "
                . 'Use env, config, workspace or user.',
            );
        }

        if ($namespace === 'config') {
            return $this->fromConfig($name);
        }

        $this->warnIfSecret($name);

        return ($this->env)($name);
    }

    /**
     * Resolves what a {{config.x}} placeholder points at.
     *
     * The value is read from the raw, pre-resolution store — that is what makes
     * a single pass predictable — and then resolved in turn. Without that second
     * step a raw '{{workspace.site_id}}' would come back as text, which would let
     * {{config.*}} walk around the refusal to interpolate a deferred placeholder
     * into a longer string.
     */
    private function fromConfig(string $name): mixed
    {
        if (\in_array($name, $this->chain, true)) {
            $printedChain = implode(' -> ', [...$this->chain, $name]);

            throw new ConfigException("Configuration placeholder cycle: {$printedChain}.");
        }

        $this->chain[] = $name;

        try {
            return $this->value($this->fromRaw($name));
        } finally {
            array_pop($this->chain);
        }
    }

    /** Reads a dotted path out of the raw, pre-resolution configuration. */
    private function fromRaw(string $name): mixed
    {
        $value = $this->raw;

        foreach (explode('.', $name) as $segment) {
            if (!\is_array($value) || !\array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * The hints are uppercase and the name may not be: {{env.db_password}} is as
     * much of a secret as {{env.DB_PASSWORD}}. One warning per variable, however
     * many times it is used, so the list stays readable.
     */
    private function warnIfSecret(string $name): void
    {
        if (\in_array($name, $this->warnedAbout, true)) {
            return;
        }

        $upper = strtoupper($name);

        foreach (self::SECRET_HINTS as $hint) {
            if (str_contains($upper, $hint)) {
                $this->warnedAbout[] = $name;
                $this->warnings[] = "{{env.{$name}}} looks like a secret. Configuration "
                    . 'values can end up rendered, so check this one is meant to be visible.';

                return;
            }
        }
    }
}
