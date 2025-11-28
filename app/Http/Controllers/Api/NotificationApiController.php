<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationApiController extends Controller
{
    /**
     * Get all notifications for the authenticated user
     */
    public function index(Request $request)
    {
        return $request->user()->notifications()->latest()->take(20)->get();
    }

    /**
     * Mark a specific notification as read
     */
    public function markRead(Request $request, string $notificationId)
    {
        $user = $request->user();
        
        $notification = $user->notifications()->where('id', $notificationId)->firstOrFail();
        
        if ($notification->read_at === null) {
            $notification->markAsRead();
        }
        
        return response()->json([
            'status' => 'marked as read',
            'notification' => $notification->fresh(),
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(['status' => 'all marked as read']);
    }
}