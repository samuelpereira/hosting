<?php

namespace App\Enums;

enum ServiceStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    public function badgeColor(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Pending => 'amber',
            self::Suspended => 'orange',
            self::Cancelled => 'red',
        };
    }
}
