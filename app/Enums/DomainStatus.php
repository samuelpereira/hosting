<?php

namespace App\Enums;

enum DomainStatus: string
{
    case Active = 'active';
    case Pending = 'pending';
    case PendingTransfer = 'pending_transfer';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Fraud = 'fraud';
    case TransferredAway = 'transferred_away';

    public static function fromWhmcs(string $value): self
    {
        return self::tryFromWhmcs($value) ?? self::Pending;
    }

    public static function tryFromWhmcs(string $value): ?self
    {
        $normalized = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($value)) ?? ''), '_');

        if ($normalized === '') {
            return null;
        }

        return match ($normalized) {
            'active' => self::Active,
            'pending', 'pending_registration' => self::Pending,
            'pending_transfer' => self::PendingTransfer,
            'expired' => self::Expired,
            'cancelled', 'canceled' => self::Cancelled,
            'fraud' => self::Fraud,
            'transferred_away' => self::TransferredAway,
            'grace', 'grace_period' => self::Active,
            'redemption', 'redemption_period' => self::Expired,
            default => null,
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Pending => 'amber',
            self::PendingTransfer => 'sky',
            self::Expired => 'orange',
            self::Cancelled, self::Fraud => 'red',
            self::TransferredAway => 'indigo',
        };
    }

    public function isEligibleForRenewalOrder(): bool
    {
        return match ($this) {
            self::Active, self::Expired => true,
            default => false,
        };
    }
}
