<?php

declare(strict_types=1);

namespace RockAdmin\View;

use Closure;
use RockAdmin\Http\UrlGenerator;
use Throwable;

/**
 * Executes a plain PHP template and returns what it printed.
 *
 * Templates are plain PHP because every alternative is a dependency with its
 * own major versions, and most of them add a compiled-template directory that
 * has to be writable on deploy. What a template is allowed to see is kept
 * deliberately small: one prepared view object and a fixed set of helper
 * closures. There is no database here, no configuration, and no reference to
 * this renderer — so a template can be overridden by someone who has never
 * read the core, and a query cannot be smuggled into one.
 */
final class Renderer
{
    /** @var array<string, Closure|mixed> */
    private readonly array $helpers;

    public function __construct(
        private readonly TemplateResolver $templates,
        private readonly Escaper $escaper,
        private readonly UrlGenerator $urls,
    ) {
        $escaper = $this->escaper;
        $urls = $this->urls;

        $this->helpers = [
            'e' => static fn (mixed $value): string => $escaper->text($value),
            'raw' => static fn (mixed $value): string => $escaper->raw($value),
            'attr' => static fn (mixed $value): string => $escaper->attr($value),
            // Every href, src and action goes through this rather than $e():
            // it is the only helper that checks the scheme, and a URL that
            // reaches a template from a data column is not something the
            // admin built.
            'href' => static fn (mixed $value): string => $escaper->url($value),
            /**
             * @param array<int|string, scalar|null> $attributes
             */
            'attrs' => static fn (array $attributes): string => $escaper->attributes($attributes),
            /**
             * @param array<string, int|string> $query
             */
            'url' => static fn (string $path, array $query = []): string => $urls->to($path, $query),
            /**
             * @param array<string, int|string> $params
             * @param array<string, int|string> $query
             */
            'route' => static fn (string $name, array $params = [], array $query = []): string
                => $urls->route($name, $params, $query),
            'partial' => fn (string $template, mixed $view = null): string => $this->render($template, $view),
        ];
    }

    public function render(string $template, mixed $view = null): string
    {
        return $this->execute($this->templates->resolve($template), $view);
    }

    public function templates(): TemplateResolver
    {
        return $this->templates;
    }

    /**
     * A copy whose resolver carries page-level template overrides.
     *
     * @param array<string, string> $overrides
     */
    public function withOverrides(array $overrides): self
    {
        return new self($this->templates->withOverrides($overrides), $this->escaper, $this->urls);
    }

    /**
     * The template runs inside a closure bound to nothing, so `$this` is not
     * available to it and the only variables in scope are the ones extracted
     * here. `Closure::bind(..., null)` is what makes that true; running the
     * template in an ordinary private method would hand it this object.
     */
    private function execute(string $file, mixed $view): string
    {
        // Declared with no parameters on purpose. A named parameter would be
        // a variable in the template's scope, and the whole point of this
        // closure is that the template sees exactly the nine documented
        // names and nothing else — func_get_arg() leaves no variable behind.
        $run = Closure::bind(
            static function (): void {
                extract(func_get_arg(1), EXTR_OVERWRITE);

                require func_get_arg(0);
            },
            null,
            null,
        );

        $depth = ob_get_level();
        ob_start();

        try {
            $run($file, ['view' => $view, ...$this->helpers]);

            return (string) ob_get_clean();
        } catch (Throwable $e) {
            // Discard whatever the template printed before it failed: half a
            // page rendered under an error page reads as a styling bug.
            while (ob_get_level() > $depth) {
                ob_end_clean();
            }

            throw $e;
        }
    }


}
