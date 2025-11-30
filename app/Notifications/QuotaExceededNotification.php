<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuotaExceededNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Subscription $subscription;

    /**
     * Create a new notification instance.
     */
    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [
            \App\Services\Notification\NotificationService::CHANNEL_DATABASE,
            \App\Services\Notification\NotificationService::CHANNEL_EMAIL,
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $plan = $this->subscription->plan;
        $tokensUsed = $this->subscription->tokens_used;
        $maxTokens = $plan->max_tokens;

        return (new MailMessage)
            ->subject('❌ Token Quota Exceeded')
            ->greeting("Hello {$notifiable->name},")
            ->line("You've reached your token quota limit ({$tokensUsed}/{$maxTokens} tokens).")
            ->line('Please upgrade your plan to continue using the service.')
            ->action('Upgrade Plan', config('app.frontend_url', config('app.url')) . '/plans')
            ->line('Thank you for using our service!');
    }

    /**
     * Get the database representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $plan = $this->subscription->plan;
        
        return [
            'title' => 'Token Quota Exceeded',
            'message' => "You've reached your token quota limit. Please upgrade your plan to continue.",
            'type' => 'quota_exceeded',
            'subscription_id' => $this->subscription->id,
            'plan_name' => $plan->name,
            'tokens_used' => $this->subscription->tokens_used,
            'max_tokens' => $plan->max_tokens,
            'action_url' => '/plans',
        ];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
