<?php

declare(strict_types=1);

namespace RockAdmin\Page;

/**
 * What a page knows about itself while its actions are being built.
 *
 * `buildAction()` holds two refusals that are not about the action alone: a
 * `when` names a column, and only a region knows its columns; a `refresh`
 * names a region, and only the page knows its regions. Until milestone 8 that
 * knowledge arrived as a `RegionDefinition` and a map of them, because the
 * page's own `actions` block was built last, after every region existed.
 *
 * An action may now also be defined inside a column's `items` (spec 8.5), and
 * that happens while the region carrying the column is still being built —
 * there is no `RegionDefinition` to hand over yet, and there never will be
 * one until the column it is waiting on is finished. So the two refusals are
 * given what they actually need, which is two lists of keys, read from the
 * page's raw configuration before any region is built.
 *
 * The alternative was a second pass that rebuilt every region once the first
 * was done, purely so the same two checks could be handed objects instead of
 * names. That would have put an inline action on a different construction path
 * from a named one, which is the one thing this task was told not to do.
 */
final class ActionContext
{
    /**
     * @param list<string> $regionKeys  every region this page declares
     * @param ?string      $gridKey     the first list region's key, or null when the page has no list region
     * @param list<string> $gridColumns that region's column keys, in the order it declares them
     */
    public function __construct(
        public readonly array $regionKeys,
        public readonly ?string $gridKey = null,
        public readonly array $gridColumns = [],
    ) {
    }

    /**
     * Read from the raw `regions` block, before anything is built.
     *
     * The first list region is the same one `firstListRegion()` finds over the
     * built regions — one array, iterated in one order — so the two answers
     * cannot drift.
     *
     * @param array<string, mixed> $regionsConfig the page's `regions`, as the file wrote it
     */
    public static function of(array $regionsConfig): self
    {
        $regionKeys = array_map(strval(...), array_keys($regionsConfig));
        $gridKey = null;
        $gridColumns = [];

        foreach ($regionsConfig as $key => $config) {
            if (!\is_array($config) || ($config['type'] ?? null) !== RegionType::List->value) {
                continue;
            }

            $gridKey = (string) $key;
            $columns = $config['columns'] ?? null;
            $gridColumns = \is_array($columns) ? array_map(strval(...), array_keys($columns)) : [];

            break;
        }

        return new self($regionKeys, $gridKey, $gridColumns);
    }
}
