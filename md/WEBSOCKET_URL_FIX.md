# WebSocket URL Fix

## Problem Identified

The frontend was receiving `wss://chat.akmicroservice.com/app/` from the backend, but Pusher.js constructs WebSocket URLs as:
```
wss://host:port/app/key
```

When the frontend parsed the URL and passed `wsHost` and `wsPort` separately, Pusher.js would reconstruct the URL, potentially causing path issues.

## Solution

Updated the backend `WebSocketApiController::config()` method to:

1. **Remove `/app/` path** from the WebSocket URL (Pusher.js adds it automatically)
2. **Parse the URL** to extract host and port separately
3. **Return structured data** that the frontend can use directly:
   - `websocket_url`: Full URL for reference
   - `ws_host`: Hostname only (e.g., "chat.akmicroservice.com")
   - `ws_port`: Port number (e.g., 443)
   - `wss_port`: WSS port (443)
   - `force_tls`: Whether to force TLS (true for wss://)
   - `app_key`: App key
   - `user_channel`: Channel name

## How Pusher.js Works

Pusher.js constructs the WebSocket URL using:
- `wsHost` + `wsPort` + `/app/` + `key`
- For `wss://` on port 443, it uses: `wss://host/app/key` (port omitted)

## Updated Response Format

```json
{
  "websocket_url": "wss://chat.akmicroservice.com",
  "ws_host": "chat.akmicroservice.com",
  "ws_port": 443,
  "wss_port": 443,
  "force_tls": true,
  "app_key": "akili-chat-key",
  "user_channel": "private-user.6"
}
```

## Frontend Compatibility

The frontend can now use:
- `ws_host` directly for `wsHost`
- `ws_port` for `wsPort`
- `wss_port` for `wssPort`
- `force_tls` for `forceTLS`

This ensures Pusher.js constructs the correct URL: `wss://chat.akmicroservice.com/app/akili-chat-key`

## Testing

After this fix:
1. Backend returns correct URL structure
2. Frontend uses the structured data
3. Pusher.js constructs: `wss://chat.akmicroservice.com/app/akili-chat-key`
4. Soketi receives connection on correct path
5. App key is recognized

---

**Last Updated:** 2025-12-01  
**Status:** Backend fix applied

