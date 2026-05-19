<?php

/**
 * Map WHMCS products to app catalog slugs.
 *
 * - names: tblproducts.name → slug (exact match, case-insensitive fallback)
 * - ids: tblproducts.id → slug (optional; use when several WHMCS products share one slug)
 *
 * Hosting import resolves packageid via ids/names even when whmcs_id is already taken
 * by another WHMCS product on the same catalog item.
 *
 * @var array{names: array<string, string>, ids?: array<int, string>}
 */
return [
    'names' => [
        //'Web S' => 'web-s',
    ],

    'ids' => [
        1 => 'web-s',
        2 => 'web-m',
        3 => 'web-l',
        4 => 'web-xl',
        5 => 'resell-s',
        6 => 'resell-m',
        7 => 'resell-l',
        8 => 'web-xl',
        10 => 'resell-l',
        11 => 'web-m',
        13 => 'web-l',
        14 => 'web-xl',
        15 => 'server-management',
        16 => 'web-m',
        17 => 'web-l',
        18 => 'web-xl',
        21 => 'vps-2-8-400',
        22 => 'web-mail-5g',
        20 => 'vps-2-8-400',
        23 => 'web-xl-plus-one',
        24 => 'web-xl-plus-two',
        25 => 'vps-2-8-400',
        26 => 'vps-2-8-400',
        27 => 'web-xl-plus-tree',
        28 => 'server-management',
    ],
];
