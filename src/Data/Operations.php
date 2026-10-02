<?php

declare(strict_types=1);

namespace RockAdmin\Data;

use RockAdmin\Config\HandlerReference;
use RockAdmin\Config\Schema;
use RockAdmin\Page\PageException;
use RockAdmin\Page\PageRepository;

/**
 * The project's own write logic, by name.
 *
 * `Roles`' sibling in shape and for the same reason: configuration names
 * something, wiring supplies it, and the two are checked against each other
 * once, early, by whoever holds both. A page file saying
 * `'handler' => '@handler:save_ad'` with nothing registered under `save_ad`
 * is refused when this is built — naming the page and the key — rather than
 * at the moment somebody presses Save.
 *
 * That timing is the whole value of the class. A registry that answered null
 * at click time would turn a wiring mistake into a 500 on one button, found
 * by whoever happened to press it.
 */
final class Operations
{
    /** @param array<string, Operation> $operations by the name configuration uses */
    private function __construct(private readonly array $operations)
    {
    }

    /** No custom logic anywhere: every write is the default. */
    public static function none(): self
    {
        return new self([]);
    }

    /**
     * @param array<string, Operation> $operations
     */
    public static function of(array $operations): self
    {
        return new self($operations);
    }

    /**
     * Every `@handler:` any page names is registered here.
     *
     * Called by whoever has both the pages and the wiring, which is the host.
     * Like `Permission::assertActionsAreGrantable()`, it lives where both
     * halves are in hand rather than in either one of them.
     *
     * @throws PageException naming the page, the key and the nearest registration
     */
    public function assertEveryHandlerExists(PageRepository $pages): void
    {
        foreach ($pages->names() as $name) {
            $page = $pages->get($name);

            foreach ($page->handlers() as $where => $reference) {
                if (isset($this->operations[$reference->name])) {
                    continue;
                }

                $nearest = Schema::nearestOf(array_keys($this->operations), $reference->name);

                throw new PageException(\sprintf(
                    "Page '%s': %s names %s, which nothing registered. %s%s",
                    $name,
                    $where,
                    (string) $reference,
                    $this->operations === []
                        ? 'No operations are registered at all.'
                        : "Registered: '" . implode("', '", array_keys($this->operations)) . "'.",
                    $nearest === null ? '' : " Did you mean '{$nearest}'?",
                ));
            }
        }
    }

    public function has(HandlerReference $reference): bool
    {
        return isset($this->operations[$reference->name]);
    }

    /**
     * @throws PageException when nothing is registered under that name, which
     *                       `assertEveryHandlerExists()` should have caught first
     */
    public function get(HandlerReference $reference): Operation
    {
        return $this->operations[$reference->name]
            ?? throw new PageException(
                "No operation is registered as {$reference}. This should have been refused when the "
                . 'registry was built; if it was not, something is building pages it did not check.',
            );
    }
}
