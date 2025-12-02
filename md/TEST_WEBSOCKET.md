# How to Retest WebSocket Connection

## Quick Test Steps

### 1. Test Backend API Endpoint

```bash
cd /home/deploy_user_dagi/services/akili_chat_backend
php test/test_websocket_live_connection.php
```

This will:
- ✅ Get WebSocket config from backend
- ✅ Test authentication
- ✅ Send a test notification
- ✅ Verify notification in database

### 2. Test All API Endpoints

```bash
php test/test_notification_api_endpoints.php
```

This tests:
- ✅ WebSocket config endpoint
- ✅ WebSocket authenticate endpoint
- ✅ VAPID key endpoint
- ✅ Web Push endpoints
- ✅ Notifications API

### 3. Test End-to-End

```bash
php test/test_end_to_end_notifications.php
```

This verifies:
- ✅ Soketi server is running
- ✅ Nginx proxy is configured
- ✅ All notification channels work
- ✅ WebSocket notifications work

## Frontend Testing

### 1. Check Browser Console

1. Open your browser to the frontend
2. Open Developer Tools (F12)
3. Go to Console tab
4. Look for:
   - `🔌 Initializing WebSocket:` - Should show correct config
   - `✅ WebSocket connected to:` - Should show successful connection
   - `📡 Subscribed to channel:` - Should show channel subscription

### 2. Check Network Tab

1. Open Developer Tools (F12)
2. Go to Network tab
3. Filter by "WS" (WebSocket)
4. Look for connection to `wss://chat.akmicroservice.com/app/akili-chat-key`
5. Status should be "101 Switching Protocols"

### 3. Send Test Notification

From admin panel or backend:
```bash
# Send test notification via WebSocket
php test/test_websocket_live_connection.php
```

Then check:
- Browser console should show: `🔔 Real-time notification received:`
- Notification should appear in UI

## Troubleshooting

### If WebSocket Still Fails

1. **Check Soketi is running:**
   ```bash
   sudo systemctl status akili-websocket
   ```

2. **Check Soketi logs:**
   ```bash
   tail -f /var/log/akili-websocket.log
   ```

3. **Check Nginx logs:**
   ```bash
   tail -f /var/log/nginx/chat.akmicroservice.error.log
   tail -f /var/log/nginx/chat.akmicroservice.access.log | grep app
   ```

4. **Verify Redis has app:**
   ```bash
   redis-cli GET "soketi:apps:akili-chat"
   ```

5. **Test direct connection:**
   ```bash
   curl -i -N \
     -H "Connection: Upgrade" \
     -H "Upgrade: websocket" \
     -H "Sec-WebSocket-Version: 13" \
     -H "Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==" \
     https://chat.akmicroservice.com/app/akili-chat-key
   ```

## Expected Results

### Backend Tests
- ✅ All tests should pass
- ✅ WebSocket config returns structured data
- ✅ Authentication works
- ✅ Notifications are sent

### Frontend
- ✅ WebSocket connects successfully
- ✅ No "App key does not exist" errors
- ✅ Channel subscription works
- ✅ Real-time notifications received

---

**Last Updated:** 2025-12-01

