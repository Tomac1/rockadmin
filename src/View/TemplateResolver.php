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
