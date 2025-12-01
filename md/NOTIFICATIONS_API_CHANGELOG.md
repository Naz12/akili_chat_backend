# Notifications API - Changelog & Migration Guide

## Version 2.0 - WebSocket & Web Push (December 2025)

### 🎉 New Features

#### 1. Real-Time WebSocket Notifications
- **Instant delivery**: Notifications arrive in real-time when user is online
- **No polling needed**: Eliminates need for periodic API calls
- **Better UX**: Instant badge updates, no delay
- **Connection**: `wss://chat.akmicroservice.com/app/`

#### 2. Web Push API
- **Browser notifications**: Works when browser tab is closed
- **Cross-platform**: Works on desktop and mobile browsers
- **User control**: Users can subscribe/unsubscribe
- **Permission-based**: Requires user permission

### 📝 What Changed

#### Existing Endpoints (No Changes)
All existing notification endpoints work exactly the same:
- ✅ `GET /api/v1/{region}/notifications` - Still works
- ✅ `POST /api/v1/{region}/notifications/{id}/read` - Still works
- ✅ `POST /api/v1/{region}/notifications/read` - Still works

#### New Endpoints Added
- 🆕 `GET /api/v1/{region}/websocket/config` - Get WebSocket config
- 🆕 `POST /api/v1/{region}/websocket/authenticate` - Authenticate WebSocket
- 🆕 `POST /api/v1/{region}/webpush/subscribe` - Subscribe to Web Push
- 🆕 `POST /api/v1/{region}/webpush/unsubscribe` - Unsubscribe from Web Push
- 🆕 `GET /api/v1/{region}/webpush/subscriptions` - Get Web Push subscriptions

### 🔄 Migration Guide

#### For Frontends Using Polling

**Before:**
```javascript
// Poll every 30 seconds
setInterval(async () => {
    const notifications = await fetch('/api/v1/local/notifications');
    updateUI(notifications);
}, 30000);
```

**After (Recommended):**
```javascript
// Use WebSocket for real-time, keep polling as fallback
try {
    // Real-time via WebSocket
    const echo = setupWebSocket();
    echo.private(`user.${userId}`).listen('.notification', handleNotification);
} catch (error) {
    // Fallback to polling if WebSocket fails
    setInterval(async () => {
        const notifications = await fetch('/api/v1/local/notifications');
        updateUI(notifications);
    }, 30000);
}
```

#### For Frontends Not Using Notifications Yet

1. **Start with WebSocket** (recommended for real-time)
2. **Add Web Push** (optional, for browser notifications)
3. **Keep database polling** as fallback

### 📊 Notification Delivery Channels

Notifications can now be delivered via multiple channels:

1. **Database** - Always stored (existing)
2. **Email** - If user allows (existing)
3. **FCM Push** - Mobile push (existing)
4. **SMS** - Text messages (existing)
5. **WebSocket** - Real-time (NEW) ✨
6. **Web Push** - Browser push (NEW) ✨

### 🎯 Best Practices

1. **Use WebSocket for real-time**: Connect when user logs in
2. **Keep polling as fallback**: If WebSocket fails, fall back to polling
3. **Add Web Push for offline**: Subscribe when user grants permission
4. **Respect user preferences**: Check user notification preferences
5. **Handle errors gracefully**: Show user-friendly error messages

### 📚 Documentation

- **Complete Guide**: [FRONTEND_NOTIFICATIONS_INTEGRATION.md](./FRONTEND_NOTIFICATIONS_INTEGRATION.md)
- **API Reference**: [CLIENT_API_DOCUMENTATION.md](./CLIENT_API_DOCUMENTATION.md)
- **Backend Implementation**: [WEBSOCKET_WEBPUSH_IMPLEMENTATION.md](./WEBSOCKET_WEBPUSH_IMPLEMENTATION.md)

### ⚠️ Breaking Changes

**None!** All existing endpoints work exactly as before. New features are additive.

### 🚀 Quick Start

1. **Install dependencies:**
   ```bash
   npm install laravel-echo pusher-js
   ```

2. **Setup WebSocket:**
   ```javascript
   import Echo from 'laravel-echo';
   import Pusher from 'pusher-js';
   
   const echo = new Echo({
       broadcaster: 'pusher',
       key: 'akili-chat-key',
       wsHost: 'chat.akmicroservice.com',
       wssPort: 443,
       forceTLS: true,
       authEndpoint: '/api/v1/local/websocket/authenticate',
       auth: {
           headers: { Authorization: `Bearer ${token}` }
       }
   });
   ```

3. **Listen for notifications:**
   ```javascript
   echo.private(`user.${userId}`).listen('.notification', (data) => {
       console.log('Notification:', data);
       updateUI(data);
   });
   ```

4. **Setup Web Push** (optional):
   ```javascript
   const permission = await Notification.requestPermission();
   if (permission === 'granted') {
       const subscription = await registration.pushManager.subscribe({...});
       await fetch('/api/v1/local/webpush/subscribe', {
           method: 'POST',
           headers: { Authorization: `Bearer ${token}` },
           body: JSON.stringify(subscription)
       });
   }
   ```

### 📞 Support

For questions or issues:
- Check [FRONTEND_NOTIFICATIONS_INTEGRATION.md](./FRONTEND_NOTIFICATIONS_INTEGRATION.md) for detailed examples
- Review backend logs for notification delivery status
- Test endpoints using the provided examples

---

**Version:** 2.0  
**Date:** 2025-12-01  
**Status:** Production Ready

