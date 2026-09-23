<?php

declare(strict_types=1);

namespace RockAdmin\View;

use RuntimeException;

/**
 * Every failure the view layer raises. A template that cannot be found, a
 * value that cannot be escaped, an attribute name that cannot be written.
 */
final class ViewException extends RuntimeException
{
}
