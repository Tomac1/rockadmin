<?php

declare(strict_types=1);

namespace RockAdmin\View;

/**
 * Which template came from which file, in resolution order.
 *
 * Kept as its own small object, rather than an array property on
 * TemplateResolver, so a resolver and every copy withOverrides() makes of it
 * can share one log: a page-level template override is the normal path for
 * any page that configures `templates`, and rendering happens through the
 * copy, not the original. A log that lived on the resolver alone would be
 * empty on the instance the development console asks, for exactly the pages
 * it exists to explain.
 */
final class TemplateResolutionLog
{
    /** @var array<string, array{name: string, file: string, override: ?string}> */
    private array $entries = [];

    public function record(string $name, string $file, ?string $override): void
    {
        // Keyed by name: a grid resolves the same row template once per row,
        // and the development console wants the set, not the log.
        $this->entries[$name] ??= [
            'name' => $name,
            'file' => $file,
            'override' => $override,
        ];
    }

    /** @return list<array{name: string, file: string, override: ?string}> */
    public function all(): array
    {
        return array_values($this->entries);
    }
}
