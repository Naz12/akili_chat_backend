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

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'fcm' => [
        'server_key' => env('FCM_SERVER_KEY'),
    ],

    'telebirr' => [
        'secret' => env('TELEBIRR_SECRET'),
    ],

    'stripe' => [
        'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
        'secret_key' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'mode' => env('STRIPE_MODE', 'test'),
    ],

    'chapa' => [
        'public_key' => env('CHAPA_PUBLIC_KEY'),
        'secret_key' => env('CHAPA_SECRET_KEY'),
        'webhook_secret' => env('CHAPA_WEBHOOK_SECRET'),
        'mode' => env('CHAPA_MODE', 'test'),
        'base_url' => env('CHAPA_BASE_URL', 'https://api.chapa.co/v1'),
    ],

    'flutterwave' => [
       'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
       'redirect_url' => env('FLUTTERWAVE_REDIRECT_URL'),
       'secret_hash' => env('FLW_SECRET_HASH'),
    ],

    'twilio' => [
    'sid' => env('TWILIO_SID'),
    'token' => env('TWILIO_AUTH_TOKEN'),
    'from' => env('TWILIO_PHONE_NUMBER'),
     ],

    // Brain orchestration microservice endpoints
    'brain' => [
        'transcriber_url' => env('TRANSCRIBER_URL'),
        'doc_service_url' => env('DOC_SERVICE_URL'),
        'hmac_secret' => env('DAGU_HMAC_SECRET'),
        'transcriber_client_key' => env('TRANSCRIBER_CLIENT_KEY'),
        'brightdata_dataset_id' => env('TRANSCRIBER_BRIGHTDATA_DATASET_ID'),
    ],



];