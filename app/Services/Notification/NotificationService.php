<?php

namespace App\Services\Notification;

use App\Models\User;
use App\Models\NotificationLog;
use App\Services\Notification\Channels\DatabaseChannel;
use App\Services\Notification\Channels\EmailChannel;
use App\Services\Notification\Channels\PushChannel;
use App\Services\Notification\Channels\SmsChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Standalone Notification Service
 * 
 * Centralized service for sending notifications across multiple channels.
 * Respects user preferences and provides flexible channel management.
 */
class NotificationService
{
    /**
     * Available notification channels
     */
    const CHANNEL_DATABASE = 'database';
    const CHANNEL_EMAIL = 'email';
    const CHANNEL_PUSH = 'push';
    const CHANNEL_SMS = 'sms';

    /**
     * Channel handlers
     */
    protected array $channels = [];

    public function __construct(
        DatabaseChannel $databaseChannel,
        EmailChannel $emailChannel,
        PushChannel $pushChannel,
        SmsChannel $smsChannel
    ) {
        $this->channels = [
            self::CHANNEL_DATABASE => $databaseChannel,
            self::CHANNEL_EMAIL => $emailChannel,
            self::CHANNEL_PUSH => $pushChannel,
            self::CHANNEL_SMS => $smsChannel,
        ];
    }

    /**
     * Send a notification to a user
     * 
     * @param User $user The user to notify
     * @param Notification $notification The notification instance
     * @param array $channels Optional channels to use (defaults to notification's via() method)
     * @param bool $respectPreferences Whether to respect user preferences (default: true)
     * @return array Results from each channel
     */
    public function send(
        User $user,
        Notification $notification,
        array $channels = [],
        bool $respectPreferences = true
    ): array {
        // Get channels from notification if not specified
        if (empty($channels)) {
            $channels = $notification->via($user);
        }

        $results = [];
        $preference = $user->preference;

        foreach ($channels as $channel) {
            // Skip if channel doesn't exist
            if (!isset($this->channels[$channel])) {
                Log::warning("Unknown notification channel: {$channel}", [
                    'user_id' => $user->id,
                    'notification' => get_class($notification),
                ]);
                continue;
            }

            // Check user preferences if enabled
            if ($respectPreferences && $preference) {
                if (!$this->isChannelAllowed($channel, $user, $preference)) {
                    Log::debug("Channel {$channel} skipped due to user preference", [
                        'user_id' => $user->id,
                    ]);
                    continue;
                }
            }

            try {
                $handler = $this->channels[$channel];
                $result = $handler->send($user, $notification);

                // Log to notification_logs for audit trail
                if ($channel !== self::CHANNEL_DATABASE) {
                    $this->logNotification($user, $channel, $notification);
                }

                $results[$channel] = [
                    'success' => true,
                    'result' => $result,
                ];
            } catch (\Exception $e) {
                Log::error("Failed to send notification via {$channel}", [
                    'user_id' => $user->id,
                    'channel' => $channel,
                    'error' => $e->getMessage(),
                    'notification' => get_class($notification),
                    'trace' => $e->getTraceAsString(),
                ]);

                $results[$channel] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
                
                // If email fails, don't prevent database notification from being sent
                // Continue to next channel
                continue;
            }
        }

        return $results;
    }

    /**
     * Send notification to multiple users
     * 
     * @param array|Collection $users Collection of User models
     * @param Notification $notification The notification instance
     * @param array $channels Optional channels to use
     * @param bool $respectPreferences Whether to respect user preferences
     * @return array Results indexed by user_id
     */
    public function sendToMany(
        $users,
        Notification $notification,
        array $channels = [],
        bool $respectPreferences = true
    ): array {
        $results = [];

        foreach ($users as $user) {
            $results[$user->id] = $this->send($user, $notification, $channels, $respectPreferences);
        }

        return $results;
    }

    /**
     * Check if a channel is allowed for a user based on preferences
     */
    protected function isChannelAllowed(string $channel, User $user, $preference): bool
    {
        return match ($channel) {
            self::CHANNEL_DATABASE => true, // Always allowed
            self::CHANNEL_EMAIL => (bool) $preference->allow_marketing_email,
            self::CHANNEL_PUSH => (bool) ($preference->allow_push_notifications && $user->fcm_token),
            self::CHANNEL_SMS => (bool) ($preference->allow_sms && $user->phone),
            default => false,
        };
    }

    /**
     * Log notification to notification_logs table
     */
    protected function logNotification(User $user, string $channel, Notification $notification): void
    {
        try {
            $content = $this->extractNotificationContent($notification);

            NotificationLog::create([
                'user_id' => $user->id,
                'channel' => $channel,
                'content' => $content,
            ]);
        } catch (\Exception $e) {
            Log::warning("Failed to log notification", [
                'user_id' => $user->id,
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract content from notification for logging
     */
    protected function extractNotificationContent(Notification $notification): string
    {
        // Try to get title/message from notification
        if (method_exists($notification, 'toDatabase')) {
            $data = $notification->toDatabase(new \App\Models\User());
            return $data['title'] ?? $data['message'] ?? get_class($notification);
        }

        if (method_exists($notification, 'toMail')) {
            $mail = $notification->toMail(new \App\Models\User());
            return $mail->subject ?? get_class($notification);
        }

        return get_class($notification);
    }
}

