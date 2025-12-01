<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

class WebSocketApiController extends Controller
{
    /**
     * Authenticate WebSocket connection
     * This endpoint is called by the WebSocket server to authenticate users
     */
    public function authenticate(Request $request)
    {
        try {
            // Get the authenticated user
            $user = $request->user();

            if (!$user) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }

            // Get channel name from request
            $channelName = $request->input('channel_name');
            $socketId = $request->input('socket_id');

            if (!$channelName || !$socketId) {
                return response()->json(['error' => 'Missing channel_name or socket_id'], 400);
            }

            // Authorize the channel
            // Format: private-user.{user_id}
            if (preg_match('/^private-user\.(\d+)$/', $channelName, $matches)) {
                $channelUserId = (int) $matches[1];

                if ($channelUserId !== $user->id) {
                    return response()->json(['error' => 'Unauthorized'], 403);
                }

                // Generate auth signature (simplified - in production use proper signing)
                $auth = hash_hmac('sha256', $socketId . ':' . $channelName, config('app.key'));

                return response()->json([
                    'auth' => config('broadcasting.connections.redis.key') . ':' . $auth,
                ]);
            }

            return response()->json(['error' => 'Invalid channel'], 400);
        } catch (\Exception $e) {
            Log::error('WebSocket authentication failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Authentication failed'], 500);
        }
    }

    /**
     * Get WebSocket configuration for frontend
     */
    public function config(Request $request)
    {
        $user = $request->user();

        // Get WebSocket URL from environment or use default
        $websocketUrl = env('WEBSOCKET_URL', 'wss://chat.akmicroservice.com/app/');
        
        // Ensure it's a full URL (wss:// or ws://)
        if (!preg_match('/^wss?:\/\//', $websocketUrl)) {
            $websocketUrl = 'wss://' . $websocketUrl;
        }

        return response()->json([
            'websocket_url' => $websocketUrl,
            'app_key' => env('SOKETI_APP_KEY', 'akili-chat-key'),
            'user_channel' => 'private-user.' . $user->id,
        ]);
    }
}

