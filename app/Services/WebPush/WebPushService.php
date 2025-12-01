<?php

namespace App\Services\WebPush;

use App\Models\User;
use App\Models\WebPushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

/**
 * Web Push API Service
 * 
 * Handles browser push notifications using Web Push API
 */
class WebPushService
{
    protected WebPush $webPush;

    public function __construct()
    {
        $vapidPublicKey = config('services.webpush.vapid_public_key');
        $vapidPrivateKey = config('services.webpush.vapid_private_key');
        $vapidSubject = config('services.webpush.vapid_subject', config('app.url'));

        if (!$vapidPublicKey || !$vapidPrivateKey) {
            Log::warning('Web Push VAPID keys not configured');
            // Create a dummy WebPush instance that will fail gracefully
            $this->webPush = new WebPush([]);
        } else {
            $this->webPush = new WebPush([
                'VAPID' => [
                    'subject' => $vapidSubject,
                    'publicKey' => $vapidPublicKey,
                    'privateKey' => $vapidPrivateKey,
                ],
            ]);
        }
    }

    /**
     * Send push notification to a user
     * 
     * @param User $user The user to notify
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $options Additional options (icon, badge, data, etc.)
     * @return array Results for each subscription
     */
    public function sendToUser(User $user, string $title, string $body, array $options = []): array
    {
        $subscriptions = WebPushSubscription::where('user_id', $user->id)
            ->where('active', true)
            ->get();

        if ($subscriptions->isEmpty()) {
            Log::debug('No active Web Push subscriptions for user', ['user_id' => $user->id]);
            return [];
        }

        $results = [];
        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'icon' => $options['icon'] ?? config('app.url') . '/icon-192x192.png',
            'badge' => $options['badge'] ?? config('app.url') . '/badge-72x72.png',
            'data' => $options['data'] ?? [],
            'tag' => $options['tag'] ?? null,
            'requireInteraction' => $options['requireInteraction'] ?? false,
            'timestamp' => now()->timestamp,
        ]);

        foreach ($subscriptions as $subscription) {
            try {
                $pushSubscription = Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'keys' => [
                        'p256dh' => $subscription->p256dh_key,
                        'auth' => $subscription->auth_key,
                    ],
                ]);

                $this->webPush->queueNotification(
                    $pushSubscription,
                    $payload
                );

                $results[$subscription->id] = ['status' => 'queued'];
            } catch (\Exception $e) {
                Log::error('Failed to queue Web Push notification', [
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);

                $results[$subscription->id] = [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];

                // If subscription is invalid, mark it as inactive
                if (str_contains($e->getMessage(), '410') || str_contains($e->getMessage(), 'expired')) {
                    $subscription->update(['active' => false]);
                }
            }
        }

        // Flush notifications
        foreach ($this->webPush->flush() as $report) {
            $endpoint = $report->getRequest()->getUri()->__toString();
            $subscription = WebPushSubscription::where('endpoint', $endpoint)->first();

            if ($subscription) {
                if ($report->isSuccess()) {
                    Log::debug('Web Push sent successfully', [
                        'user_id' => $subscription->user_id,
                        'subscription_id' => $subscription->id,
                    ]);
                } else {
                    Log::warning('Web Push failed', [
                        'user_id' => $subscription->user_id,
                        'subscription_id' => $subscription->id,
                        'reason' => $report->getReason(),
                    ]);

                    // Mark subscription as inactive if it's expired/invalid
                    if ($report->isSubscriptionExpired()) {
                        $subscription->update(['active' => false]);
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Send notification to multiple users
     * 
     * @param array|Collection $users Collection of User models
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $options Additional options
     * @return array Results indexed by user_id
     */
    public function sendToMany($users, string $title, string $body, array $options = []): array
    {
        $results = [];

        foreach ($users as $user) {
            $results[$user->id] = $this->sendToUser($user, $title, $body, $options);
        }

        return $results;
    }
}

