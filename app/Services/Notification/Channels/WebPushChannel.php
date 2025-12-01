<?php

namespace App\Services\Notification\Channels;

use App\Models\User;
use App\Services\WebPush\WebPushService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Web Push API notification channel
 */
class WebPushChannel implements ChannelInterface
{
    protected WebPushService $webPushService;

    public function __construct(WebPushService $webPushService)
    {
        $this->webPushService = $webPushService;
    }

    /**
     * Send notification via Web Push API
     */
    public function send(User $user, Notification $notification)
    {
        try {
            // Extract title and body
            $title = $this->extractTitle($notification, $user);
            $body = $this->extractBody($notification, $user);
            $options = $this->extractOptions($notification, $user);

            // Send via Web Push
            $results = $this->webPushService->sendToUser($user, $title, $body, $options);

            if (empty($results)) {
                return ['status' => 'skipped', 'reason' => 'no_subscriptions'];
            }

            $successCount = count(array_filter($results, fn($r) => ($r['status'] ?? '') === 'queued'));
            
            return [
                'status' => $successCount > 0 ? 'sent' : 'failed',
                'subscriptions' => count($results),
                'successful' => $successCount,
            ];
        } catch (\Exception $e) {
            Log::error('Web Push notification failed', [
                'user_id' => $user->id,
                'notification' => get_class($notification),
                'error' => $e->getMessage(),
            ]);

            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * Extract title from notification
     */
    protected function extractTitle(Notification $notification, User $user): string
    {
        if (method_exists($notification, 'toDatabase')) {
            $data = $notification->toDatabase($user);
            return $data['title'] ?? 'Notification';
        }

        if (method_exists($notification, 'toMail')) {
            $mail = $notification->toMail($user);
            return $mail->subject ?? 'Notification';
        }

        return 'Notification';
    }

    /**
     * Extract body from notification
     */
    protected function extractBody(Notification $notification, User $user): string
    {
        if (method_exists($notification, 'toDatabase')) {
            $data = $notification->toDatabase($user);
            return $data['message'] ?? '';
        }

        if (method_exists($notification, 'toMail')) {
            $mail = $notification->toMail($user);
            return $mail->introLines[0] ?? '';
        }

        return '';
    }

    /**
     * Extract additional options for Web Push
     */
    protected function extractOptions(Notification $notification, User $user): array
    {
        $options = [];

        if (method_exists($notification, 'toDatabase')) {
            $data = $notification->toDatabase($user);
            
            // Include relevant data
            $options['data'] = $data;
            $options['tag'] = $data['type'] ?? null;
            
            // Add action URL if available
            if (isset($data['action_url'])) {
                $options['data']['action_url'] = $data['action_url'];
            }
        }

        return $options;
    }
}

