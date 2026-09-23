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
            $class = trim($class);

            if ($class !== '') {
                $names[] = $class;
            }
        }

        return implode(' ', array_values(array_unique($names)));
    }

    /** One class: 'ra-<structural>-<identity>', validated the same way. */
    public static function identity(string $structural, string $identity): string
    {
        return 'ra-' . self::segment($structural) . '-' . self::segment($identity);
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
