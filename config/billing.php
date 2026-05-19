<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default tax rates (percent)
    |--------------------------------------------------------------------------
    |
    | Used when seeding billing_tax_settings. Clients with vat_exception or
    | irs_exception skip the corresponding rate on invoices.
    |
    */
    'default_vat_rate_percent' => (float) env('BILLING_DEFAULT_VAT_RATE_PERCENT', 23),

    'default_irs_rate_percent' => (float) env('BILLING_DEFAULT_IRS_RATE_PERCENT', 11.5),

    /*
    |--------------------------------------------------------------------------
    | Domain lines: IRS exception
    |--------------------------------------------------------------------------
    |
    | When true, domain order lines never have IRS retention applied (VAT still
    | applies per client settings).
    |
    */
    'domains_irs_exception' => (bool) env('BILLING_DOMAINS_IRS_EXCEPTION', true),

    /*
    |--------------------------------------------------------------------------
    | Company details (invoicing / emails)
    |--------------------------------------------------------------------------
    */
    'company' => [
        'name' => env('BILLING_COMPANY_NAME'),
        'address_line' => env('BILLING_COMPANY_ADDRESS'),
        'postal_code' => env('BILLING_COMPANY_POSTAL_CODE'),
        'city' => env('BILLING_COMPANY_CITY'),
        'country' => env('BILLING_COMPANY_COUNTRY'),
        'phone' => env('BILLING_COMPANY_PHONE'),
        'email' => env('BILLING_COMPANY_EMAIL'),
        'bank_account' => env('BILLING_COMPANY_BANK_ACCOUNT'),
    ],

    /*
    |--------------------------------------------------------------------------
    | User details
    |--------------------------------------------------------------------------
    */
    'user' => [
        'name' => env('USER_NAME'),
        'email' => env('USER_EMAIL'),
        'password' => env('USER_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Outbound billing emails (queue)
    |--------------------------------------------------------------------------
    */
    'mail' => [
        'queue' => env('BILLING_MAIL_QUEUE', 'default'),
    ],

];
