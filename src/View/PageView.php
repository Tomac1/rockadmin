<?php

declare(strict_types=1);

namespace RockAdmin\View;

/**
 * A complete admin page ready for rendering.
 *
 * Carries the page's identity (key and title), its shell (branding and
 * navigation), buttons for actions, rendered content slots, and page type
 * information for styling.
 */
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
    ) {
    }

    public function slot(string $name): string
    {
        return $this->slots[$name] ?? '';
    }

    public function hasSlot(string $name): bool
    {
        return isset($this->slots[$name]);
    }

    public function bodyClasses(): string
    {
        return Classes::of('page', $this->key) . ' ' . Classes::identity('page-type', $this->type);
    }
}
