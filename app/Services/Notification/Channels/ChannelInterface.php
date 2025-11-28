<?php

namespace App\Services\Notification\Channels;

use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Interface for notification channel handlers
 */
interface ChannelInterface
{
    /**
     * Send the notification via this channel
     * 
     * @param User $user The user to notify
     * @param Notification $notification The notification instance
     * @return mixed Channel-specific result
     */
    public function send(User $user, Notification $notification);
}

