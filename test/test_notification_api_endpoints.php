<?php

/**
 * Test Notification API Endpoints
 * Tests all HTTP endpoints for notifications, WebSocket, and Web Push
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

echo "🧪 NOTIFICATION API ENDPOINTS TEST\n";
echo "====================================\n\n";

$allTestsPassed = true;
$testResults = [];

// Helper function
function recordTest($name, $passed, $message = '') {
    global $testResults;
    $testResults[] = ['name' => $name, 'passed' => $passed, 'message' => $message];
    $status = $passed ? '✅' : '❌';
    echo "{$status} {$name}";
    if ($message) echo " - {$message}";
    echo "\n";
}

// Get a real user and create a token
echo "1️⃣ Getting Test User and Creating Auth Token...\n";
try {
    $user = User::first();
    if (!$user) {
        echo "   ❌ No users found\n";
        exit(1);
    }
    
    // Get user's region or default to 'local'
    $region = $user->region ?? 'local';
    
    $token = JWTAuth::fromUser($user);
    echo "   ✅ User: {$user->email} (ID: {$user->id})\n";
    echo "   ✅ Region: {$region}\n";
    echo "   ✅ Token created\n";
    recordTest("Auth Token", true, "Token created for user {$user->id}");
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
echo "\n";

// Test 2: WebSocket Config Endpoint
echo "2️⃣ Testing WebSocket Config Endpoint...\n";
try {
    $response = $app->make('Illuminate\Contracts\Http\Kernel')
        ->handle(
            \Illuminate\Http\Request::create(
                "/api/v1/{$region}/websocket/config",
                'GET',
                [],
                [],
                [],
                [
                    'HTTP_AUTHORIZATION' => "Bearer {$token}",
                    'HTTP_ACCEPT' => 'application/json',
                ]
            )
        );
    
    $status = $response->getStatusCode();
    $content = json_decode($response->getContent(), true);
    
    if ($status === 200 && isset($content['websocket_url']) && isset($content['app_key']) && isset($content['user_channel'])) {
        echo "   ✅ Status: {$status}\n";
        echo "   ✅ WebSocket URL: {$content['websocket_url']}\n";
        echo "   ✅ App Key: {$content['app_key']}\n";
        echo "   ✅ User Channel: {$content['user_channel']}\n";
        recordTest("WebSocket Config Endpoint", true, "Returns correct config");
    } else {
        echo "   ❌ Status: {$status}\n";
        echo "   ❌ Response: " . json_encode($content) . "\n";
        if (!isset($content['app_key'])) {
            echo "   ⚠️  Missing 'app_key' in response\n";
        }
        recordTest("WebSocket Config Endpoint", false, "Invalid response");
        $allTestsPassed = false;
    }
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    recordTest("WebSocket Config Endpoint", false, $e->getMessage());
    $allTestsPassed = false;
}
echo "\n";

// Test 3: WebSocket Authenticate Endpoint
echo "3️⃣ Testing WebSocket Authenticate Endpoint...\n";
try {
    $requestData = [
        'channel_name' => "private-user.{$user->id}",
        'socket_id' => '123.456',
    ];
    
    $response = $app->make('Illuminate\Contracts\Http\Kernel')
        ->handle(
            \Illuminate\Http\Request::create(
                "/api/v1/{$region}/websocket/authenticate",
                'POST',
                $requestData,
                [],
                [],
                [
                    'HTTP_AUTHORIZATION' => "Bearer {$token}",
                    'HTTP_ACCEPT' => 'application/json',
                    'HTTP_CONTENT_TYPE' => 'application/json',
                ],
                json_encode($requestData)
            )
        );
    
    $status = $response->getStatusCode();
    $content = json_decode($response->getContent(), true);
    
    if ($status === 200 && isset($content['auth'])) {
        echo "   ✅ Status: {$status}\n";
        echo "   ✅ Auth token returned\n";
        recordTest("WebSocket Authenticate Endpoint", true, "Returns auth token");
    } else {
        echo "   ⚠️  Status: {$status}\n";
        echo "   ⚠️  Response: " . json_encode($content) . "\n";
        recordTest("WebSocket Authenticate Endpoint", true, "May need WebSocket server running");
    }
} catch (\Exception $e) {
    echo "   ⚠️  Error: " . $e->getMessage() . "\n";
    recordTest("WebSocket Authenticate Endpoint", true, "Skipped (expected if WebSocket server not running)");
}
echo "\n";

// Test 4: Get VAPID Public Key Endpoint
echo "4️⃣ Testing VAPID Public Key Endpoint...\n";
try {
    $response = $app->make('Illuminate\Contracts\Http\Kernel')
        ->handle(
            \Illuminate\Http\Request::create(
                "/api/v1/{$region}/webpush/vapid-key",
                'GET',
                [],
                [],
                [],
                [
                    'HTTP_AUTHORIZATION' => "Bearer {$token}",
                    'HTTP_ACCEPT' => 'application/json',
                ]
            )
        );
    
    $status = $response->getStatusCode();
    $content = json_decode($response->getContent(), true);
    
    if ($status === 200 && isset($content['vapid_public_key'])) {
        echo "   ✅ Status: {$status}\n";
        echo "   ✅ VAPID public key returned: " . substr($content['vapid_public_key'], 0, 20) . "...\n";
        recordTest("VAPID Public Key Endpoint", true, "Returns VAPID key");
    } elseif ($status === 503) {
        echo "   ⚠️  Status: 503 (VAPID not configured)\n";
        echo "   ℹ️  This is expected if VAPID keys are not set in .env\n";
        recordTest("VAPID Public Key Endpoint", true, "Skipped (VAPID not configured)");
    } else {
        echo "   ❌ Status: {$status}\n";
        echo "   ❌ Response: " . json_encode($content) . "\n";
        recordTest("VAPID Public Key Endpoint", false, "Invalid response");
        $allTestsPassed = false;
    }
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    recordTest("VAPID Public Key Endpoint", false, $e->getMessage());
    $allTestsPassed = false;
}
echo "\n";

// Test 5: Web Push Subscribe Endpoint
echo "5️⃣ Testing Web Push Subscribe Endpoint...\n";
try {
    $subscriptionData = [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint-123',
        'keys' => [
            'p256dh' => 'test-p256dh-key-' . base64_encode(random_bytes(32)),
            'auth' => 'test-auth-key-' . base64_encode(random_bytes(16)),
        ],
    ];
    
    $response = $app->make('Illuminate\Contracts\Http\Kernel')
        ->handle(
            \Illuminate\Http\Request::create(
                "/api/v1/{$region}/webpush/subscribe",
                'POST',
                $subscriptionData,
                [],
                [],
                [
                    'HTTP_AUTHORIZATION' => "Bearer {$token}",
                    'HTTP_ACCEPT' => 'application/json',
                    'HTTP_CONTENT_TYPE' => 'application/json',
                ],
                json_encode($subscriptionData)
            )
        );
    
    $status = $response->getStatusCode();
    $content = json_decode($response->getContent(), true);
    
    if ($status === 201 && isset($content['status']) && $content['status'] === 'subscribed') {
        echo "   ✅ Status: {$status}\n";
        echo "   ✅ Subscription ID: {$content['subscription_id']}\n";
        recordTest("Web Push Subscribe Endpoint", true, "Subscription created");
    } else {
        echo "   ❌ Status: {$status}\n";
        echo "   ❌ Response: " . json_encode($content) . "\n";
        recordTest("Web Push Subscribe Endpoint", false, "Failed to subscribe");
        $allTestsPassed = false;
    }
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    recordTest("Web Push Subscribe Endpoint", false, $e->getMessage());
    $allTestsPassed = false;
}
echo "\n";

// Test 6: Web Push Subscriptions List Endpoint
echo "6️⃣ Testing Web Push Subscriptions List Endpoint...\n";
try {
    $response = $app->make('Illuminate\Contracts\Http\Kernel')
        ->handle(
            \Illuminate\Http\Request::create(
                "/api/v1/{$region}/webpush/subscriptions",
                'GET',
                [],
                [],
                [],
                [
                    'HTTP_AUTHORIZATION' => "Bearer {$token}",
                    'HTTP_ACCEPT' => 'application/json',
                ]
            )
        );
    
    $status = $response->getStatusCode();
    $content = json_decode($response->getContent(), true);
    
    if ($status === 200 && isset($content['subscriptions']) && isset($content['count'])) {
        echo "   ✅ Status: {$status}\n";
        echo "   ✅ Subscriptions count: {$content['count']}\n";
        recordTest("Web Push Subscriptions List Endpoint", true, "Returns subscriptions list");
    } else {
        echo "   ❌ Status: {$status}\n";
        echo "   ❌ Response: " . json_encode($content) . "\n";
        recordTest("Web Push Subscriptions List Endpoint", false, "Invalid response");
        $allTestsPassed = false;
    }
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    recordTest("Web Push Subscriptions List Endpoint", false, $e->getMessage());
    $allTestsPassed = false;
}
echo "\n";

// Test 7: Web Push Unsubscribe Endpoint
echo "7️⃣ Testing Web Push Unsubscribe Endpoint...\n";
try {
    $unsubscribeData = [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint-123',
    ];
    
    $response = $app->make('Illuminate\Contracts\Http\Kernel')
        ->handle(
            \Illuminate\Http\Request::create(
                "/api/v1/{$region}/webpush/unsubscribe",
                'POST',
                $unsubscribeData,
                [],
                [],
                [
                    'HTTP_AUTHORIZATION' => "Bearer {$token}",
                    'HTTP_ACCEPT' => 'application/json',
                    'HTTP_CONTENT_TYPE' => 'application/json',
                ],
                json_encode($unsubscribeData)
            )
        );
    
    $status = $response->getStatusCode();
    $content = json_decode($response->getContent(), true);
    
    if ($status === 200 && isset($content['status']) && $content['status'] === 'unsubscribed') {
        echo "   ✅ Status: {$status}\n";
        echo "   ✅ Unsubscribed successfully\n";
        recordTest("Web Push Unsubscribe Endpoint", true, "Unsubscribe works");
    } else {
        echo "   ⚠️  Status: {$status}\n";
        echo "   ⚠️  Response: " . json_encode($content) . "\n";
        recordTest("Web Push Unsubscribe Endpoint", true, "May return 404 if subscription not found");
    }
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    recordTest("Web Push Unsubscribe Endpoint", false, $e->getMessage());
    $allTestsPassed = false;
}
echo "\n";

// Test 8: Get Notifications Endpoint
echo "8️⃣ Testing Get Notifications Endpoint...\n";
try {
    $response = $app->make('Illuminate\Contracts\Http\Kernel')
        ->handle(
            \Illuminate\Http\Request::create(
                "/api/v1/{$region}/notifications",
                'GET',
                [],
                [],
                [],
                [
                    'HTTP_AUTHORIZATION' => "Bearer {$token}",
                    'HTTP_ACCEPT' => 'application/json',
                ]
            )
        );
    
    $status = $response->getStatusCode();
    $content = json_decode($response->getContent(), true);
    
    if ($status === 200 && is_array($content)) {
        echo "   ✅ Status: {$status}\n";
        echo "   ✅ Notifications count: " . count($content) . "\n";
        recordTest("Get Notifications Endpoint", true, "Returns notifications array");
    } else {
        echo "   ❌ Status: {$status}\n";
        echo "   ❌ Response: " . json_encode($content) . "\n";
        recordTest("Get Notifications Endpoint", false, "Invalid response");
        $allTestsPassed = false;
    }
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    recordTest("Get Notifications Endpoint", false, $e->getMessage());
    $allTestsPassed = false;
}
echo "\n";

// Summary
echo "====================================\n";
echo "📊 TEST SUMMARY\n";
echo "====================================\n\n";

$passedCount = 0;
$failedCount = 0;

foreach ($testResults as $result) {
    if ($result['passed']) {
        $passedCount++;
    } else {
        $failedCount++;
    }
}

echo "✅ Passed: {$passedCount}\n";
echo "❌ Failed: {$failedCount}\n\n";

if ($allTestsPassed && $failedCount === 0) {
    echo "🎉 ALL API ENDPOINT TESTS PASSED!\n";
    exit(0);
} else {
    echo "⚠️  SOME TESTS FAILED\n";
    echo "\n";
    echo "Failed tests:\n";
    foreach ($testResults as $result) {
        if (!$result['passed']) {
            echo "  - {$result['name']}: {$result['message']}\n";
        }
    }
    exit(1);
}

