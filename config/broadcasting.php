<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | This option controls the default broadcaster that will be used by the
    | framework when an event needs to be broadcast. You may set this to
    | any of the connections defined in the "connections" array below.
    |
    | Supported: "pusher", "ably", "redis", "log", "null"
    |
    */

    'default' => env('BROADCAST_DRIVER', 'pusher'),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the broadcast connections that will be used
    | to broadcast events to other systems or over websockets. Samples of
    | each available type of connection are provided inside this array.
    |
    */

    'connections' => [

        'pusher' => [
            'driver' => 'pusher',
            'key' => env('SOKETI_APP_KEY', env('PUSHER_APP_KEY', 'akili-chat-key')),
            'secret' => env('SOKETI_APP_SECRET', env('PUSHER_APP_SECRET', 'akili-chat-secret')),
            'app_id' => env('SOKETI_APP_ID', env('PUSHER_APP_ID', 'akili-chat')),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER', ''),
                'host' => env('PUSHER_HOST', '127.0.0.1'),
                'port' => env('PUSHER_PORT', 6001),
                'scheme' => env('PUSHER_SCHEME', 'http'),
                'encrypted' => false,
                'useTLS' => false,
            ],
            'client_options' => [
                // Guzzle client options
            ],
        ],

        'ably' => [
            'driver' => 'ably',
            'key' => env('ABLY_KEY'),
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
        ],

    ],

];

