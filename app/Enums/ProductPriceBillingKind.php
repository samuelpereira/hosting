<?php

namespace App\Enums;

enum ProductPriceBillingKind: string
{
    case OneTime = 'one_time';
    case Recurring = 'recurring';
}
