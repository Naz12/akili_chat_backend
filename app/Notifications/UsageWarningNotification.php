<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UsageWarningNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Subscription $subscription;
    protected float $percentageUsed;

    /**
     * Create a new notification instance.
     */
    public function __construct(Subscription $subscription, float $percentageUsed)
    {
        $this->subscription = $subscription;
        $this->percentageUsed = $percentageUsed;
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
            ->subject('⚠️ Token Usage Warning - ' . round($this->percentageUsed) . '% Used')
            ->greeting("Hello {$notifiable->name},")
            ->line("You've used {$this->percentageUsed}% of your token quota ({$tokensUsed}/{$maxTokens} tokens).")
            ->line('Consider upgrading your plan to avoid service interruption.')
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
            'title' => 'Token Usage Warning',
            'message' => "You've used " . round($this->percentageUsed) . "% of your token quota. Consider upgrading your plan.",
            'type' => 'usage_warning',
            'subscription_id' => $this->subscription->id,
            'plan_name' => $plan->name,
            'tokens_used' => $this->subscription->tokens_used,
            'max_tokens' => $plan->max_tokens,
            'percentage_used' => round($this->percentageUsed, 2),
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
