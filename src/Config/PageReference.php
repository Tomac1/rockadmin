<?php

declare(strict_types=1);

namespace RockAdmin\Config;

/**
 * A reference to a page, optionally to one of its sub-paths: `@page:users`,
 * `@page:users/{id}/edit` (specification 8.5).
 *
 * The same shape as EnumReference, and for the same reason: `Resolver` lives
 * in `Config` and knows the syntax, while the catalogue of pages lives in
 * `Page`. So the string becomes an object here and `PageRepository` is what
 * says whether the page exists.
 *
 * Like EnumReference, this constructor does not validate. The narrow pattern
 * that judges what a *page file* may write is in `Resolver`, because that is
 * the only place the text came from configuration; a reference built in code —
 * by `RowPlaceholders` substituting `{id}` for `42`, say — carries segments no
 * page file would be allowed to write.
 */
final class PageReference
{
    /**
     * @param string $page   the page file's name without `.php`
     * @param string $suffix everything after the page, `''` for the page itself
     */
    public function __construct(
        public readonly string $page,
        public readonly string $suffix = '',
    ) {
    }

    public function __toString(): string
    {
        return '@page:' . $this->page . ($this->suffix === '' ? '' : '/' . $this->suffix);
    }

    /** @param array{page: string, suffix: string} $data written by var_export() */
    public static function __set_state(array $data): self
    {
        return new self($data['page'], $data['suffix']);
    }
}
