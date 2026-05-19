<?php

namespace App\Enums;

enum ProductType: string
{
    case Dedicated = 'dedicated';
    case Hosting = 'hosting';
    case Reseller = 'reseller';
    case Service = 'service';
    case Vps = 'vps';
}
