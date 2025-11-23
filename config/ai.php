<?php


return [
    'engines' => [
        'gpt-3.5' => [
            'name' => 'GPT-3.5 Turbo',
            'provider' => 'openai',
            'url' => env('OPENAI_URL'),
            'key' => env('OPENAI_KEY'),
            'price_per_1k' => 0.0015,
        ],

        // Future support
        // 'deepseek' => [...],
    ]
];