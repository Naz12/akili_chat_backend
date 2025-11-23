<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

class ResetPasswordNotification extends Notification
{
    public $token;

    /**
     * Create a notification instance.
     */
    public function __construct($token)
    {
        $this->token = $token;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Build the reset password email message.
     */
    public function toMail($notifiable)
    {
        // Deep link to open Flutter app
        $deepLink = 'chatdagu://reset-password'
            . '?token=' . $this->token
            . '&email=' . urlencode($notifiable->getEmailForPasswordReset());

        // Fallback web URL in case deep linking fails
        $webUrl = URL::temporarySignedRoute(
            'password.reset', 
            Carbon::now()->addMinutes(Config::get('auth.passwords.' . config('auth.defaults.passwords') . '.expire', 60)),
            ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]
        );

        return (new MailMessage)
            ->subject('Reset Your Password')
            ->line('Click the button below to reset your password in the ChatDagu mobile app.')
            ->action('Open in ChatDagu App', $deepLink)
            ->line('If the app does not open, you can also reset it from the web:')
            ->action('Reset via Web', $webUrl)
            ->line('If you did not request a password reset, no further action is required.');
    }
}