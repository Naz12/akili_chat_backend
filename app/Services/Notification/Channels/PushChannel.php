<?php

namespace App\Services\Notification\Channels;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Push notification channel (FCM)
 */
class PushChannel implements ChannelInterface
{
    /**
     * Send notification via FCM push
     */
    public function send(User $user, Notification $notification)
    {
        if (!$user->fcm_token) {
            return ['status' => 'skipped', 'reason' => 'no_fcm_token'];
        }

        $title = $this->extractTitle($notification, $user);
        $body = $this->extractBody($notification, $user);

        $serverKey = config('services.fcm.server_key');
        if (!$serverKey) {
            Log::warning('FCM server key not configured');
            return ['status' => 'skipped', 'reason' => 'no_server_key'];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key=' . $serverKey,
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'to' => $user->fcm_token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $this->extractData($notification, $user),
            ]);

            if ($response->successful()) {
                return ['status' => 'sent', 'fcm_token' => substr($user->fcm_token, 0, 20) . '...'];
            }

            Log::warning('FCM push failed', [
                'user_id' => $user->id,
                'response' => $response->body(),
            ]);

            return ['status' => 'failed', 'reason' => 'fcm_error'];
        } catch (\Exception $e) {
            Log::error('FCM push exception', [
                'user_id' => $user->id,
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
     * Extract additional data from notification
     */
    protected function extractData(Notification $notification, User $user): array
    {
        if (method_exists($notification, 'toDatabase')) {
            $data = $notification->toDatabase($user);
            unset($data['title'], $data['message']);
            return $data;
        }

        return [];
    }
}

