<?php

declare(strict_types=1);

namespace RockAdmin\View;

/**
 * A clickable button in the admin interface.
 *
 * Each button has a key identifying it, a label for display, a URL, optional
 * icon, style (primary, secondary, etc.), and arbitrary HTML attributes.
 */
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
    ) {
    }

    public function classes(): string
    {
        return Classes::of('btn', $this->key, ['btn', 'btn-' . $this->style]);
    }
}
