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
        return Classes::of('flash', $this->level, ['text-bg-' . $this->level]);
    }
}
