<?php

declare(strict_types=1);

namespace RockAdmin\Db;

enum JoinType: string
{
    case Left = 'left';
    case Inner = 'inner';

    public function keyword(): string
    {
        return match ($this) {
            self::Left => 'LEFT JOIN',
            self::Inner => 'INNER JOIN',
        };
    }
}
