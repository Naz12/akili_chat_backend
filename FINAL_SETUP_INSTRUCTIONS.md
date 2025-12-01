# Final Setup Instructions - WebSocket Notifications

## ✅ What's Been Fixed

1. **Docker Image** - Fixed glibc compatibility (switched to `node:18-slim`)
2. **Nginx Config** - Added `/app/` location block with `proxy_buffering off`
3. **WebSocket Authentication** - Fixed to use correct app key and secret
4. **All Backend Tests** - 10/10 tests passing

## ⚠️ Action Required

The nginx config file requires sudo to save. Run this command:

```bash
sudo bash /home/deploy_user_dagi/services/akili_chat_backend/apply-nginx-websocket-config.sh
```

This script will:
- Backup the current nginx config
- Verify the `/app/` location block has `proxy_buffering off`
- Test nginx configuration
- Reload nginx
- Check Soketi service status
- Test WebSocket endpoint

## Current Status

### ✅ Working
- Soketi WebSocket server - Running on port 6001
- Redis - Connected
- Database - Working
- All API endpoints - Working
- WebSocket authentication - Fixed
- VAPID keys - Configured (now returning 200)

### ⚠️ Needs Reload
- Nginx - Config updated but needs reload

## After Running the Script

1. **Test the connection:**
   ```bash
   php test/test_end_to_end_notifications.php
   ```

2. **Check frontend connection:**
   - Frontend should automatically retry WebSocket connection
   - Should see "WebSocket connected" in console
   - Should stop using polling fallback

3. **Monitor logs if issues persist:**
   ```bash
   # Soketi logs
   tail -f /var/log/akili-websocket.log
   
   # Nginx error logs
   tail -f /var/log/nginx/chat.akmicroservice.error.log
   
   # Nginx access logs
   tail -f /var/log/nginx/chat.akmicroservice.access.log | grep app
   ```

## Expected Frontend Behavior

After nginx reload:
- ✅ WebSocket connects to `wss://chat.akmicroservice.com/app/akili-chat-key`
- ✅ Subscribes to `private-user.{userId}` channel
- ✅ Receives real-time notifications
- ✅ Stops using polling fallback

## Troubleshooting

If WebSocket still fails after reload:

1. **Check Soketi is running:**
   ```bash
   sudo systemctl status akili-websocket
   ```

2. **Check nginx config:**
   ```bash
   sudo nginx -t
   ```

3. **Check if port 6001 is listening:**
   ```bash
   sudo netstat -tlnp | grep 6001
   ```

4. **Test direct connection:**
   ```bash
   curl http://127.0.0.1:6001
   ```

---

**Last Updated:** 2025-12-01  
**Status:** Backend ready, nginx needs reload

