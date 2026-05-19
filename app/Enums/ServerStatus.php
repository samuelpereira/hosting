<?php

namespace App\Enums;

enum ServerStatus: string
{
    case Active = 'active';
    case Canceled = 'canceled';

    public function badgeColor(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Canceled => 'amber',
        };
    }
}
