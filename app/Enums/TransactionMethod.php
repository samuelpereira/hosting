<?php

namespace App\Enums;

enum TransactionMethod: string
{
    case BankTransfer = 'banktransfer';
    case MBWay = 'mbway';
    case PayPal = 'paypal';

    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => __('BankTransfer'),
            self::MBWay => __('MBWay'),
            self::PayPal => __('PayPal'),
        };
    }

    public static function tryFromWhmcs(?string $value): ?self
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $normalized = strtolower(preg_replace('/[^a-z0-9]+/i', '', $value) ?? '');

        return match ($normalized) {
            'paypal', 'paypalcheckout', 'paypalpayments' => self::PayPal,
            'mbway' => self::MBWay,
            'banktransfer', 'transfer' => self::BankTransfer,
            default => null,
        };
    }
}
