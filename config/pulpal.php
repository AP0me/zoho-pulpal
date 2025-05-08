<?php

return [

    /*
   |--------------------------------------------------------------------------
   | Host
   |--------------------------------------------------------------------------
   |
   | This option defines the environment for using API
   |
   */

    'host' => env('PULPAY_HOST', 'https://payment-api-dev.pulpal.az'),


    /*
    |--------------------------------------------------------------------------
    | Merchant ID
    |--------------------------------------------------------------------------
    |
    | This option defines the Merchant ID in PulPal system
    | You can find this parameter via merchant administration panel
    | ATTENTION!
    | Merchant ID values are different in test and production environments!
    |
    */

    'merchant_id' => env('PULPAL_MERCHANT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Provider ID
    |--------------------------------------------------------------------------
    |
    | This option defines the Provider ID for the payment
    | List of payment providers for merchant can be retrieved from PulPal support
    |
    */

    'provider_id' => env('PULPAL_PROVIDER_ID'),

    /*
    |--------------------------------------------------------------------------
    | API Public Key
    |--------------------------------------------------------------------------
    |
    | This option defines the Authentication Public Key for Top Up Payment
    | You can get this parameter by generating API Key via merchant administration panel
    |
    */

    'api_public_key' => env('PULPAL_API_PUBLIC_KEY'),

    /*
    |--------------------------------------------------------------------------
    | API Private Key
    |--------------------------------------------------------------------------
    |
    | This option defines the Authentication Private Key for Top Up Payment
    | that is used while creating signature
    | You can get this parameter by generating API Key via merchant administration panel
    |
    */

    'api_private_key' => env('PULPAL_API_PRIVATE_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Lang
    |--------------------------------------------------------------------------
    |
    | Language of the payment system
    |
    */

    'lang' => env('PULPAL_LANG')
];