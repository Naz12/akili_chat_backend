<?php

/**
 * Test WebSocket and Web Push API endpoints
 * 
 * Usage: php test_websocket_webpush.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Http;

// Configuration
$baseUrl = env('APP_URL', 'http://localhost:8000');
$region = 'local'; // or 'intl'

echo "🧪 Testing WebSocket and Web Push API\n";
echo "=====================================\n\n";

// Helper function to create authenticated request
function createAuthRequest($method, $url, $token) {
    return \Illuminate\Http\Request::create($url, $method, [], [], [], [
        'HTTP_Authorization' => 'Bearer ' . $token,
        'HTTP_Accept' => 'application/json',
        'HTTP_Content-Type' => 'application/json',
    ]);
}

// Get a test user (you can modify this to use a specific user)
$user = User::where('email', '!=', '')->first();

if (!$user) {
    echo "❌ No users found. Please create a user first.\n";
    exit(1);
}

echo "✅ Using user: {$user->email} (ID: {$user->id})\n\n";

// Login to get token
echo "1️⃣ Logging in...\n";
$loginResponse = Http::post("{$baseUrl}/api/v1/login", [
    'email' => $user->email,
    'password' => 'password', // Change this to your test user's password
]);

if (!$loginResponse->successful()) {
    echo "❌ Login failed: " . $loginResponse->body() . "\n";
    exit(1);
}

$loginData = $loginResponse->json();
$token = $loginData['access_token'] ?? $loginData['token'] ?? null;

if (!$token) {
    echo "❌ No token received from login\n";
    exit(1);
}

echo "✅ Login successful\n\n";

// Test WebSocket Config
echo "2️⃣ Testing WebSocket Config Endpoint...\n";
$wsConfigRequest = createAuthRequest('GET', "/api/v1/{$region}/websocket/config", $token);
$wsConfigController = app(\App\Http\Controllers\Api\WebSocketApiController::class);
$wsConfigResponse = $wsConfigController->config($wsConfigRequest);
$wsConfigData = json_decode($wsConfigResponse->getContent(), true);

if ($wsConfigResponse->getStatusCode() === 200) {
    echo "✅ WebSocket config retrieved:\n";
    echo "   - WebSocket URL: " . ($wsConfigData['websocket_url'] ?? 'N/A') . "\n";
    echo "   - User ID: " . ($wsConfigData['user_id'] ?? 'N/A') . "\n";
    echo "   - Channel: " . ($wsConfigData['channel'] ?? 'N/A') . "\n";
} else {
    echo "❌ Failed: " . $wsConfigResponse->getContent() . "\n";
}
echo "\n";

// Test Web Push Subscribe
echo "3️⃣ Testing Web Push Subscribe Endpoint...\n";
$webPushSubscribeRequest = createAuthRequest('POST', "/api/v1/{$region}/webpush/subscribe", $token);
$webPushSubscribeRequest->merge([
    'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint-123',
    'keys' => [
        'p256dh' => 'test-p256dh-key-' . time(),
        'auth' => 'test-auth-key-' . time(),
    ],
]);
$webPushController = app(\App\Http\Controllers\Api\WebPushApiController::class);
$webPushSubscribeResponse = $webPushController->subscribe($webPushSubscribeRequest);
$webPushSubscribeData = json_decode($webPushSubscribeResponse->getContent(), true);

if ($webPushSubscribeResponse->getStatusCode() === 201) {
    echo "✅ Web Push subscription created:\n";
    echo "   - Subscription ID: " . ($webPushSubscribeData['subscription_id'] ?? 'N/A') . "\n";
    echo "   - Status: " . ($webPushSubscribeData['status'] ?? 'N/A') . "\n";
    $subscriptionId = $webPushSubscribeData['subscription_id'] ?? null;
} else {
    echo "❌ Failed: " . $webPushSubscribeResponse->getContent() . "\n";
    $subscriptionId = null;
}
echo "\n";

// Test Web Push Subscriptions List
echo "4️⃣ Testing Web Push Subscriptions List...\n";
$webPushListRequest = createAuthRequest('GET', "/api/v1/{$region}/webpush/subscriptions", $token);
$webPushListResponse = $webPushController->subscriptions($webPushListRequest);
$webPushListData = json_decode($webPushListResponse->getContent(), true);

if ($webPushListResponse->getStatusCode() === 200) {
    echo "✅ Web Push subscriptions retrieved:\n";
    echo "   - Count: " . ($webPushListData['count'] ?? 0) . "\n";
    if (isset($webPushListData['subscriptions']) && count($webPushListData['subscriptions']) > 0) {
        foreach ($webPushListData['subscriptions'] as $sub) {
            echo "   - Subscription ID: {$sub['id']}, Endpoint: " . substr($sub['endpoint'], 0, 50) . "...\n";
        }
    }
} else {
    echo "❌ Failed: " . $webPushListResponse->getContent() . "\n";
}
echo "\n";

// Test WebSocket Authentication
echo "5️⃣ Testing WebSocket Authentication...\n";
$wsAuthRequest = createAuthRequest('POST', "/api/v1/{$region}/websocket/authenticate", $token);
$wsAuthRequest->merge([
    'channel_name' => "private-user.{$user->id}",
    'socket_id' => 'test-socket-id-' . time(),
]);
$wsAuthResponse = $wsConfigController->authenticate($wsAuthRequest);
$wsAuthData = json_decode($wsAuthResponse->getContent(), true);

if ($wsAuthResponse->getStatusCode() === 200) {
    echo "✅ WebSocket authentication successful:\n";
    echo "   - Auth token received: " . (isset($wsAuthData['auth']) ? 'Yes' : 'No') . "\n";
} else {
    echo "❌ Failed: " . $wsAuthResponse->getContent() . "\n";
}
echo "\n";

// Test sending notification via WebSocket
echo "6️⃣ Testing WebSocket Notification Service...\n";
try {
    $webSocketService = app(\App\Services\WebSocket\WebSocketService::class);
    $result = $webSocketService->sendNotification($user, [
        'title' => 'Test Notification',
        'message' => 'This is a test notification via WebSocket',
        'type' => 'test',
    ]);
    
    if ($result) {
        echo "✅ WebSocket notification sent successfully\n";
    } else {
        echo "⚠️ WebSocket notification may have failed (check logs)\n";
    }
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test sending notification via Web Push
echo "7️⃣ Testing Web Push Notification Service...\n";
try {
    $webPushService = app(\App\Services\WebPush\WebPushService::class);
    $results = $webPushService->sendToUser($user, 'Test Notification', 'This is a test notification via Web Push');
    
    if (!empty($results)) {
        echo "✅ Web Push notification queued:\n";
        foreach ($results as $subId => $result) {
            echo "   - Subscription {$subId}: " . ($result['status'] ?? 'unknown') . "\n";
        }
    } else {
        echo "⚠️ No active Web Push subscriptions found (this is expected if no real subscription exists)\n";
    }
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "   (This is expected if VAPID keys are not configured)\n";
}
echo "\n";

// Test Notification Service with new channels
echo "8️⃣ Testing Notification Service with WebSocket and WebPush channels...\n";
try {
    $notificationService = app(\App\Services\Notification\NotificationService::class);
    $testNotification = new \App\Notifications\ChatSessionSharedNotification(
        \App\Models\ChatSessionShare::first() ?? new \App\Models\ChatSessionShare()
    );
    
    // This will fail if there's no ChatSessionShare, but that's okay for testing
    if (\App\Models\ChatSessionShare::exists()) {
        $results = $notificationService->send($user, $testNotification, [
            \App\Services\Notification\NotificationService::CHANNEL_DATABASE,
            \App\Services\Notification\NotificationService::CHANNEL_WEBSOCKET,
            \App\Services\Notification\NotificationService::CHANNEL_WEBPUSH,
        ]);
        
        echo "✅ Notification sent via multiple channels:\n";
        foreach ($results as $channel => $result) {
            $status = $result['success'] ?? false ? '✅' : '❌';
            echo "   {$status} {$channel}: " . ($result['success'] ? 'Success' : ($result['error'] ?? 'Failed')) . "\n";
        }
    } else {
        echo "⚠️ Skipped: No ChatSessionShare found to test with\n";
    }
} catch (\Exception $e) {
    echo "⚠️ Error: " . $e->getMessage() . "\n";
    echo "   (This may be expected if there's no test data)\n";
}
echo "\n";

echo "✅ Testing complete!\n";
echo "\n";
echo "📝 Notes:\n";
echo "   - Web Push requires VAPID keys to be configured in .env\n";
echo "   - WebSocket requires a WebSocket server (e.g., Soketi, Laravel Echo Server)\n";
echo "   - Real Web Push subscriptions require HTTPS and user permission\n";
echo "   - WebSocket connections require a WebSocket client library (e.g., Laravel Echo)\n";

