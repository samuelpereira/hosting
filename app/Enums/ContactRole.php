<?php

namespace App\Enums;

enum ContactRole: string
{
    case Owner = 'owner';
    case Billing = 'billing';
    case Technical = 'technical';
    case Other = 'other';
}
