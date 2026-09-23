<?php

declare(strict_types=1);

namespace RockAdmin\View;

use RockAdmin\Http\SessionStore;

/**
 * A bag of messages written during one request and read on the next after a
 * redirect. Each message is stored in the session as plain data (never an
 * object), validated at the point of writing, and drained on read so it
 * appears exactly once.
 */
final class FlashBag
{
    private const KEY = 'rockadmin.flashes';

    public function __construct(private readonly SessionStore $session)
    {
    }

    /**
     * Add a message with the given level and text. The level is validated
     * immediately against FlashView's allowed set.
     */
    public function add(string $level, string $message): void
    {
        // Construct a FlashView to validate the level. Its exception names
        // the caller in the stack trace, not a request later while rendering.
        new FlashView($level, $message);

        $flashes = $this->session->get(self::KEY, []);

        // Ensure we have a list. Session data may have been corrupted.
        if (!\is_array($flashes)) {
            $flashes = [];
        }

        $flashes[] = ['level' => $level, 'message' => $message];
        $this->session->set(self::KEY, $flashes);
    }

    /** Add a success message. */
    public function success(string $message): void
    {
        $this->add('success', $message);
    }

    /** Add an info message. */
    public function info(string $message): void
    {
        $this->add('info', $message);
    }

    /** Add a warning message. */
    public function warning(string $message): void
    {
        $this->add('warning', $message);
    }

    /** Add a danger message. */
    public function danger(string $message): void
    {
        $this->add('danger', $message);
    }

    /**
     * Return all messages and remove them from the session. Entries that are
     * not arrays or whose level is not allowed by FlashView are skipped.
     *
     * @return list<FlashView>
     */
    public function take(): array
    {
        $flashes = $this->session->get(self::KEY, []);

        // Drained first, and unconditionally. Draining at the end would leave
        // anything this method refuses to read sitting in the session for
        // ever — and what it refuses to read is exactly what should not be
        // kept: a value another application wrote, or a format this version
        // no longer understands.
        $this->session->forget(self::KEY);

        if (!\is_array($flashes)) {
            return [];
        }

        $result = [];
        foreach ($flashes as $entry) {
            // Skip entries that are not arrays.
            if (!\is_array($entry)) {
                continue;
            }

            // Check that level and message keys exist and are strings.
            if (!isset($entry['level'], $entry['message']) || !\is_string($entry['level']) || !\is_string($entry['message'])) {
                continue;
            }

            // Try to construct a FlashView. Skip the entry if its level is
            // invalid.
            try {
                $result[] = new FlashView($entry['level'], $entry['message']);
            } catch (ViewException) {
                // Skip malformed entries.
                continue;
            }
        }

        return $result;
    }

    /** Check whether there are any messages without draining the bag. */
    public function isEmpty(): bool
    {
        $flashes = $this->session->get(self::KEY, []);

        if (!\is_array($flashes)) {
            return true;
        }

        return \count($flashes) === 0;
    }
}
