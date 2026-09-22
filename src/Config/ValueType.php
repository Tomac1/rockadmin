<?php

declare(strict_types=1);

namespace RockAdmin\Config;

/**
 * The types a configuration key may declare.
 *
 * Deliberately few: configuration is arrays of scalars, and a richer type
 * system here would be a schema language nobody asked for.
 */
enum ValueType: string
{
    case String = 'string';
    case Int = 'int';
    case Bool = 'bool';
    case Array = 'array';
    case Mixed = 'mixed';
}
