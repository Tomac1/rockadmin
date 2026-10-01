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

    /**
     * The design system's button vocabulary, not Bootstrap's.
     *
     * `style` is a host-facing name and stays what it was — a project passing
     * `style: 'danger'` should not have to learn which modifier draws a red
     * button — so the translation happens here, at the one place that turns a
     * view object into classes. Anything unrecognised becomes the neutral
     * button rather than nothing: a class the design system does not define
     * styles no element, and an unstyled button reads as a broken page rather
     * than as a typo in configuration.
     *
     * The size is explicit because `.ra-btn` carries none of its own. Without
     * a size modifier the browser's default padding applies and the button
     * renders 16px tall, which is exactly what these two buttons did here.
     */
    public function classes(): string
    {
        $variants = [
            'primary' => 'primary',
            'secondary' => 'secondary',
            'success' => 'success',
            'danger' => 'danger',
            'warning' => 'warning',
            'info' => 'secondary',
            'light' => 'ghost',
            'dark' => 'secondary',
            'link' => 'ghost',
            'ghost' => 'ghost',
            'outline' => 'outline',
            'danger-ghost' => 'danger-ghost',
        ];

        $variant = $variants[$this->style] ?? 'secondary';

        return Classes::of('btn', $this->key, ['ra-btn--' . $variant, 'ra-btn--sm']);
    }
}
