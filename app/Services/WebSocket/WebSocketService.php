<?php

namespace App\Services\WebSocket;

use App\Models\User;
use App\Services\WebSocket\Events\NotificationSent;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * WebSocket Service Module
 * 
 * Standalone WebSocket service that can be used for notifications,
 * real-time chat, presence, and other real-time features.
 */
class WebSocketService
{
    /**
     * Broadcast a message to a specific user
     * 
     * @param User $user The user to send the message to
     * @param string $event Event name
     * @param array $data Event data
     * @return bool
     */
    public function sendToUser(User $user, string $event, array $data): bool
    {
        try {
            // For notification events, use the NotificationSent event
            if ($event === 'notification') {
                event(new NotificationSent($user, $data));
            } else {
                // For other events, use Redis pub/sub directly
                $channel = "user.{$user->id}";
                $payload = json_encode([
                    'event' => $event,
                    'data' => $data,
                    'timestamp' => now()->toIso8601String(),
                ]);

                Redis::publish($channel, $payload);
            }

            Log::debug('WebSocket message sent', [
                'user_id' => $user->id,
                'event' => $event,
                'channel' => "user.{$user->id}",
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('WebSocket send failed', [
                'user_id' => $user->id,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Broadcast a message to multiple users
     * 
     * @param array|Collection $users Collection of User models
     * @param string $event Event name
     * @param array $data Event data
     * @return array Results indexed by user_id
     */
    public function sendToMany($users, string $event, array $data): array
    {
        $results = [];

        foreach ($users as $user) {
            $results[$user->id] = $this->sendToUser($user, $event, $data);
        }

        return $results;
    }

    /**
     * Broadcast a notification event to a user
     * 
     * @param User $user The user to notify
     * @param array $notificationData Notification data
     * @return bool
     */
    public function sendNotification(User $user, array $notificationData): bool
    {
        return $this->sendToUser($user, 'notification', $notificationData);
    }

    /**
     * Broadcast a custom event to a user
     * 
     * @param User $user The user to send to
     * @param string $event Custom event name
     * @param array $data Event data
     * @return bool
     */
    public function sendCustomEvent(User $user, string $event, array $data): bool
    {
        return $this->sendToUser($user, $event, $data);
    }

    /**
     * Check if user has active WebSocket connection
     * This is a simple check - in production you'd track connections
     * 
     * @param User $user
     * @return bool
     */
    public function isUserConnected(User $user): bool
    {
        // In a real implementation, you'd check Redis for active connections
        // For now, we'll assume they might be connected
        // This can be enhanced with connection tracking
        try {
            $key = "websocket:user:{$user->id}:connected";
            return Redis::exists($key) > 0;
        } catch (\Exception $e) {
            Log::warning('WebSocket connection check failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Mark user as connected
     * 
     * @param User $user
     * @param string $connectionId
     * @return void
     */
    public function markUserConnected(User $user, string $connectionId): void
    {
        try {
            $key = "websocket:user:{$user->id}:connected";
            Redis::setex($key, 300, $connectionId); // 5 minute TTL
        } catch (\Exception $e) {
            Log::error('Failed to mark user as connected', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Mark user as disconnected
     * 
     * @param User $user
     * @return void
     */
    public function markUserDisconnected(User $user): void
    {
        try {
            $key = "websocket:user:{$user->id}:connected";
            Redis::del($key);
        } catch (\Exception $e) {
            Log::error('Failed to mark user as disconnected', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

