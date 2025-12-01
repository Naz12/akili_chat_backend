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

                // Generate auth signature for Soketi/Pusher protocol
                // Format: app_key:signature
                $appKey = env('SOKETI_APP_KEY', 'akili-chat-key');
                $appSecret = env('SOKETI_APP_SECRET', 'akili-chat-secret');
                
                // Generate signature using Pusher protocol
                $stringToSign = $socketId . ':' . $channelName;
                $signature = hash_hmac('sha256', $stringToSign, $appSecret, false);

                return response()->json([
                    'auth' => $appKey . ':' . $signature,
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
        // Pusher.js constructs URLs as: wss://host:port/app/key
        // So we need to return just the base URL (host:port) without /app/ path
        $websocketUrl = env('WEBSOCKET_URL', 'wss://chat.akmicroservice.com');
        
        // Remove /app/ path if present (Pusher.js adds it automatically)
        $websocketUrl = preg_replace('/\/app\/?$/', '', $websocketUrl);
        
        // Ensure it's a full URL (wss:// or ws://)
        if (!preg_match('/^wss?:\/\//', $websocketUrl)) {
            $websocketUrl = 'wss://' . $websocketUrl;
        }

        // Parse URL to extract host and port for Pusher.js
        $parsedUrl = parse_url($websocketUrl);
        $host = $parsedUrl['host'] ?? 'chat.akmicroservice.com';
        $port = $parsedUrl['port'] ?? ($parsedUrl['scheme'] === 'wss' ? 443 : 80);
        $scheme = $parsedUrl['scheme'] ?? 'wss';

        return response()->json([
            'websocket_url' => $websocketUrl, // Full URL for reference
            'ws_host' => $host, // Hostname only (for Pusher.js wsHost)
            'ws_port' => $port, // Port number (for Pusher.js wsPort)
            'wss_port' => 443, // WSS port (for Pusher.js wssPort)
            'force_tls' => $scheme === 'wss', // Whether to force TLS
            'app_key' => env('SOKETI_APP_KEY', 'akili-chat-key'),
            'user_channel' => 'private-user.' . $user->id,
        ]);
    }
}

