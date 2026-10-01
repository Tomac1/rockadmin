<?php

declare(strict_types=1);

namespace RockAdmin\View;

/**
 * A transient notification or alert message.
 *
 * Flash messages are displayed once and include a level (success, info,
 * warning, danger) and a message string. They map to Bootstrap alert styling.
 */
final class FlashView
{
    private const ALLOWED_LEVELS = ['success', 'info', 'warning', 'danger'];

    public function __construct(
        public readonly string $level,
        public readonly string $message,
    ) {
        if (!\in_array($this->level, self::ALLOWED_LEVELS, true)) {
            throw new ViewException(
                "Flash level '{$this->level}' not allowed; must be one of: " . implode(', ', self::ALLOWED_LEVELS) . '.',
            );
        }
    }

    public function classes(): string
    {
        // The design system's own level names, which are not Bootstrap's:
        // `danger` is `error` there, and the rest line up. Mapped here rather
        // than renaming the level, because the level is what a handler asks
        // for and `$flashes->danger(...)` reads better than `->error(...)`
        // beside an exception that is not one.
        $variant = $this->level === 'danger' ? 'error' : $this->level;

        return Classes::of('flash', $this->level, [
            'ra-toast',
            'ra-toast--' . $variant,
        ]);
    }
}
