<?php

namespace App\Enum;

enum AdminStatus: int
{
    case General = 0;
    case Admin = 1;

    public function label(): string
    {
        return match ($this) {
            self::General => '一般ユーザー',
            self::Admin => '管理者',
        };
    }
}
