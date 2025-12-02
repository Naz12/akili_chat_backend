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
        
        try {
            Mail::send([], [], function ($message) use ($mailMessage, $user, $htmlContent) {
                $message->to($user->email)
                        ->subject($mailMessage->subject ?? 'Notification')
                        ->html($htmlContent);
            });

            Log::info('Email sent successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'subject' => $mailMessage->subject ?? 'Notification'
            ]);

            return ['status' => 'sent', 'email' => $user->email];
        } catch (\Exception $e) {
            Log::error('Failed to send email via SMTP', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e; // Re-throw to let the caller handle it
        }
    }
}

