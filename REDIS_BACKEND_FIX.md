# Soketi Redis Backend Fix

## ✅ What's Been Done

1. **App stored in Redis** - The app is now stored in Redis in multiple formats:
   - `soketi:apps:akili-chat` (direct key)
   - `apps:akili-chat` (alternative format)
   - `soketi:apps` (hash format)
   - `soketi:apps:list` (list format)

2. **Service updated** - Soketi now uses Redis backend:
   ```ini
   SOKETI_APP_MANAGER_DRIVER=redis
   SOKETI_REDIS_HOST=127.0.0.1
   SOKETI_REDIS_PORT=6379
   SOKETI_REDIS_PREFIX=soketi:apps:
   ```

3. **Network mode** - Using `--network host` so Soketi can access Redis on localhost

## 📋 To Apply the Fix

Run these commands:

```bash
cd /home/deploy_user_dagi/services/akili_chat_backend

# 1. Rebuild Docker image (if needed)
sudo bash build-soketi-docker.sh

# 2. Apply service configuration
sudo bash fix-soketi-env-vars.sh

# 3. Test the connection
php test/test_websocket_live_connection.php
```

## 🔍 Verify Redis Data

Check that the app is in Redis:

```bash
redis-cli GET "soketi:apps:akili-chat"
```

Should return:
```json
{"id":"akili-chat","key":"akili-chat-key","secret":"akili-chat-secret"}
```

## 🐛 If It Still Doesn't Work

1. **Check Soketi logs:**
   ```bash
   tail -f /var/log/akili-websocket.log
   ```
   Look for Redis connection errors or app loading messages.

2. **Check Redis connection from container:**
   ```bash
   docker exec akili-websocket ping -c 1 127.0.0.1
   ```

3. **Verify Redis key format:**
   Soketi might expect a different key format. Check the logs for what it's looking for.

4. **Try different Redis prefix:**
   Update the service file to use a different prefix:
   ```ini
   -e SOKETI_REDIS_PREFIX=apps:
   ```

## 📊 Expected Result

After applying the fix:
- ✅ Soketi connects to Redis
- ✅ Soketi loads the app from Redis
- ✅ App key `akili-chat-key` is recognized
- ✅ WebSocket connections succeed
- ✅ No more "App key does not exist" errors

## 🔄 Redis Key Formats Tried

The setup script stores the app in multiple formats to ensure Soketi can find it:

1. **Direct key:** `soketi:apps:akili-chat`
2. **Alternative:** `apps:akili-chat`
3. **Hash:** `soketi:apps` (hash field: `akili-chat`)
4. **List:** `soketi:apps:list` (as list item)

One of these should work with Soketi's Redis driver.

---

**Last Updated:** 2025-12-01  
**Status:** Redis backend configured, ready to test

