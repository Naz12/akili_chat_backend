# WebSocket and Web Push API Implementation

## Overview

This document describes the WebSocket and Web Push API implementation for the Akili Chat Backend. Both features are implemented as standalone modules that can be used independently or integrated with the notification system.

## Architecture

### WebSocket Module (`app/Services/WebSocket/`)

A standalone WebSocket service module that can be used for:
- Real-time notifications
- Live chat features
- Presence/online status
- Any real-time bidirectional communication

**Key Components:**
- `WebSocketService.php` - Main service for sending WebSocket messages
- `Events/NotificationSent.php` - Laravel event for broadcasting notifications
- `routes/channels.php` - Channel authorization

### Web Push API Module (`app/Services/WebPush/`)

A standalone Web Push API service for browser push notifications:
- Works when browser tab is closed
- Requires user permission
- Uses VAPID keys for authentication

**Key Components:**
- `WebPushService.php` - Main service for sending Web Push notifications
- `app/Models/WebPushSubscription.php` - Model for storing subscriptions
- Database table: `web_push_subscriptions`

### Integration with Notification Service

Both modules are integrated into the existing `NotificationService`:
- `WebSocketChannel` - Sends notifications via WebSocket
- `WebPushChannel` - Sends notifications via Web Push API

## API Endpoints

### Web Push API

#### Subscribe to Web Push
```
POST /api/v1/{region}/webpush/subscribe
Authorization: Bearer {token}

Body:
{
  "endpoint": "https://fcm.googleapis.com/fcm/send/...",
  "keys": {
    "p256dh": "...",
    "auth": "..."
  }
}

Response:
{
  "status": "subscribed",
  "subscription_id": 1
}
```

#### Unsubscribe from Web Push
```
POST /api/v1/{region}/webpush/unsubscribe
Authorization: Bearer {token}

Body:
{
  "endpoint": "https://fcm.googleapis.com/fcm/send/..."
}

Response:
{
  "status": "unsubscribed"
}
```

#### Get User's Web Push Subscriptions
```
GET /api/v1/{region}/webpush/subscriptions
Authorization: Bearer {token}

Response:
{
  "subscriptions": [
    {
      "id": 1,
      "endpoint": "https://fcm.googleapis.com/fcm/send/...",
      "created_at": "2025-12-01T08:00:00.000000Z"
    }
  ],
  "count": 1
}
```

### WebSocket API

#### Get WebSocket Configuration
```
GET /api/v1/{region}/websocket/config
Authorization: Bearer {token}

Response:
{
  "websocket_url": "ws://localhost:6001",
  "user_id": 1,
  "channel": "private-user.1"
}
```

#### Authenticate WebSocket Connection
```
POST /api/v1/{region}/websocket/authenticate
Authorization: Bearer {token}

Body:
{
  "channel_name": "private-user.1",
  "socket_id": "123.456"
}

Response:
{
  "auth": "app-key:signature"
}
```

## Configuration

### Environment Variables

Add to `.env`:

```env
# Broadcasting (for WebSocket)
BROADCAST_DRIVER=redis

# Web Push VAPID Keys
VAPID_PUBLIC_KEY=your_public_key_here
VAPID_PRIVATE_KEY=your_private_key_here
VAPID_SUBJECT=mailto:your-email@example.com
```

### Generating VAPID Keys

You can generate VAPID keys using the `web-push` npm package:

```bash
npx web-push generate-vapid-keys
```

Or use an online tool like: https://web-push-codelab.glitch.me/

### WebSocket Server Setup

For production, you'll need a WebSocket server. Recommended options:

1. **Soketi** (Laravel-compatible, self-hosted)
   ```bash
   npm install -g @soketi/soketi
   soketi start
   ```

2. **Laravel Echo Server**
   ```bash
   npm install -g laravel-echo-server
   laravel-echo-server start
   ```

3. **Pusher** (Managed service)
   - Sign up at https://pusher.com
   - Add credentials to `.env`

## Usage Examples

### Sending Notification via WebSocket

```php
use App\Services\WebSocket\WebSocketService;

$webSocketService = app(WebSocketService::class);
$webSocketService->sendNotification($user, [
    'title' => 'New Message',
    'message' => 'You have a new message',
    'type' => 'message',
]);
```

### Sending Notification via Web Push

