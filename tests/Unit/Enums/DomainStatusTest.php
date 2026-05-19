<?php

use App\Enums\DomainStatus;

test('maps WHMCS domain status strings', function (string $whmcs, DomainStatus $expected) {
    expect(DomainStatus::fromWhmcs($whmcs))->toBe($expected);
})->with([
    ['Active', DomainStatus::Active],
    ['Pending', DomainStatus::Pending],
    ['Grace', DomainStatus::Active],
    ['Grace Period', DomainStatus::Active],
    ['Transferred Away', DomainStatus::TransferredAway],
    ['Cancelled', DomainStatus::Cancelled],
    ['Expired', DomainStatus::Expired],
]);
