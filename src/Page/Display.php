<?php

declare(strict_types=1);

namespace RockAdmin\Page;

enum Display: string
{
    case Plain = 'plain';
    case Badge = 'badge';
    case Check = 'check';
    case YesNo = 'yesno';
    case Progress = 'progress';
    case Percent = 'percent';
    case Link = 'link';
}
