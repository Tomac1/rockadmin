<?php

declare(strict_types=1);

namespace RockAdmin\Http;

/**
 * An outgoing response. Immutable, so a handler can hand one back and a
 * caller can add headers without the original changing under it.
 */
final class Response
{
    /** @var array<string, string> */
    public readonly array $headers;

    /** @param array<string, string> $headers */
    public function __construct(
        public readonly int $status = 200,
        public readonly string $body = '',
        array $headers = [],
    ) {
        $this->headers = array_change_key_case($headers);
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($status, $body, ['content-type' => 'text/html; charset=utf-8']);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self($status, '', ['location' => $location]);
    }

    public static function notFound(string $body = ''): self
    {
        return self::html($body, 404);
    }

    public function withHeader(string $name, string $value): self
    {
        return new self($this->status, $this->body, [strtolower($name) => $value] + $this->headers);
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value, true);
        }

        echo $this->body;
    }
}
