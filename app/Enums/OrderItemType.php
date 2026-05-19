<?php

namespace App\Enums;

enum OrderItemType: string
{
    case Domain = 'domain';
    case Manual = 'manual';
    case Service = 'service';
}
