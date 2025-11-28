<?php

namespace App\Services\Notification\Channels;

use App\Models\User;
use App\Services\SmsService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * SMS notification channel
 */
class SmsChannel implements ChannelInterface
{
    /**
     * Send notification via SMS
     */
    public function send(User $user, Notification $notification)
    {
        if (!$user->phone) {
            return ['status' => 'skipped', 'reason' => 'no_phone'];
        }

        $message = $this->extractMessage($notification, $user);

        if (empty($message)) {
            Log::warning('SMS notification has no message', [
                'user_id' => $user->id,
                'notification' => get_class($notification),
            ]);
            return ['status' => 'skipped', 'reason' => 'no_message'];
        }

        try {
            SmsService::send($user->phone, $message);
            return ['status' => 'sent', 'phone' => substr($user->phone, -4) . '****'];
        } catch (\Exception $e) {
            Log::error('SMS send failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * Extract message from notification
     */
    protected function extractMessage(Notification $notification, User $user): string
    {
        if (method_exists($notification, 'toDatabase')) {
            $data = $notification->toDatabase($user);
            return $data['message'] ?? '';
        }

        if (method_exists($notification, 'toSms')) {
            return $notification->toSms($user);
        }

        if (method_exists($notification, 'toMail')) {
            $mail = $notification->toMail($user);
            return $mail->introLines[0] ?? '';
        }

        return '';
    }
}

