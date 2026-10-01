<?php

declare(strict_types=1);

namespace RockAdmin\View;

/**
 * One entry in the top bar's workspace switcher.
 *
 * It exists before the thing it switches. Specification 9.3 puts a switcher
 * here and the design system draws one, but workspaces are a later milestone
 * — so this is the shape the shell will be handed, and nothing hands it one
 * yet. The alternative was to leave the template with no type to iterate and
 * invent one later under deadline, which is how a view object ends up
 * carrying a definition.
 */
final class WorkspaceView
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        /** Where switching to it posts. Spec 9.3: the active workspace lives in the session. */
        public readonly string $url,
        public readonly bool $active = false,
    ) {
    }
}
