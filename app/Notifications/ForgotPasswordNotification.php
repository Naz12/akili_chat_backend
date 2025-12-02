<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

class ForgotPasswordNotification extends Notification
{
    public $resetCode;

    /**
     * Create a notification instance.
     */
    public function __construct($resetCode)
    {
        $this->resetCode = $resetCode;
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
        return (new MailMessage)
            ->subject('Reset Your Password - ChatDagu')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('You requested to reset your password. Use the code below to reset your password:')
            ->line('**Your password reset code is: ' . $this->resetCode . '**')
            ->line('This code will expire in 10 minutes.')
            ->line('If you did not request a password reset, please ignore this email or contact support if you have concerns.')
            ->salutation('Best regards, The ChatDagu Team');
    }
}

