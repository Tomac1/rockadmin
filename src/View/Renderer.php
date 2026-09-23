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
 *
 * The limit of that is Reflection: a template could read the helpers' captured
 * variables with ReflectionFunction::getStaticVariables() and reach the escaper
 * and the URL generator. It could reach this Renderer too — $partial is a
 * non-static closure, so ReflectionFunction::getClosureThis() hands back the
 * object it is bound to, which is this Renderer, not null. None of the three
 * leads to the configuration or the database, and a template that imports
 * ReflectionFunction has stopped being a template — the guarantee here is
 * against smuggling a query into one by accident, not against a hostile
 * template file, which is code the project already trusts.
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
            'attrs' => static fn (array $attributes): string
                => $escaper->attributes(self::attributeValues($attributes)),
            'url' => static fn (string $path, array $query = []): string
                => $urls->to($path, self::urlParameters($query, 'query')),
            'route' => static fn (string $name, array $params = [], array $query = []): string
                => $urls->route($name, self::urlParameters($params, 'parameters'), self::urlParameters($query, 'query')),
            'partial' => fn (string $template, mixed $view = null): string => $this->render($template, $view),
        ];
    }

    /**
     * A template hands these helpers whatever a view object holds, so the
     * array arriving here is `array<mixed, mixed>` however it was declared
     * upstream. Checking each value is the honest way to reach the narrower
     * type the escaper asks for — and it turns a TypeError somewhere inside
     * the escaper into a message naming the attribute.
     *
     * @param  array<mixed, mixed>                $attributes
     * @return array<int|string, scalar|null>
     */
    private static function attributeValues(array $attributes): array
    {
        $clean = [];

        foreach ($attributes as $name => $value) {
            if ($value !== null && !\is_scalar($value)) {
                throw new ViewException(
                    "The attribute '{$name}' holds a " . get_debug_type($value)
                    . '. An attribute value is a string, a number, a boolean or null.',
                );
            }

            $clean[(string) $name] = $value;
        }

        return $clean;
    }

    /**
     * The same narrowing for the two places a template passes values into a
     * URL.
     *
     * This is stricter than PHP would be: http_build_query() would happily
     * turn 2.5 into "2.5" and true into "1". Both are guesses. A float in a
     * URL is a locale question nobody wants to answer twice, and "1" for true
     * is a convention this project does not use anywhere else — so a value
     * that is not a string or an integer is refused by name rather than
     * spelled by accident. UrlGenerator's own signature already says
     * array<string, int|string>; this makes that true at the boundary a
     * template writes against.
     *
     * @param  array<mixed, mixed>        $values
     * @return array<string, int|string>
     */
    private static function urlParameters(array $values, string $what): array
    {
        $clean = [];

        foreach ($values as $name => $value) {
            if (!\is_string($value) && !\is_int($value)) {
                throw new ViewException(
                    "The URL {$what} '{$name}' holds a " . get_debug_type($value)
                    . '. A URL carries strings and whole numbers.',
                );
            }

            $clean[(string) $name] = $value;
        }

        return $clean;
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
        // The two parameters are named so oddly because they are variables in
        // the template's scope for as long as they exist. $raRockAdminScope is
        // unset the moment it has been extracted; $raRockAdminTemplateFile has
        // to survive until the require, so a template can see it — which is
        // harmless, and cheaper than the alternatives. What a template must
        // never see is an object it could work backwards from, and the scope
        // test pins that.
        $run = Closure::bind(
            /** @param array<string, mixed> $raRockAdminScope */
            static function (string $raRockAdminTemplateFile, array $raRockAdminScope): void {
                extract($raRockAdminScope, EXTR_OVERWRITE);
                unset($raRockAdminScope);

                require $raRockAdminTemplateFile;
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
