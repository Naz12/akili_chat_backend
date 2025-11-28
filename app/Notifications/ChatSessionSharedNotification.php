<?php

namespace App\Notifications;

use App\Models\ChatSessionShare;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChatSessionSharedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The chat session share instance
     */
    protected ChatSessionShare $share;

    /**
     * Create a new notification instance.
     */
    public function __construct(ChatSessionShare $share)
    {
        $this->share = $share;
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
        $sharerName = $this->share->sharedBy->name ?? 'Someone';
        $sessionTitle = $this->share->originalSession->title ?? 'Untitled Chat';
        $message = $this->share->message;

        $mailMessage = (new MailMessage)
            ->subject("{$sharerName} shared a chat session with you")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$sharerName} has shared a chat session titled \"{$sessionTitle}\" with you.");

        if ($message) {
            $mailMessage->line("Message: {$message}");
        }

        // Frontend URL - adjust based on your frontend setup
        $frontendUrl = config('app.frontend_url', 'https://app.example.com');
        $acceptUrl = "{$frontendUrl}/chat/shares/{$this->share->id}/accept";

        return $mailMessage
            ->action('View Shared Session', $acceptUrl)
            ->line('You can accept this share to continue the conversation in your own chat session.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'share_id' => $this->share->id,
            'session_title' => $this->share->originalSession->title ?? 'Untitled Chat',
            'sharer_name' => $this->share->sharedBy->name ?? 'Someone',
            'message' => $this->share->message,
        ];
    }

    /**
     * Get the database representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        // Refresh the share to get latest status and duplicated session
        $this->share->refresh();
        
        $isAccepted = $this->share->isAccepted();
        $duplicatedSessionId = $this->share->duplicated_chat_session_id;
        
        // If accepted, point to the duplicated session; otherwise point to accept endpoint
        // Use a more generic route format that frontend can handle
        if ($isAccepted && $duplicatedSessionId) {
            // Common frontend route patterns: /chat/{id} or /chat?session={id}
            // Frontend should use duplicated_session_id to construct the correct URL
            $actionUrl = "/chat/{$duplicatedSessionId}";
        } else {
            $actionUrl = "/chat/shares/{$this->share->id}";
        }
        
        return [
            'title' => 'Chat Session Shared',
            'message' => ($this->share->sharedBy->name ?? 'Someone') . 
                        ' shared "' . 
                        ($this->share->originalSession->title ?? 'Untitled Chat') . 
                        '" with you',
            'type' => 'chat_session_shared',
            'share_id' => $this->share->id,
            'share_status' => $this->share->status,
            'original_session_id' => $this->share->original_chat_session_id,
            'duplicated_session_id' => $duplicatedSessionId,
            'session_title' => $this->share->originalSession->title ?? 'Untitled Chat',
            'sharer_name' => $this->share->sharedBy->name ?? 'Someone',
            'sharer_message' => $this->share->message,
            'action_url' => $actionUrl,
        ];
    }
}
