<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'bank' => [
        'url' => env('BCA_API_URL'),
        'port' => env('BCA_BANK_API_PORT'),
        'client' => env('BCA_BANK_CLIENT_ID'),
        'secret' => env('BCA_BANK_CLIENT_SECRET'),
        'channel' => env('BCA_BANK_CHANNEL_ID'),
        'partner' => env('BCA_BANK_PARTNER_ID')
    ],
    'va' => [
        'url' => env('BCA_API_URL'),
        'port' => env('BCA_VA_API_PORT'),
        'client' => env('BCA_VA_CLIENT_ID'),
        'secret' => env('BCA_VA_CLIENT_SECRET'),
        'channel' => env('BCA_VA_CHANNEL_ID'),
        'partner' => env('BCA_VA_PARTNER_ID')
    ],
    'next_trans' => [
        'url' => env('NEXT_TRANS_URL'),
        'port' => env('NEXT_TRANS_PORT'),
        'client' => env('NEXT_TRANS_CLIENT_ID'),
        'secret' => env('NEXT_TRANS_CLIENT_SECRET')
    ],
];
