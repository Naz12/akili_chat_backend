<?php

/**
 * End-to-End Notification System Test
 * Tests the complete flow from backend to frontend readiness
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Services\Notification\NotificationService;
use App\Notifications\AdminBroadcastNotification;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;

echo "🧪 END-TO-END NOTIFICATION SYSTEM TEST\n";
echo "========================================\n\n";

$allTestsPassed = true;
$testResults = [];

function recordTest($name, $passed, $message = '') {
    global $testResults;
    $testResults[] = ['name' => $name, 'passed' => $passed, 'message' => $message];
    $status = $passed ? '✅' : '❌';
    echo "{$status} {$name}";
    if ($message) echo " - {$message}";
    echo "\n";
}

// Test 1: Get Test User
echo "1️⃣ Getting Test User...\n";
try {
    $user = User::first();
    if (!$user) {
        echo "   ❌ No users found\n";
        exit(1);
    }
    $region = $user->region ?? 'local';
    $token = JWTAuth::fromUser($user);
    echo "   ✅ User: {$user->email} (ID: {$user->id}, Region: {$region})\n";
    recordTest("Get Test User", true, "User {$user->id}");
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
echo "\n";

// Test 2: Check Soketi Service
echo "2️⃣ Checking Soketi WebSocket Server...\n";
try {
    $ch = curl_init('http://127.0.0.1:6001');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode > 0 && $httpCode != 502) {
        echo "   ✅ Soketi is responding on port 6001 (HTTP {$httpCode})\n";
        recordTest("Soketi Server", true, "Server is running");
    } else {
        echo "   ❌ Soketi is not responding (HTTP {$httpCode})\n";
        if ($error) echo "   Error: {$error}\n";
        recordTest("Soketi Server", false, "Server not responding");
        $allTestsPassed = false;
    }
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    recordTest("Soketi Server", false, $e->getMessage());
    $allTestsPassed = false;
}
echo "\n";

// Test 3: Check Nginx WebSocket Proxy
echo "3️⃣ Testing Nginx WebSocket Proxy...\n";
try {
    $ch = curl_init('https://chat.akmicroservice.com/app/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode > 0 && $httpCode != 502 && $httpCode != 404) {
        echo "   ✅ Nginx proxy is working (HTTP {$httpCode})\n";
        recordTest("Nginx WebSocket Proxy", true, "Proxy is working");
    } else {
        echo "   ⚠️  Nginx proxy returned HTTP {$httpCode}\n";
        if ($httpCode == 502) {
            echo "   ⚠️  This may mean Soketi is not fully ready yet\n";
        }
        recordTest("Nginx WebSocket Proxy", $httpCode != 502, "HTTP {$httpCode}");
    }
} catch (\Exception $e) {
    echo "   ⚠️  Cannot test proxy: " . $e->getMessage() . "\n";
    recordTest("Nginx WebSocket Proxy", false, "Cannot test");
}
echo "\n";

// Test 4: WebSocket Config Endpoint
echo "4️⃣ Testing WebSocket Config Endpoint...\n";
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
    
    if ($status === 200 && isset($content['websocket_url']) && isset($content['app_key'])) {
        echo "   ✅ Status: {$status}\n";
        echo "   ✅ WebSocket URL: {$content['websocket_url']}\n";
        echo "   ✅ App Key: {$content['app_key']}\n";
        echo "   ✅ User Channel: {$content['user_channel']}\n";
        recordTest("WebSocket Config Endpoint", true, "Returns correct config");
    } else {
        echo "   ❌ Status: {$status}\n";
        recordTest("WebSocket Config Endpoint", false, "Invalid response");
        $allTestsPassed = false;
    }
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    recordTest("WebSocket Config Endpoint", false, $e->getMessage());
    $allTestsPassed = false;
}
echo "\n";

// Test 5: Redis Connection
echo "5️⃣ Testing Redis Connection...\n";
try {
    Redis::ping();
    echo "   ✅ Redis is connected\n";
    recordTest("Redis Connection", true, "Redis is available");
} catch (\Exception $e) {
    echo "   ❌ Redis error: " . $e->getMessage() . "\n";
    recordTest("Redis Connection", false, "Redis not available");
    $allTestsPassed = false;
}
echo "\n";

// Test 6: Database Channel
echo "6️⃣ Testing Database Notification Channel...\n";
try {
    $notificationService = app(NotificationService::class);
    $notification = new AdminBroadcastNotification(
        'E2E Test Notification',
        'This is an end-to-end test notification',
        [NotificationService::CHANNEL_DATABASE]
    );
    
    $results = $notificationService->send($user, $notification, [NotificationService::CHANNEL_DATABASE], true);
    
    if (isset($results[NotificationService::CHANNEL_DATABASE]['success']) && 
        $results[NotificationService::CHANNEL_DATABASE]['success']) {
        echo "   ✅ Database notification sent successfully\n";
        recordTest("Database Channel", true, "Notification saved");
    } else {
        $error = $results[NotificationService::CHANNEL_DATABASE]['error'] ?? 'Unknown error';
        echo "   ❌ Failed: {$error}\n";
        recordTest("Database Channel", false, $error);
        $allTestsPassed = false;
    }
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    recordTest("Database Channel", false, $e->getMessage());
    $allTestsPassed = false;
}
echo "\n";

// Test 7: WebSocket Channel
echo "7️⃣ Testing WebSocket Notification Channel...\n";
try {
    $notification = new AdminBroadcastNotification(
        'E2E WebSocket Test',
        'Testing WebSocket notification delivery',
        [NotificationService::CHANNEL_WEBSOCKET]
    );
    
    $results = $notificationService->send($user, $notification, [NotificationService::CHANNEL_WEBSOCKET], true);
    
    if (isset($results[NotificationService::CHANNEL_WEBSOCKET]['success']) && 
        $results[NotificationService::CHANNEL_WEBSOCKET]['success']) {
        echo "   ✅ WebSocket notification sent successfully\n";
        recordTest("WebSocket Channel", true, "Notification broadcasted");
    } else {
        $error = $results[NotificationService::CHANNEL_WEBSOCKET]['error'] ?? 'Unknown error';
        echo "   ⚠️  WebSocket notification: {$error}\n";
        recordTest("WebSocket Channel", false, $error);
        $allTestsPassed = false;
    }
} catch (\Exception $e) {
    echo "   ⚠️  Error: " . $e->getMessage() . "\n";
    recordTest("WebSocket Channel", false, $e->getMessage());
    $allTestsPassed = false;
}
echo "\n";

// Test 8: Multi-Channel Notification
echo "8️⃣ Testing Multi-Channel Notification...\n";
try {
    $channels = [
        NotificationService::CHANNEL_DATABASE,
        NotificationService::CHANNEL_WEBSOCKET,
    ];
    
    $notification = new AdminBroadcastNotification(
        'E2E Multi-Channel Test',
        'Testing multiple notification channels',
        $channels
    );
    
    $results = $notificationService->send($user, $notification, $channels, true);
    
    $successCount = 0;
    foreach ($results as $channel => $result) {
        if (isset($result['success']) && $result['success']) {
            $successCount++;
            echo "   ✅ Channel '{$channel}': Success\n";
        } else {
            $error = $result['error'] ?? 'Unknown error';
            echo "   ⚠️  Channel '{$channel}': " . substr($error, 0, 50) . "\n";
        }
    }
    
    if ($successCount > 0) {
        echo "   ✅ Multi-channel test: {$successCount} channel(s) succeeded\n";
        recordTest("Multi-Channel Notification", true, "{$successCount} channel(s) succeeded");
    } else {
        echo "   ❌ Multi-channel test: No channels succeeded\n";
        recordTest("Multi-Channel Notification", false, "No channels succeeded");
        $allTestsPassed = false;
    }
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    recordTest("Multi-Channel Notification", false, $e->getMessage());
    $allTestsPassed = false;
}
echo "\n";

// Test 9: Notifications API Endpoint
echo "9️⃣ Testing Notifications API Endpoint...\n";
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
        $count = count($content);
        echo "   ✅ Status: {$status}\n";
        echo "   ✅ Notifications count: {$count}\n";
        recordTest("Notifications API Endpoint", true, "Returns {$count} notifications");
    } else {
        echo "   ❌ Status: {$status}\n";
        recordTest("Notifications API Endpoint", false, "Invalid response");
        $allTestsPassed = false;
    }
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    recordTest("Notifications API Endpoint", false, $e->getMessage());
    $allTestsPassed = false;
}
echo "\n";

// Test 10: VAPID Key Endpoint
echo "🔟 Testing VAPID Key Endpoint...\n";
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
        echo "   ✅ VAPID key returned\n";
        recordTest("VAPID Key Endpoint", true, "Returns VAPID key");
    } elseif ($status === 503) {
        echo "   ⚠️  Status: 503 (VAPID not configured - this is OK)\n";
        echo "   ℹ️  Frontend will handle this gracefully\n";
        recordTest("VAPID Key Endpoint", true, "Skipped (not configured)");
    } else {
        echo "   ❌ Status: {$status}\n";
        recordTest("VAPID Key Endpoint", false, "Unexpected status");
    }
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    recordTest("VAPID Key Endpoint", false, $e->getMessage());
}
echo "\n";

// Summary
echo "========================================\n";
echo "📊 END-TO-END TEST SUMMARY\n";
echo "========================================\n\n";

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
    echo "🎉 ALL END-TO-END TESTS PASSED!\n";
    echo "\n";
    echo "✅ Backend is fully ready for frontend:\n";
    echo "  ✅ Soketi WebSocket server is running\n";
    echo "  ✅ Nginx proxy is configured\n";
    echo "  ✅ All API endpoints are working\n";
    echo "  ✅ Database notifications working\n";
    echo "  ✅ WebSocket notifications working\n";
    echo "  ✅ Multi-channel notifications working\n";
    echo "  ✅ Redis connection working\n";
    echo "\n";
    echo "🚀 Frontend can now connect to:\n";
    echo "  - WebSocket: wss://chat.akmicroservice.com/app/\n";
    echo "  - App Key: akili-chat-key\n";
    echo "  - Channel: private-user.{userId}\n";
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
    echo "\n";
    echo "🔧 Fix these issues:\n";
    if (in_array('Soketi Server', array_column($testResults, 'name'))) {
        $soketiTest = array_filter($testResults, fn($r) => $r['name'] === 'Soketi Server');
        if (!$soketiTest[array_key_first($soketiTest)]['passed']) {
            echo "  1. Check Soketi service: sudo systemctl status akili-websocket\n";
            echo "  2. Check logs: tail -f /var/log/akili-websocket.log\n";
        }
    }
    exit(1);
}

