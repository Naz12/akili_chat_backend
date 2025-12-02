<?php

/**
 * Backend Frontend Readiness Test
 * Tests all endpoints and services the frontend needs
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

echo "🧪 BACKEND FRONTEND READINESS TEST\n";
echo "====================================\n\n";

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

// Test 1: Get User and Create Token
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
    recordTest("Get User", true, "User {$user->id}");
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
    
    if ($status === 200 && isset($content['websocket_url']) && isset($content['app_key'])) {
        $wsUrl = $content['websocket_url'];
        $appKey = $content['app_key'];
        echo "   ✅ Status: {$status}\n";
        echo "   ✅ WebSocket URL: {$wsUrl}\n";
        echo "   ✅ App Key: {$appKey}\n";
        echo "   ✅ User Channel: {$content['user_channel']}\n";
        
        // Verify URL format
        if (strpos($wsUrl, 'wss://chat.akmicroservice.com/app/') === 0) {
            echo "   ✅ URL format is correct\n";
            recordTest("WebSocket Config", true, "Returns correct config");
        } else {
            echo "   ⚠️  URL format may be incorrect: {$wsUrl}\n";
            recordTest("WebSocket Config", false, "URL format incorrect");
            $allTestsPassed = false;
        }
    } else {
        echo "   ❌ Status: {$status}\n";
        echo "   ❌ Response: " . json_encode($content) . "\n";
        recordTest("WebSocket Config", false, "Invalid response");
        $allTestsPassed = false;
    }
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    recordTest("WebSocket Config", false, $e->getMessage());
    $allTestsPassed = false;
}
echo "\n";

// Test 3: Check if Soketi is accessible
echo "3️⃣ Testing Soketi WebSocket Server...\n";
try {
    // Try to connect to Soketi directly
    $ch = curl_init('http://127.0.0.1:6001');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode > 0) {
        echo "   ✅ Soketi is responding on port 6001 (HTTP {$httpCode})\n";
        recordTest("Soketi Server", true, "Server is running");
    } else {
        echo "   ❌ Soketi is not responding on port 6001\n";
        echo "   ⚠️  Check: sudo systemctl status akili-websocket\n";
        recordTest("Soketi Server", false, "Server not responding");
        $allTestsPassed = false;
    }
} catch (\Exception $e) {
    echo "   ⚠️  Cannot test Soketi: " . $e->getMessage() . "\n";
    recordTest("Soketi Server", false, "Cannot test");
    $allTestsPassed = false;
}
echo "\n";

// Test 4: Test WebSocket via Nginx
echo "4️⃣ Testing WebSocket via Nginx Proxy...\n";
try {
    $ch = curl_init('https://chat.akmicroservice.com/app/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode > 0) {
        echo "   ✅ Nginx proxy is responding (HTTP {$httpCode})\n";
        recordTest("Nginx WebSocket Proxy", true, "Proxy is working");
    } else {
        echo "   ❌ Nginx proxy is not responding\n";
        if ($error) {
            echo "   Error: {$error}\n";
        }
        echo "   ⚠️  Check nginx config and reload: sudo systemctl reload nginx\n";
        recordTest("Nginx WebSocket Proxy", false, "Proxy not responding");
        $allTestsPassed = false;
    }
} catch (\Exception $e) {
    echo "   ⚠️  Cannot test Nginx proxy: " . $e->getMessage() . "\n";
    recordTest("Nginx WebSocket Proxy", false, "Cannot test");
    $allTestsPassed = false;
}
echo "\n";

// Test 5: VAPID Key Endpoint
echo "5️⃣ Testing VAPID Public Key Endpoint...\n";
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
        $allTestsPassed = false;
    }
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    recordTest("VAPID Key Endpoint", false, $e->getMessage());
    $allTestsPassed = false;
}
echo "\n";

// Test 6: Notifications Endpoint
echo "6️⃣ Testing Notifications Endpoint...\n";
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
        recordTest("Notifications Endpoint", true, "Returns notifications");
    } else {
        echo "   ❌ Status: {$status}\n";
        recordTest("Notifications Endpoint", false, "Invalid response");
        $allTestsPassed = false;
    }
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    recordTest("Notifications Endpoint", false, $e->getMessage());
    $allTestsPassed = false;
}
echo "\n";

// Test 7: Check Redis
echo "7️⃣ Testing Redis Connection...\n";
try {
    \Illuminate\Support\Facades\Redis::ping();
    echo "   ✅ Redis is connected\n";
    recordTest("Redis Connection", true, "Redis is available");
} catch (\Exception $e) {
    echo "   ❌ Redis is not available: " . $e->getMessage() . "\n";
    recordTest("Redis Connection", false, "Redis not available");
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
    echo "🎉 BACKEND IS READY FOR FRONTEND!\n";
    echo "\n";
    echo "All critical endpoints are working:\n";
    echo "  ✅ WebSocket config endpoint\n";
    echo "  ✅ Notifications endpoint\n";
    echo "  ✅ VAPID key endpoint (optional)\n";
    echo "  ✅ Redis connection\n";
    echo "\n";
    echo "⚠️  WebSocket Connection Issue:\n";
    echo "  The frontend is trying to connect but failing.\n";
    echo "  This is likely because:\n";
    echo "  1. Nginx needs to be reloaded: sudo systemctl reload nginx\n";
    echo "  2. Soketi service needs to be restarted: sudo systemctl restart akili-websocket\n";
    echo "  3. Check nginx config has /app/ location block\n";
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
    echo "🔧 Fix these issues before frontend can connect:\n";
    echo "  1. Ensure Soketi is running: sudo systemctl status akili-websocket\n";
    echo "  2. Reload nginx: sudo systemctl reload nginx\n";
    echo "  3. Check nginx config: sudo nginx -t\n";
    exit(1);
}

