<?php

namespace App\Enums;

enum RegistrarTldPriceOperation: string
{
    case Register = 'register';
    case Renew = 'renew';
    case Transfer = 'transfer';
}
