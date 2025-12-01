<?php

namespace App\Services\Notification\Channels;

use App\Models\User;
use App\Services\WebSocket\WebSocketService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * WebSocket notification channel
 */
class WebSocketChannel implements ChannelInterface
{
    protected WebSocketService $webSocketService;

    public function __construct(WebSocketService $webSocketService)
    {
        $this->webSocketService = $webSocketService;
    }

    /**
     * Send notification via WebSocket
     */
    public function send(User $user, Notification $notification)
    {
        try {
            // Extract notification data
            $notificationData = $this->extractNotificationData($notification, $user);

            // Send via WebSocket
            $result = $this->webSocketService->sendNotification($user, $notificationData);

            return ['status' => 'sent', 'websocket' => $result];
        } catch (\Exception $e) {
            Log::error('WebSocket notification failed', [
                'user_id' => $user->id,
                'notification' => get_class($notification),
                'error' => $e->getMessage(),
            ]);

            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * Extract notification data for WebSocket
     */
    protected function extractNotificationData(Notification $notification, User $user): array
    {
        $data = [];

        // Try to get data from toDatabase method
        if (method_exists($notification, 'toDatabase')) {
            $data = $notification->toDatabase($user);
        } elseif (method_exists($notification, 'toArray')) {
            $data = $notification->toArray($user);
        }

        // Add notification type
        $data['notification_type'] = get_class($notification);
        $data['timestamp'] = now()->toIso8601String();

        return $data;
    }
}

