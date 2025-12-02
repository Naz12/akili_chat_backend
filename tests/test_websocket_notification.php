<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== WebSocket Notification Test ===\n\n";

// Find user
$user = \App\Models\User::where('email', 'snazrawi@gmail.com')->first();

if (!$user) {
    echo "❌ User not found\n";
    exit(1);
}

echo "✅ Found user: {$user->name} (ID: {$user->id})\n\n";

// Test WebSocket notification
echo "Testing WebSocket notification...\n";

$notificationService = app(\App\Services\Notification\NotificationService::class);
$notification = new \App\Notifications\AdminBroadcastNotification(
    'WebSocket Test',
    'This is a test notification sent via WebSocket at ' . now()->toDateTimeString(),
    ['websocket']
);

try {
    $results = $notificationService->send($user, $notification, ['websocket'], false);
    
    echo "✅ Notification sent!\n";
    echo "Results: " . json_encode($results, JSON_PRETTY_PRINT) . "\n\n";
    
    // Check broadcasting config
    echo "Broadcasting Configuration:\n";
    echo "  Driver: " . config('broadcasting.default') . "\n";
    echo "  Pusher Host: " . config('broadcasting.connections.pusher.options.host') . "\n";
    echo "  Pusher Port: " . config('broadcasting.connections.pusher.options.port') . "\n";
    echo "  Pusher App ID: " . config('broadcasting.connections.pusher.app_id') . "\n";
    echo "  Pusher Key: " . config('broadcasting.connections.pusher.key') . "\n\n";
    
    echo "✅ Test completed successfully!\n";
    echo "Check the frontend to see if the notification appears.\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}



