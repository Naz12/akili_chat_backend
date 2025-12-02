<?php

/**
 * Verify .env Configuration for WebSocket/Soketi
 * Run with: php verify-env-config.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔍 VERIFYING .ENV CONFIGURATION\n";
echo "================================\n\n";

$required = [
    'WEBSOCKET_URL' => [
        'expected' => 'wss://chat.akmicroservice.com',
        'description' => 'WebSocket base URL (without /app/ path)',
        'required' => false, // Has default
    ],
    'SOKETI_APP_ID' => [
        'expected' => 'akili-chat',
        'description' => 'Soketi App ID',
        'required' => false, // Has default
    ],
    'SOKETI_APP_KEY' => [
        'expected' => 'akili-chat-key',
        'description' => 'Soketi App Key (must match service file)',
        'required' => false, // Has default
    ],
    'SOKETI_APP_SECRET' => [
        'expected' => 'akili-chat-secret',
        'description' => 'Soketi App Secret (must match service file)',
        'required' => false, // Has default
    ],
    'BROADCAST_DRIVER' => [
        'expected' => 'redis',
        'description' => 'Laravel Broadcasting driver',
        'required' => false, // Has default
    ],
];

$optional = [
    'VAPID_PUBLIC_KEY' => 'Web Push VAPID public key (optional)',
    'VAPID_PRIVATE_KEY' => 'Web Push VAPID private key (optional)',
    'VAPID_SUBJECT' => 'Web Push VAPID subject (optional)',
];

echo "📋 REQUIRED VARIABLES:\n";
echo "----------------------\n\n";

$allGood = true;
foreach ($required as $key => $config) {
    $value = env($key);
    $expected = $config['expected'];
    $hasValue = !empty($value);
    $matches = $value === $expected;
    
    if ($hasValue && $matches) {
        echo "✅ {$key}\n";
        echo "   Value: {$value}\n";
        echo "   Status: Correct\n";
    } elseif ($hasValue && !$matches) {
        echo "⚠️  {$key}\n";
        echo "   Current: {$value}\n";
        echo "   Expected: {$expected}\n";
        echo "   Status: Different value (may still work)\n";
        $allGood = false;
    } else {
        echo "ℹ️  {$key}\n";
        echo "   Value: (not set, using default)\n";
        echo "   Default: {$expected}\n";
        echo "   Status: Using default (OK)\n";
    }
    echo "   Description: {$config['description']}\n";
    echo "\n";
}

echo "\n📋 OPTIONAL VARIABLES:\n";
echo "----------------------\n\n";

foreach ($optional as $key => $description) {
    $value = env($key);
    if (!empty($value)) {
        $displayValue = strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value;
        echo "✅ {$key}\n";
        echo "   Value: {$displayValue}\n";
        echo "   Status: Configured\n";
    } else {
        echo "ℹ️  {$key}\n";
        echo "   Value: (not set)\n";
        echo "   Status: Optional - not required\n";
    }
    echo "   Description: {$description}\n";
    echo "\n";
}

echo "\n🔍 VERIFICATION SUMMARY:\n";
echo "----------------------\n\n";

// Check if values match service file
$serviceFile = __DIR__ . '/akili-websocket.service';
if (file_exists($serviceFile)) {
    $serviceContent = file_get_contents($serviceFile);
    
    echo "📄 Checking against service file:\n";
    
    $checks = [
        'SOKETI_APP_ID' => 'akili-chat',
        'SOKETI_APP_KEY' => 'akili-chat-key',
        'SOKETI_APP_SECRET' => 'akili-chat-secret',
    ];
    
    foreach ($checks as $key => $expected) {
        $envValue = env($key, $expected);
        $inService = str_contains($serviceContent, "{$key}={$expected}") || 
                     str_contains($serviceContent, "-e {$key}={$expected}");
        
        if ($inService) {
            echo "   ✅ {$key} matches service file\n";
        } else {
            echo "   ⚠️  {$key} may not match service file\n";
            echo "      Env: {$envValue}\n";
            echo "      Service: Check akili-websocket.service\n";
            $allGood = false;
        }
    }
} else {
    echo "   ⚠️  Service file not found\n";
}

echo "\n";

// Check WebSocket URL format
$wsUrl = env('WEBSOCKET_URL', 'wss://chat.akmicroservice.com');
if (str_contains($wsUrl, '/app/')) {
    echo "⚠️  WEBSOCKET_URL contains '/app/' path\n";
    echo "   Current: {$wsUrl}\n";
    echo "   Should be: " . preg_replace('/\/app\/?$/', '', $wsUrl) . "\n";
    echo "   (Pusher.js adds /app/ automatically)\n";
    $allGood = false;
} else {
    echo "✅ WEBSOCKET_URL format is correct\n";
}

echo "\n";

if ($allGood) {
    echo "✅ ALL CONFIGURATIONS LOOK GOOD!\n";
    echo "\n";
    echo "Next steps:\n";
    echo "  1. Clear config cache: php artisan config:clear\n";
    echo "  2. Restart Soketi: sudo systemctl restart akili-websocket\n";
    echo "  3. Test: php test/test_websocket_live_connection.php\n";
} else {
    echo "⚠️  SOME ISSUES FOUND\n";
    echo "\n";
    echo "Please review the warnings above and update your .env file if needed.\n";
}

echo "\n";

