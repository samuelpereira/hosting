<?php

use App\Enums\DomainStatus;
use App\Models\Domain;

test('domain is eligible for automated renewal only with auto renew and active or expired status', function () {
    $domain = new Domain([
        'auto_renew' => true,
        'status' => DomainStatus::Active,
    ]);

    expect($domain->isEligibleForAutomatedRenewal())->toBeTrue();

    $domain->status = DomainStatus::Expired;
    expect($domain->isEligibleForAutomatedRenewal())->toBeTrue();

    $domain->auto_renew = false;
    expect($domain->isEligibleForAutomatedRenewal())->toBeFalse();

    $domain->auto_renew = true;
    $domain->status = DomainStatus::Cancelled;
    expect($domain->isEligibleForAutomatedRenewal())->toBeFalse();
});
