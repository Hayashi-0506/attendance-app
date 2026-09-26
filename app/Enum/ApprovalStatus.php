<?php

namespace App\Enum;

enum ApprovalStatus: int
{
    case Pending = false;
    case Approved = true;

    public function label(): string
    {
        return match ($this) {
            self::Pending => '承認待ち',
            self::Approved => '承認済み',
        };
    }
}
