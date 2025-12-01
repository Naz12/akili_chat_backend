# Soketi Simple Environment Variable Fix

## Problem

Soketi was not recognizing the app key even with array driver configured.

## Solution

Use the **simpler environment variable format** that Soketi expects for a single app:

```ini
SOKETI_APP_ID=akili-chat
SOKETI_APP_KEY=akili-chat-key
SOKETI_APP_SECRET=akili-chat-secret
```

Instead of:
```ini
SOKETI_APP_MANAGER_DRIVER=array
SOKETI_APP_MANAGER_ARRAY_0_ID=akili-chat
SOKETI_APP_MANAGER_ARRAY_0_KEY=akili-chat-key
SOKETI_APP_MANAGER_ARRAY_0_SECRET=akili-chat-secret
```

## Why This Works

Soketi automatically uses the array driver when you provide:
- `SOKETI_APP_ID`
- `SOKETI_APP_KEY`
- `SOKETI_APP_SECRET`

This is the simplest and most reliable way to configure a single app.

## To Apply

```bash
cd /home/deploy_user_dagi/services/akili_chat_backend

# 1. Copy updated service file
sudo cp akili-websocket.service /etc/systemd/system/akili-websocket.service

# 2. Reload systemd
sudo systemctl daemon-reload

# 3. Restart Soketi
sudo systemctl restart akili-websocket

# 4. Check status
sudo systemctl status akili-websocket

# 5. Check logs
tail -f /var/log/akili-websocket.log
```

## Expected Result

After restart:
- ✅ Soketi recognizes `akili-chat-key`
- ✅ WebSocket connections succeed
- ✅ No more "App key does not exist" errors

---

**Last Updated:** 2025-12-01  
**Status:** Ready to apply

