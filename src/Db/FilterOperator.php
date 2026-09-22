<?php

declare(strict_types=1);

namespace RockAdmin\Db;

/**
 * The comparisons a filter may make.
 *
 * An enum rather than a string, because an operator that arrives from a URL
 * as free text is one concatenation away from being a SQL injection.
 */
enum FilterOperator: string
{
    case Equals = 'equals';
    case NotEquals = 'not_equals';
    case Contains = 'contains';
    case StartsWith = 'starts_with';
    case EndsWith = 'ends_with';
    case GreaterThan = 'gt';
    case GreaterOrEqual = 'gte';
    case LessThan = 'lt';
    case LessOrEqual = 'lte';
    case Between = 'between';
    case In = 'in';
    case IsNull = 'is_null';
    case IsNotNull = 'is_not_null';
}
