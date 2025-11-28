<?php

namespace App\Services\Notification\Channels;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

/**
 * Email notification channel
 */
class EmailChannel implements ChannelInterface
{
    /**
     * Send notification via email
     */
    public function send(User $user, Notification $notification)
    {
        if (!method_exists($notification, 'toMail')) {
            throw new \RuntimeException(
                'Notification must implement toMail() method for email channel'
            );
        }

        $mailMessage = $notification->toMail($user);
        
        if (!$mailMessage) {
            Log::warning('Notification toMail() returned null', [
                'user_id' => $user->id,
                'notification' => get_class($notification),
            ]);
            return ['status' => 'skipped', 'reason' => 'no_mail_message'];
        }

        // MailMessage needs to be sent via Laravel's Mail channel
        // We use Mail::send() with a view callback to properly send MailMessage
        // MailMessage::render() returns HtmlString, so we convert it to string
        $htmlContent = (string) $mailMessage->render();
        
        Mail::send([], [], function ($message) use ($mailMessage, $user, $htmlContent) {
            $message->to($user->email)
                    ->subject($mailMessage->subject ?? 'Notification')
                    ->html($htmlContent);
        });

        return ['status' => 'sent', 'email' => $user->email];
    }
}

