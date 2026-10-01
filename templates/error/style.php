<?php

/**
 * The error pages' styling, inline.
 *
 * Every other page links the design system through `Assets`, which hashes
 * each file, checks it exists and mints a URL through `UrlGenerator`. An
 * error page cannot: `TemplateErrorPage` is handed a `Renderer` and a
 * `Throwable` and nothing else, and that is deliberate -- it is reached when
 * something has already failed, and the configuration, the URL generator or
 * the asset route may be the thing that failed. A 500 page that 500s is the
 * one page in an admin that has to work.
 *
 * So it is self-contained: no link, no request, no dependency on anything
 * outside this file. The rule it does not break is the one about `<script>`;
 * there is no behaviour here, only enough type and spacing that a person
 * meeting a 500 reads a sentence rather than a wall of Times New Roman.
 *
 * The colours are written out rather than taken from the design system's
 * tokens, for the same reason: a `var()` whose custom property never loaded
 * falls back to nothing, and `color: ` with no value is `inherit`.
 *
 * @var \Closure(mixed): string $e
 */
?>
<style>
    .ra-error {
        margin: 0;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.5rem;
        background: #f1f5f9;
        color: #0f172a;
        font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
        font-size: 0.875rem;
        line-height: 1.5;
    }

    .ra-error-main {
        width: 100%;
        max-width: 32rem;
        padding: 1.5rem;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 0.25rem;
        box-shadow: 0 1px 2px rgb(15 23 42 / 6%);
    }

    .ra-error-title {
        margin: 0 0 0.5rem;
        font-size: 1.25rem;
        font-weight: 600;
        letter-spacing: -0.01em;
    }

    .ra-error-message {
        margin: 0;
        color: #475569;
    }

    .ra-error-detail {
        margin: 1rem 0 0;
        padding: 0.75rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.25rem;
        font-family: ui-monospace, "JetBrains Mono", "SFMono-Regular", monospace;
        font-size: 0.75rem;
        white-space: pre-wrap;
        overflow-wrap: anywhere;
    }
</style>
