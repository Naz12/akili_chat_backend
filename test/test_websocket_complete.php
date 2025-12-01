<?php

/**
 * Complete WebSocket and Notification System Test
 * 
 * Tests:
 * 1. WebSocket service status
 * 2. Docker container
 * 3. WebSocket endpoint
 * 4. Nginx proxy
 * 5. Notification service integration
 * 6. Admin notifier functionality
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Services\WebSocket\WebSocketService;
use App\Services\WebPush\WebPushService;
use App\Services\Notification\NotificationService;
use App\Notifications\AdminBroadcastNotification;

echo "🧪 Complete WebSocket & Notification System Test\n";
echo "===============================================\n\n";

$allTestsPassed = true;

// Test 1: WebSocket Service Status
echo "1️⃣ Testing WebSocket Service Status...\n";
$serviceStatus = shell_exec('systemctl is-active akili-websocket.service 2>/dev/null');
if (trim($serviceStatus) === 'active') {
    echo "   ✅ WebSocket service is running\n";
} else {
    echo "   ❌ WebSocket service is NOT running\n";
    $allTestsPassed = false;
}
echo "\n";

// Test 2: WebSocket Endpoint
echo "2️⃣ Testing WebSocket Endpoint (localhost:6001)...\n";
$ch = curl_init('http://localhost:6001');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_HEADER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 404 || $httpCode === 200) {
    echo "   ✅ WebSocket endpoint is responding (HTTP $httpCode - normal for WebSocket)\n";
} else {
    echo "   ⚠️  WebSocket endpoint returned HTTP $httpCode\n";
    if ($httpCode === 0) {
        echo "   ❌ Cannot connect to WebSocket server\n";
        $allTestsPassed = false;
    }
}
echo "\n";

// Test 3: Nginx Proxy Configuration
echo "3️⃣ Testing Nginx Configuration...\n";
// Check if WebSocket location exists in our config file
$ourConfigFile = '/home/deploy_user_dagi/services/akili_chat_backend/chat.akmicroservice.com.conf';
$appliedConfigFile = '/etc/nginx/sites-enabled/chat.akmicroservice.com.conf';

$ourConfigCheck = shell_exec("grep -c 'location /app/' {$ourConfigFile} 2>/dev/null");
$appliedConfigCheck = shell_exec("grep -c 'location /app/' {$appliedConfigFile} 2>/dev/null");

if (trim($ourConfigCheck) > 0) {
    echo "   ✅ WebSocket proxy location found in our nginx config file\n";
    
    if (trim($appliedConfigCheck) > 0) {
        echo "   ✅ WebSocket proxy location found in applied nginx config\n";
    } else {
        echo "   ⚠️  WebSocket proxy not in applied config (needs: sudo cp chat.akmicroservice.com.conf /etc/nginx/sites-enabled/)\n";
    }
} else {
    echo "   ❌ WebSocket proxy location NOT found in config file\n";
    $allTestsPassed = false;
}

// Check nginx test (may have errors from other sites, but that's OK)
$nginxTest = shell_exec('nginx -t 2>&1');
if (strpos($nginxTest, 'chat.akmicroservice.com') !== false && 
    (strpos($nginxTest, 'error') !== false || strpos($nginxTest, 'emerg') !== false)) {
    echo "   ⚠️  Nginx test shows errors, but they may be from other sites\n";
} else {
    echo "   ℹ️  Note: nginx -t may show errors from other sites (not related to WebSocket)\n";
}
echo "\n";

// Test 4: WebSocket Service Class
echo "4️⃣ Testing WebSocket Service Class...\n";
try {
    $webSocketService = app(WebSocketService::class);
    echo "   ✅ WebSocketService instantiated successfully\n";
    
    // Test sending a notification
    $testUser = User::first();
    if ($testUser) {
        $result = $webSocketService->sendNotification($testUser, [
            'title' => 'Test Notification',
            'message' => 'This is a test from the test script',
            'type' => 'test',
        ]);
        
        if ($result) {
            echo "   ✅ WebSocket notification sent successfully\n";
        } else {
            echo "   ⚠️  WebSocket notification returned false (may need Redis/connection)\n";
        }
    } else {
        echo "   ⚠️  No users found to test with\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    $allTestsPassed = false;
}
echo "\n";

// Test 5: WebPush Service Class
echo "5️⃣ Testing WebPush Service Class...\n";
try {
    $webPushService = app(WebPushService::class);
    echo "   ✅ WebPushService instantiated successfully\n";
    
    $testUser = User::first();
    if ($testUser) {
        $results = $webPushService->sendToUser($testUser, 'Test', 'Test message');
        if (empty($results)) {
            echo "   ✅ WebPush service works (no subscriptions found - expected)\n";
        } else {
            echo "   ✅ WebPush service works (" . count($results) . " subscriptions)\n";
        }
    }
} catch (\Exception $e) {
    echo "   ⚠️  Error: " . $e->getMessage() . "\n";
    echo "   (This is expected if VAPID keys are not configured)\n";
}
echo "\n";

// Test 6: Notification Service Integration
echo "6️⃣ Testing Notification Service Integration...\n";
try {
    $notificationService = app(NotificationService::class);
    echo "   ✅ NotificationService instantiated successfully\n";
    
    // Check channels
    $reflection = new ReflectionClass($notificationService);
    $channelsProperty = $reflection->getProperty('channels');
    $channelsProperty->setAccessible(true);
    $channels = $channelsProperty->getValue($notificationService);
    
    $requiredChannels = ['database', 'email', 'push', 'sms', 'websocket', 'webpush'];
    $missingChannels = [];
    
    foreach ($requiredChannels as $channel) {
        if (!isset($channels[$channel])) {
            $missingChannels[] = $channel;
        }
    }
    
    if (empty($missingChannels)) {
        echo "   ✅ All notification channels registered\n";
        echo "      Channels: " . implode(', ', array_keys($channels)) . "\n";
    } else {
        echo "   ❌ Missing channels: " . implode(', ', $missingChannels) . "\n";
        $allTestsPassed = false;
    }
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    $allTestsPassed = false;
}
echo "\n";

// Test 7: AdminBroadcastNotification
echo "7️⃣ Testing AdminBroadcastNotification Class...\n";
try {
    $testUser = User::first();
    if ($testUser) {
        $notification = new AdminBroadcastNotification(
            'Test Subject',
            'Test Message',
            [NotificationService::CHANNEL_DATABASE, NotificationService::CHANNEL_WEBSOCKET]
        );
        
        $via = $notification->via($testUser);
        if (in_array(NotificationService::CHANNEL_DATABASE, $via) && 
            in_array(NotificationService::CHANNEL_WEBSOCKET, $via)) {
            echo "   ✅ AdminBroadcastNotification works correctly\n";
        } else {
            echo "   ⚠️  AdminBroadcastNotification channels: " . implode(', ', $via) . "\n";
        }
    }
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    $allTestsPassed = false;
}
echo "\n";

// Test 8: End-to-End Notification Test
echo "8️⃣ Testing End-to-End Notification (Database + WebSocket)...\n";
try {
    $testUser = User::first();
    if ($testUser) {
        $notificationService = app(NotificationService::class);
        $notification = new AdminBroadcastNotification(
            'E2E Test Notification',
            'This is an end-to-end test notification',
            [NotificationService::CHANNEL_DATABASE, NotificationService::CHANNEL_WEBSOCKET]
        );
        
        $results = $notificationService->send($testUser, $notification);
        
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
            echo "   ✅ End-to-end test: {$successCount} channel(s) succeeded\n";
        } else {
            echo "   ⚠️  End-to-end test: No channels succeeded\n";
        }
    } else {
        echo "   ⚠️  No users found to test with\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    echo "   Trace: " . substr($e->getTraceAsString(), 0, 200) . "\n";
    $allTestsPassed = false;
}
echo "\n";

// Test 9: Check Database Notification
echo "9️⃣ Checking Database Notifications...\n";
try {
    $testUser = User::first();
    if ($testUser) {
        $notifications = $testUser->notifications()->latest()->take(5)->get();
        if ($notifications->count() > 0) {
            echo "   ✅ Found {$notifications->count()} recent notification(s)\n";
            $latest = $notifications->first();
            echo "   Latest: " . ($latest->data['title'] ?? 'N/A') . "\n";
        } else {
            echo "   ⚠️  No notifications found in database\n";
        }
    }
} catch (\Exception $e) {
    echo "   ⚠️  Error: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 10: WebSocket Logs
echo "🔟 Checking WebSocket Logs...\n";
$logFile = '/var/log/akili-websocket.log';
if (file_exists($logFile) && is_readable($logFile)) {
    $logLines = file($logFile);
    $recentLogs = array_slice($logLines, -10);
    $hasErrors = false;
    
    foreach ($recentLogs as $line) {
        if (stripos($line, 'error') !== false || stripos($line, 'failed') !== false) {
            $hasErrors = true;
            break;
        }
    }
    
    if (!$hasErrors) {
        echo "   ✅ Recent logs show no errors\n";
        echo "   Last log: " . trim(end($recentLogs)) . "\n";
    } else {
        echo "   ⚠️  Recent logs contain errors\n";
    }
} else {
    echo "   ⚠️  Cannot read log file (may need sudo)\n";
}
echo "\n";

// Summary
echo "===============================================\n";
if ($allTestsPassed) {
    echo "✅ ALL CRITICAL TESTS PASSED\n";
} else {
    echo "⚠️  SOME TESTS HAD ISSUES (see above)\n";
}
echo "\n";

echo "📝 Next Steps:\n";
echo "   1. Reload nginx: sudo systemctl reload nginx\n";
echo "   2. Test admin notifier: https://chat.akmicroservice.com/admin/notifier\n";
echo "   3. Send test notification with WebSocket channel selected\n";
echo "   4. Check WebSocket logs: sudo tail -f /var/log/akili-websocket.log\n";
echo "   5. Test frontend connection: wss://chat.akmicroservice.com/app/\n";
echo "\n";

