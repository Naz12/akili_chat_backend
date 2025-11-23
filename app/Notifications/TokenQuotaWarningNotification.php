<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TokenQuotaWarningNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    protected $percent;

    public function __construct($percent)
    {
        $this->percent = $percent;
    }
    
    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('⚠️ Token Usage Warning')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line("You have used {$this->percent}% of your token quota.")
            ->line('Once you reach 100%, you will not be able to use the AI service until your quota resets or is upgraded.')
            ->action('Upgrade Your Plan', url('/plans')) // 🔁 Adjust this to your real frontend
            ->line('Thank you for using our service!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }

        public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Token Quota Warning',
            'message' => "You’ve used {$this->percent}% of your token quota.",
            'type' => 'quota_warning',
            'percentage' => $this->percent,
        ];
    }

}