```php
use App\Services\WebPush\WebPushService;

$webPushService = app(WebPushService::class);
$webPushService->sendToUser($user, 'New Message', 'You have a new message', [
    'data' => ['message_id' => 123],
    'tag' => 'message',
]);
```

### Using Notification Service

```php
use App\Services\Notification\NotificationService;
use App\Notifications\ChatSessionSharedNotification;

$notificationService = app(NotificationService::class);
$notification = new ChatSessionSharedNotification($share);

// Send via multiple channels
$results = $notificationService->send($user, $notification, [
    NotificationService::CHANNEL_DATABASE,
    NotificationService::CHANNEL_WEBSOCKET,
    NotificationService::CHANNEL_WEBPUSH,
]);
```

## Frontend Integration

### Web Push Subscription (JavaScript)

```javascript
// Request permission
const permission = await Notification.requestPermission();

if (permission === 'granted') {
  // Register service worker
  const registration = await navigator.serviceWorker.register('/sw.js');
  
  // Get subscription
  const subscription = await registration.pushManager.subscribe({
    userVisibleOnly: true,
    applicationServerKey: 'YOUR_VAPID_PUBLIC_KEY'
  });
  
  // Send to backend
  await fetch('/api/v1/local/webpush/subscribe', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      endpoint: subscription.endpoint,
      keys: {
        p256dh: btoa(String.fromCharCode(...new Uint8Array(subscription.getKey('p256dh')))),
        auth: btoa(String.fromCharCode(...new Uint8Array(subscription.getKey('auth'))))
      }
    })
  });
}
```

### WebSocket Connection (JavaScript with Laravel Echo)

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: 'your-app-key',
    wsHost: 'localhost',
    wsPort: 6001,
    forceTLS: false,
    disableStats: true,
    authEndpoint: '/api/v1/local/websocket/authenticate',
    auth: {
        headers: {
            Authorization: `Bearer ${token}`
        }
    }
});

// Listen for notifications
Echo.private(`user.${userId}`)
    .listen('.notification', (data) => {
        console.log('Notification received:', data);
        // Handle notification
    });
```

## Testing

### Basic Functionality Test

Run the simple test script:

```bash
php test_websocket_webpush_simple.php
```

### Full API Test

Run the comprehensive test (requires server running):

```bash
php test_websocket_webpush.php
```

### Manual Testing with curl

1. **Login and get token:**
```bash
TOKEN=$(curl -X POST http://localhost:8000/api/v1/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password"}' \
  | jq -r '.access_token')
```

2. **Get WebSocket config:**
```bash
curl -X GET "http://localhost:8000/api/v1/local/websocket/config" \
  -H "Authorization: Bearer $TOKEN"
```

3. **Subscribe to Web Push:**
```bash
curl -X POST "http://localhost:8000/api/v1/local/webpush/subscribe" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "endpoint": "https://fcm.googleapis.com/fcm/send/test",
    "keys": {
      "p256dh": "test-key",
      "auth": "test-auth"
    }
  }'
```

## Database Schema

### web_push_subscriptions

```sql
CREATE TABLE web_push_subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    endpoint VARCHAR(500) NOT NULL,
    p256dh_key VARCHAR(255) NOT NULL,
    auth_key VARCHAR(255) NOT NULL,
    active BOOLEAN DEFAULT TRUE,
    user_agent VARCHAR(500) NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX (user_id),
    INDEX (endpoint),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

## Channel Preferences

WebSocket and Web Push respect user preferences:
- **WebSocket**: Always allowed (if user is online)
- **WebPush**: Uses `allow_push_notifications` preference (same as FCM push)

## Error Handling

Both services include comprehensive error handling:
- Failed WebSocket sends are logged but don't block other channels
- Invalid Web Push subscriptions are automatically marked as inactive
- All errors are logged with context for debugging

## Future Enhancements

1. **Connection Tracking**: Track active WebSocket connections
2. **Presence System**: Show online/offline status
3. **Typing Indicators**: Real-time typing status in chat
4. **Message Delivery Status**: Real-time delivery confirmations
5. **Multi-device Sync**: Sync notifications across devices

## Notes

- WebSocket requires a WebSocket server (Soketi, Laravel Echo Server, or Pusher)
- Web Push requires HTTPS in production (except localhost)
- VAPID keys must be configured for Web Push to work
- Both modules are designed to be independent and reusable

