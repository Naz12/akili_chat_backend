<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminBroadcastNotification extends Notification
{
    use Queueable;

    protected string $subject;
    protected string $message;
    protected array $channels;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $subject, string $message, array $channels = [])
    {
        $this->subject = $subject;
        $this->message = $message;
        $this->channels = $channels;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // If channels are specified, use them; otherwise use all available
        if (!empty($this->channels)) {
            return $this->channels;
        }

        return [
            \App\Services\Notification\NotificationService::CHANNEL_DATABASE,
            \App\Services\Notification\NotificationService::CHANNEL_EMAIL,
            \App\Services\Notification\NotificationService::CHANNEL_WEBSOCKET,
            \App\Services\Notification\NotificationService::CHANNEL_WEBPUSH,
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject)
            ->greeting("Hello {$notifiable->name},")
            ->line($this->message);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'subject' => $this->subject,
            'message' => $this->message,
            'type' => 'admin_broadcast',
        ];
    }

    /**
     * Get the database representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->subject,
            'message' => $this->message,
            'type' => 'admin_broadcast',
            'timestamp' => now()->toIso8601String(),
        ];
    }
}

