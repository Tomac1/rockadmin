<?php

declare(strict_types=1);

namespace RockAdmin\View;

/**
 * Assembles the class attribute every element in this admin carries.
 *
 * The convention is a structural class saying what a thing is, an optional
 * identity class saying which one it is, and whatever appearance classes come
 * from Bootstrap or from configuration. One selector then restyles every grid
 * cell in the application; another restyles the price cell on one page.
 */
final class Classes
{
    /** @param list<string> $extra appearance classes, appended in order */
    public static function of(string $structural, ?string $identity = null, array $extra = []): string
    {
        $names = [self::name($structural, 'structural class')];

        if ($identity !== null) {
            $names[] = $names[0] . '-' . self::segment($identity);
        }

        foreach ($extra as $class) {
            foreach (self::appearance($class) as $name) {
                $names[] = $name;
            }
        }

        return implode(' ', array_values(array_unique($names)));
    }

    /** One class: 'ra-<structural>-<identity>', validated the same way. */
    public static function identity(string $structural, string $identity): string
    {
        return 'ra-' . self::segment($structural) . '-' . self::segment($identity);
    }

    /**
     * Appearance classes are held to a looser rule than ra- classes: they are
     * Bootstrap's vocabulary and a project's own, so they may be upper case,
     * may carry a colon or a slash the way a utility framework spells one,
     * and several may arrive in one string the way `'class' => 'text-end
     * fw-bold'` is written in configuration.
     *
     * What they may not do is end the attribute they are written into. A
     * quote, an angle bracket or a control character in a class name has no
     * legitimate spelling and exactly one use, so it is refused rather than
     * escaped — an escaped class attribute is not a class anyone can target.
     *
     * @return list<string>
     */
    private static function appearance(string $value): array
    {
        // The backslash is asked about by its code point rather than joining
        // the character class: spelling one inside a single-quoted PHP string
        // that is also a regular expression takes four of them, and getting
        // that wrong compiles to a pattern which quietly matches nothing.
        if (preg_match('/[\x00-\x1F"\'<>`]/', $value) === 1 || str_contains($value, \chr(92))) {
            throw new ViewException(
                "Refusing '{$value}' as a class: a class name cannot contain a quote, "
                . 'an angle bracket, a backslash or a control character.',
            );
        }

        $names = preg_split('/\s+/', trim($value)) ?: [];

        return array_values(array_filter($names, static fn (string $name): bool => $name !== ''));
    }

    private static function name(string $value, string $what): string
    {
        return 'ra-' . self::segment($value, $what);
    }

    private static function segment(string $value, string $what = 'class name'): string
    {
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) !== 1) {
            throw new ViewException("Refusing '{$value}' as a {$what}: expected kebab-case.");
        }

        return $value;
    }
}
