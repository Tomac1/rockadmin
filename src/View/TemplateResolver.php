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

    private readonly TemplateResolutionLog $log;

    /**
     * @param list<string>          $paths     project directories, highest priority first
     * @param string|null           $default   the SDK's templates; null means this package's own
     * @param array<string, string> $overrides template name => the name to use instead
     * @param TemplateResolutionLog|null $log  shared with a copy made by withOverrides(); null starts a new one
     */
    public function __construct(array $paths, ?string $default = null, array $overrides = [], ?TemplateResolutionLog $log = null)
    {
        $normalize = static fn (string $path): string => rtrim(str_replace('\\', '/', $path), '/');

        $this->projectPaths = array_values(array_map($normalize, $paths));
        $this->default = $normalize($default ?? \dirname(__DIR__, 2) . '/templates');
        $this->paths = [...$this->projectPaths, $this->default];
        $this->overrides = $overrides;
        $this->log = $log ?? new TemplateResolutionLog();
    }

    /** Returns the absolute path of the file this name resolves to. */
    public function resolve(string $name): string
    {
        ['requested' => $requested, 'override' => $override, 'effective' => $effective] = $this->effective($name);

        $file = $this->find($effective);

        if ($file === null) {
            $searched = implode(', ', array_map(
                static fn (string $path): string => $path . '/' . $effective . '.php',
                $this->paths,
            ));

            throw new ViewException("No template '{$requested}' in any template path. Looked in: {$searched}.");
        }

        $this->log->record($requested, $file, $override === null ? null : $effective);

        return $file;
    }

    /**
     * Answers without recording: a probe for whether a template exists must
     * not count as having rendered it, or TemplateErrorPage's two has()
     * checks (error/{status}, then error/500) would log both every time,
     * whether or not either one goes on to render.
     */
    public function has(string $name): bool
    {
        try {
            $parts = $this->effective($name);
        } catch (ViewException) {
            return false;
        }

        return $this->find($parts['effective']) !== null;
    }

    /**
     * A copy carrying page-level template overrides. Overrides are per page,
     * the resolver is per request, so the page scopes rather than mutates —
     * but the resolution log is shared, not copied: a page-level override is
     * the normal path for any page that configures `templates`, and it is
     * the copy, not the original, that goes on to render. Sharing the log is
     * what makes resolutions() on the original see what the copy resolved.
     *
     * @param array<string, string> $overrides
     */
    public function withOverrides(array $overrides): self
    {
        return new self($this->projectPaths, $this->default, [...$this->overrides, ...$overrides], $this->log);
    }

    /**
     * Which template came from which file, in resolution order. The
     * development console renders this.
     *
     * @return list<array{name: string, file: string, override: ?string}>
     */
    public function resolutions(): array
    {
        return $this->log->all();
    }

    /**
     * @return array{requested: string, override: ?string, effective: string}
     */
    private function effective(string $name): array
    {
        $requested = $this->normalize($name, 'template name');
        $override = $this->overrides[$requested] ?? null;
        $effective = $override === null ? $requested : $this->normalize($override, 'template override');

        return ['requested' => $requested, 'override' => $override, 'effective' => $effective];
    }

    private function find(string $effective): ?string
    {
        foreach ($this->paths as $path) {
            $file = $path . '/' . $effective . '.php';

            if (is_file($file)) {
                return $file;
            }
        }

        return null;
    }

    private function normalize(string $name, string $what): string
    {
        $clean = str_ends_with($name, '.php') ? substr($name, 0, -4) : $name;

        if ($clean === '') {
            throw new ViewException("An empty {$what} cannot resolve to a file.");
        }

        // Each refusal says which rule was broken. This error reaches a person
        // who wrote a name in configuration, and "refused" without "why" sends
        // them reading the source to find out.
        if (str_contains($clean, "\0")) {
            throw new ViewException("Refusing '{$name}' as a {$what}: it contains a null byte.");
        }

        if (str_contains($clean, '\\')) {
            throw new ViewException(
                "Refusing '{$name}' as a {$what}: separate segments with '/', on every platform.",
            );
        }

        foreach (explode('/', $clean) as $segment) {
            if ($segment === '') {
                throw new ViewException("Refusing '{$name}' as a {$what}: it has an empty segment.");
            }

            if ($segment === '.' || $segment === '..') {
                throw new ViewException(
                    "Refusing '{$name}' as a {$what}: a name is relative to a template path and cannot leave it.",
                );
            }

            if (str_contains($segment, ':')) {
                throw new ViewException(
                    "Refusing '{$name}' as a {$what}: a name is relative, so it names no drive or stream wrapper.",
                );
            }
        }

        return $clean;
    }
}
