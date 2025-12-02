# WebSocket Frontend Troubleshooting Guide

## ✅ Backend Status: ALL TESTS PASSING

All backend components are working correctly:
- ✅ Soketi WebSocket server running
- ✅ Nginx proxy configured
- ✅ WebSocket authentication working
- ✅ Notification broadcasting working
- ✅ All API endpoints responding

## 🔍 Frontend Connection Issue

The frontend is failing to connect to WebSocket. Here's what we know:

### Backend Configuration (Verified Working)
- **WebSocket URL:** `wss://chat.akmicroservice.com/app/`
- **App Key:** `akili-chat-key`
- **Channel Format:** `private-user.{userId}`
- **Auth Endpoint:** `/api/v1/{region}/websocket/authenticate`

### Frontend Should Use
```javascript
const echo = new Echo({
    broadcaster: 'pusher',
    key: 'akili-chat-key',
    wsHost: 'chat.akmicroservice.com',
    wsPort: 443,
    wssPort: 443,
    forceTLS: true,
    authEndpoint: '/api/v1/{region}/websocket/authenticate',
    auth: {
        headers: {
            Authorization: `Bearer ${token}`
        }
    }
});

// Subscribe to channel
echo.private(`user.${userId}`)
    .listen('.notification', (data) => {
        console.log('Notification received:', data);
    });
```

**Important:** Laravel Echo automatically converts `private-user.{id}` to `private-user.{id}` when using `.private()`, so use `user.{id}` in the code.

## 🔧 Troubleshooting Steps

### 1. Verify Nginx is Reloaded
```bash
sudo systemctl reload nginx
```

### 2. Check Frontend Configuration
Verify the frontend is using:
- ✅ Correct WebSocket URL: `wss://chat.akmicroservice.com/app/`
- ✅ Correct app key: `akili-chat-key`
- ✅ Correct channel: `private-user.{userId}` (or `user.{userId}` in Echo code)
- ✅ Correct auth endpoint: `/api/v1/{region}/websocket/authenticate`
- ✅ Authorization header with Bearer token

### 3. Check Browser Console
Look for:
- WebSocket connection errors
- Authentication errors (401, 403)
- CORS errors
- Network errors

### 4. Test Direct Connection
Open browser console and run:
```javascript
const ws = new WebSocket('wss://chat.akmicroservice.com/app/akili-chat-key');
ws.onopen = () => console.log('Connected');
ws.onerror = (e) => console.error('Error:', e);
ws.onclose = (e) => console.log('Closed:', e.code, e.reason);
```

### 5. Check Network Tab
In browser DevTools → Network → WS:
- Look for WebSocket connection attempts
- Check request/response headers
- Verify upgrade to WebSocket (101 status)

### 6. Verify Soketi is Running
```bash
sudo systemctl status akili-websocket
curl http://127.0.0.1:6001
```

### 7. Check Logs
```bash
# Soketi logs
tail -f /var/log/akili-websocket.log

# Nginx error logs
tail -f /var/log/nginx/chat.akmicroservice.error.log

# Nginx access logs (filter for WebSocket)
tail -f /var/log/nginx/chat.akmicroservice.access.log | grep app
```

## 🐛 Common Issues

### Issue 1: "WebSocket connection failed"
**Cause:** Nginx not reloaded or incorrect configuration
**Fix:** Run `sudo systemctl reload nginx`

### Issue 2: "Authentication failed" (401/403)
**Cause:** Wrong auth endpoint or missing token
**Fix:** Verify auth endpoint includes region and Authorization header

### Issue 3: "Channel subscription failed"
**Cause:** Wrong channel name format
**Fix:** Use `user.{id}` in Echo code (Laravel adds `private-` prefix automatically)

### Issue 4: "CORS error"
**Cause:** CORS headers not set for WebSocket
**Fix:** Verify AddCorsHeaders middleware is applied to WebSocket routes

### Issue 5: "404 Not Found"
**Cause:** Nginx not routing `/app/` correctly
**Fix:** Verify nginx config has `/app/` location block before `/` location

## ✅ Expected Behavior

When working correctly:
1. Frontend connects to `wss://chat.akmicroservice.com/app/akili-chat-key`
2. WebSocket upgrade succeeds (101 status)
3. Frontend subscribes to `private-user.{userId}`
4. Authentication succeeds
5. Notifications are received in real-time
6. Polling fallback is NOT used

## 📊 Current Status

- ✅ **Backend:** All systems operational
- ✅ **Soketi:** Running and responding
- ✅ **Nginx:** Configured (needs reload)
- ⚠️ **Frontend:** Connection failing (likely nginx not reloaded)

## 🚀 Next Steps

1. **Reload Nginx:**
   ```bash
   sudo bash /home/deploy_user_dagi/services/akili_chat_backend/apply-nginx-websocket-config.sh
   ```

2. **Test Connection:**
   ```bash
   php test/test_websocket_live_connection.php
   ```

3. **Check Frontend:**
   - Refresh browser
   - Check console for connection success
   - Verify notifications are received

---

**Last Updated:** 2025-12-01  
**Backend Status:** ✅ Ready  
**Action Required:** Reload nginx

