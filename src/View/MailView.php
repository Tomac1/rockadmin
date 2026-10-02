<?php

declare(strict_types=1);

namespace RockAdmin\View;

/**
 * What a mail template is handed.
 *
 * `PageView`'s distant cousin, and much smaller for a reason: a message has no
 * menu, no actions, no permissions and no reader to resolve them against. It
 * carries what both halves of a message need -- who it is for, what the link
 * is, and how long it lasts -- and nothing else.
 *
 * `$body` is set only on the way into a layout, by `MessageFactory`. A content
 * template never reads it, and the layout reads nothing else.
 */
final class MailView
{
    public function __construct(
        /** The admin's own name, as the message signs itself. */
        public readonly string $brand,
        /** The heading, which is usually also the subject. */
        public readonly string $title,
        /** Who it is for, by name where there is one and by address otherwise. */
        public readonly string $recipient,
        /** Where the button goes. An absolute URL: a mail client has no base. */
        public readonly string $url,
        /** How long the link lasts, already in words: '60 minutes', '2 days'. */
        public readonly string $expiry,
        /** The rendered content, for the layout only. Raw HTML, escaped by the template that made it. */
        public readonly string $body = '',
    ) {
    }

    public function withBody(string $body): self
    {
        return new self($this->brand, $this->title, $this->recipient, $this->url, $this->expiry, $body);
    }
}
