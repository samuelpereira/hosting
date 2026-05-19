<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Draft = 'draft';
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Canceled = 'canceled';

    public function badgeColor(): string
    {
        return match ($this) {
            self::Paid => 'green',
            self::Unpaid => 'amber',
            self::Draft => 'sky',
            self::Canceled => 'red',
        };
    }
}
