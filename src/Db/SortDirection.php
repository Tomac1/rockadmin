<?php

declare(strict_types=1);

namespace RockAdmin\Db;

enum SortDirection: string
{
    case Asc = 'asc';
    case Desc = 'desc';

    public function keyword(): string
    {
        return match ($this) {
            self::Asc => 'ASC',
            self::Desc => 'DESC',
        };
    }
}
