<?php

namespace App\Services\Notification\Channels;

use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Database notification channel
 * Uses Laravel's built-in notification system
 */
class DatabaseChannel implements ChannelInterface
{
    /**
     * Send notification to database
     */
    public function send(User $user, Notification $notification)
    {
        // Create a temporary notification that uses Laravel's standard 'database' channel
        // We'll manually create the database record using the notification's toDatabase() method
        if (!method_exists($notification, 'toDatabase')) {
            throw new \RuntimeException(
                'Notification must implement toDatabase() method for database channel'
            );
        }

        $data = $notification->toDatabase($user);
        
        // Create the notification record directly in the database
        // Laravel will automatically JSON encode the data array
        $user->notifications()->create([
            'id' => \Illuminate\Support\Str::uuid(),
            'type' => get_class($notification),
            'data' => $data, // Laravel handles JSON encoding automatically
            'read_at' => null,
        ]);
        
        return ['status' => 'sent'];
    }
}

