<?php

/**
 * Simple test for WebSocket and Web Push functionality
 * Tests the services directly without requiring HTTP server
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Services\WebSocket\WebSocketService;
use App\Services\WebPush\WebPushService;
use App\Services\Notification\NotificationService;

echo "🧪 Testing WebSocket and Web Push Services\n";
echo "==========================================\n\n";

// Get a test user
$user = User::first();

if (!$user) {
    echo "❌ No users found. Please create a user first.\n";
    exit(1);
}

echo "✅ Using user: {$user->email} (ID: {$user->id})\n\n";

// Test 1: WebSocket Service
echo "1️⃣ Testing WebSocket Service...\n";
try {
    $webSocketService = app(WebSocketService::class);
    
    $result = $webSocketService->sendNotification($user, [
        'title' => 'Test Notification',
        'message' => 'This is a test notification via WebSocket',
        'type' => 'test',
        'timestamp' => now()->toIso8601String(),
    ]);
    
    if ($result) {
        echo "   ✅ WebSocket notification sent successfully\n";
    } else {
        echo "   ⚠️ WebSocket notification returned false (may need Redis/WebSocket server)\n";
    }
} catch (\Exception $e) {
    echo "   ⚠️ Error: " . $e->getMessage() . "\n";
    echo "   (This is expected if Redis is not configured)\n";
}
echo "\n";

// Test 2: Web Push Service
echo "2️⃣ Testing Web Push Service...\n";
try {
    $webPushService = app(WebPushService::class);
    
    $results = $webPushService->sendToUser($user, 'Test Notification', 'This is a test notification via Web Push');
    
    if (empty($results)) {
        echo "   ✅ Service initialized (no subscriptions found, which is expected)\n";
    } else {
        echo "   ✅ Web Push notification queued for " . count($results) . " subscription(s)\n";
        foreach ($results as $subId => $result) {
            echo "      - Subscription {$subId}: " . ($result['status'] ?? 'unknown') . "\n";
        }
    }
} catch (\Exception $e) {
    echo "   ⚠️ Error: " . $e->getMessage() . "\n";
    echo "   (This is expected if VAPID keys are not configured)\n";
}
echo "\n";

// Test 3: Web Push Subscription Model
echo "3️⃣ Testing Web Push Subscription Model...\n";
try {
    $subscription = \App\Models\WebPushSubscription::create([
        'user_id' => $user->id,
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-' . time(),
        'p256dh_key' => 'test-p256dh-key',
        'auth_key' => 'test-auth-key',
        'active' => true,
    ]);
    
    echo "   ✅ Subscription created (ID: {$subscription->id})\n";
    
    $count = \App\Models\WebPushSubscription::where('user_id', $user->id)->where('active', true)->count();
    echo "   ✅ Active subscriptions for user: {$count}\n";
    
    // Clean up
    $subscription->delete();
    echo "   ✅ Test subscription cleaned up\n";
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 4: Notification Service with new channels
echo "4️⃣ Testing Notification Service Integration...\n";
try {
    $notificationService = app(NotificationService::class);
    
    // Check if channels are registered
    $reflection = new ReflectionClass($notificationService);
    $channelsProperty = $reflection->getProperty('channels');
    $channelsProperty->setAccessible(true);
    $channels = $channelsProperty->getValue($notificationService);
    
    $hasWebSocket = isset($channels[\App\Services\Notification\NotificationService::CHANNEL_WEBSOCKET]);
    $hasWebPush = isset($channels[\App\Services\Notification\NotificationService::CHANNEL_WEBPUSH]);
    
    if ($hasWebSocket) {
        echo "   ✅ WebSocket channel registered\n";
    } else {
        echo "   ❌ WebSocket channel NOT registered\n";
    }
    
    if ($hasWebPush) {
        echo "   ✅ WebPush channel registered\n";
    } else {
        echo "   ❌ WebPush channel NOT registered\n";
    }
    
    // Test sending a simple notification
    if ($hasWebSocket || $hasWebPush) {
        echo "   ✅ Notification Service is ready to use new channels\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 5: API Controllers
echo "5️⃣ Testing API Controllers...\n";
try {
    $webPushController = app(\App\Http\Controllers\Api\WebPushApiController::class);
    $webSocketController = app(\App\Http\Controllers\Api\WebSocketApiController::class);
    
    echo "   ✅ WebPushApiController instantiated\n";
    echo "   ✅ WebSocketApiController instantiated\n";
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 6: Routes
echo "6️⃣ Checking Routes...\n";
try {
    $routes = \Illuminate\Support\Facades\Route::getRoutes();
    $webPushRoutes = [];
    $webSocketRoutes = [];
    
    foreach ($routes as $route) {
        $uri = $route->uri();
        if (str_contains($uri, 'webpush')) {
            $webPushRoutes[] = $route->methods()[0] . ' ' . $uri;
        }
        if (str_contains($uri, 'websocket')) {
            $webSocketRoutes[] = $route->methods()[0] . ' ' . $uri;
        }
    }
    
    if (!empty($webPushRoutes)) {
        echo "   ✅ Web Push routes registered:\n";
        foreach ($webPushRoutes as $route) {
            echo "      - {$route}\n";
        }
    } else {
        echo "   ⚠️ No Web Push routes found\n";
    }
    
    if (!empty($webSocketRoutes)) {
        echo "   ✅ WebSocket routes registered:\n";
        foreach ($webSocketRoutes as $route) {
            echo "      - {$route}\n";
        }
    } else {
        echo "   ⚠️ No WebSocket routes found\n";
    }
} catch (\Exception $e) {
    echo "   ⚠️ Error checking routes: " . $e->getMessage() . "\n";
}
echo "\n";

echo "✅ Basic functionality tests complete!\n";
echo "\n";
echo "📝 Next Steps:\n";
echo "   1. Configure VAPID keys in .env for Web Push:\n";
echo "      VAPID_PUBLIC_KEY=your_public_key\n";
echo "      VAPID_PRIVATE_KEY=your_private_key\n";
echo "      VAPID_SUBJECT=mailto:your-email@example.com\n";
echo "\n";
echo "   2. Set up WebSocket server (e.g., Soketi or Laravel Echo Server)\n";
echo "   3. Test with real HTTP requests using the API endpoints\n";
echo "   4. Test with real user logins in the frontend\n";

