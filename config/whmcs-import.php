<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WHMCS database connection
    |--------------------------------------------------------------------------
    |
    | Restore your WHMCS SQL dump into this database, then run:
    |   php artisan billing:import-whmcs-clients --with-contacts
    |
    | Or use --file with --prepare to load a .sql file into this database first.
    |
    */
    'connection' => env('WHMCS_DB_CONNECTION', 'whmcs'),

    'clients_table' => env('WHMCS_CLIENTS_TABLE', 'tblclients'),

    'currencies_table' => env('WHMCS_CURRENCIES_TABLE', 'tblcurrencies'),

    'contacts_table' => env('WHMCS_CONTACTS_TABLE', 'tblcontacts'),

    'hosting_table' => env('WHMCS_HOSTING_TABLE', 'tblhosting'),

    'products_table' => env('WHMCS_PRODUCTS_TABLE', 'tblproducts'),

    'domains_table' => env('WHMCS_DOMAINS_TABLE', 'tbldomains'),

    'invoices_table' => env('WHMCS_INVOICES_TABLE', 'tblinvoices'),

    'invoice_items_table' => env('WHMCS_INVOICE_ITEMS_TABLE', 'tblinvoiceitems'),

    'default_product_id' => env('WHMCS_IMPORT_DEFAULT_PRODUCT_ID'),

    /*
    |--------------------------------------------------------------------------
    | WHMCS product name → app product slug
    |--------------------------------------------------------------------------
    |
    | See config/whmcs-product-map.php (names + optional ids). Import links whmcs_id when
    | free; hosting always resolves packageid via map even when slugs are shared.
    |
    */
    'product_name_map' => require __DIR__.'/whmcs-product-map.php',

    /*
    |--------------------------------------------------------------------------
    | Default registrar for WHMCS domain import
    |--------------------------------------------------------------------------
    |
    | Unknown WHMCS registrar codes map to this registrar (no new registrars are
    | created). New TLDs discovered during import are also created under it.
    |
    */
    'default_registrar_id' => env('WHMCS_IMPORT_DEFAULT_REGISTRAR_ID'),

    /*
    |--------------------------------------------------------------------------
    | Column mapping (WHMCS tblclients → app clients)
    |--------------------------------------------------------------------------
    |
    | Keys are WHMCS column names. Adjust if your dump uses different names.
    |
    */
    'columns' => [
        'id' => 'id',
        'firstname' => 'firstname',
        'lastname' => 'lastname',
        'companyname' => 'companyname',
        'email' => 'email',
        'address1' => 'address1',
        'address2' => 'address2',
        'city' => 'city',
        'postcode' => 'postcode',
        'country' => 'country',
        'phonenumber' => 'phonenumber',
        'tax_id' => 'tax_id',
        'taxexempt' => 'taxexempt',
        'currency' => 'currency',
        'status' => 'status',
        'notes' => 'notes',
        'datecreated' => 'datecreated',
    ],

    'default_currency' => env('WHMCS_IMPORT_DEFAULT_CURRENCY', 'EUR'),

    'active_statuses' => ['Active', 'active'],

    'domain_active_statuses' => ['Active', 'active', 'Grace', 'grace'],

    'invoice_paid_statuses' => ['Paid', 'paid'],

    /*
    |--------------------------------------------------------------------------
    | Column mapping (WHMCS tblcontacts → app contacts)
    |--------------------------------------------------------------------------
    */
    'contact_columns' => [
        'id' => 'id',
        'userid' => 'userid',
        'firstname' => 'firstname',
        'lastname' => 'lastname',
        'companyname' => 'companyname',
        'email' => 'email',
        'phonenumber' => 'phonenumber',
        'subaccount' => 'subaccount',
        'invoiceemails' => 'invoiceemails',
        'productemails' => 'productemails',
        'supportemails' => 'supportemails',
        'domainemails' => 'domainemails',
        'generalemails' => 'generalemails',
    ],

    /*
    |--------------------------------------------------------------------------
    | Column mapping (WHMCS tblproducts → app products)
    |--------------------------------------------------------------------------
    */
    'product_columns' => [
        'id' => 'id',
        'type' => 'type',
        'name' => 'name',
        'description' => 'description',
        'hidden' => 'hidden',
    ],

    /*
    |--------------------------------------------------------------------------
    | Column mapping (WHMCS tblhosting → app services)
    |--------------------------------------------------------------------------
    */
    'hosting_columns' => [
        'id' => 'id',
        'userid' => 'userid',
        'packageid' => 'packageid',
        'server' => 'server',
        'regdate' => 'regdate',
        'domain' => 'domain',
        'username' => 'username',
        'password' => 'password',
        'amount' => 'amount',
        'firstpaymentamount' => 'firstpaymentamount',
        'billingcycle' => 'billingcycle',
        'nextduedate' => 'nextduedate',
        'domainstatus' => 'domainstatus',
        'notes' => 'notes',
        'disklimit' => 'disklimit',
        'termination_date' => 'termination_date',
    ],

    /*
    |--------------------------------------------------------------------------
    | Column mapping (WHMCS tbldomains → app domains)
    |--------------------------------------------------------------------------
    */
    'domain_columns' => [
        'id' => 'id',
        'userid' => 'userid',
        'status' => 'status',
        'domain' => 'domain',
        'registrar' => 'registrar',
        'registrationdate' => 'registrationdate',
        'registrationperiod' => 'registrationperiod',
        'expirydate' => 'expirydate',
        'nextduedate' => 'nextduedate',
        'firstpaymentamount' => 'firstpaymentamount',
        'recurringamount' => 'recurringamount',
        'additionalnotes' => 'additionalnotes',
        'donotrenew' => 'donotrenew',
    ],

    /*
    |--------------------------------------------------------------------------
    | Column mapping (WHMCS tblinvoices → app orders)
    |--------------------------------------------------------------------------
    */
    'invoice_columns' => [
        'id' => 'id',
        'userid' => 'userid',
        'date' => 'date',
        'duedate' => 'duedate',
        'datepaid' => 'datepaid',
        'subtotal' => 'subtotal',
        'tax' => 'tax',
        'total' => 'total',
        'status' => 'status',
        'paymentmethod' => 'paymentmethod',
        'notes' => 'notes',
        'invoicenum' => 'invoicenum',
    ],

    /*
    |--------------------------------------------------------------------------
    | Column mapping (WHMCS tblinvoiceitems → app order_items)
    |--------------------------------------------------------------------------
    */
    'invoice_item_columns' => [
        'id' => 'id',
        'invoiceid' => 'invoiceid',
        'type' => 'type',
        'relid' => 'relid',
        'description' => 'description',
        'amount' => 'amount',
    ],

];
