<?php

namespace App\Services\Billing;

use App\Models\BillingTaxSetting;
use App\Models\Client;

class OrderTotalsCalculator
{
    /**
     * @param  list<array{line_subtotal_minor: int, is_domain?: bool}>  $lines
     * @return array{subtotal_minor: int, tax_minor: int, irs_minor: int, total_minor: int}
     */
    public function forClientLines(Client $client, array $lines): array
    {
        $settings = BillingTaxSetting::instance();

        $subtotalMinor = 0;
        $taxMinor = 0;
        $irsMinor = 0;

        foreach ($lines as $line) {
            $lineSubtotalMinor = (int) $line['line_subtotal_minor'];
            $subtotalMinor += $lineSubtotalMinor;
            $isDomainLine = (bool) ($line['is_domain'] ?? false);
            $taxMinor += $settings->vatMinorForLine($client, $lineSubtotalMinor);
            $irsMinor += $settings->irsMinorForLine($client, $lineSubtotalMinor, $isDomainLine);
        }

        return [
            'subtotal_minor' => $subtotalMinor,
            'tax_minor' => $taxMinor,
            'irs_minor' => $irsMinor,
            'total_minor' => $subtotalMinor + $taxMinor - $irsMinor,
        ];
    }
}
