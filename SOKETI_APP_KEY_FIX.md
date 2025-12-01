# Soketi App Key Configuration Fix

## Problem

Soketi is returning: `App key akili-chat-key does not exist.`

This means Soketi is running but doesn't recognize the app key configured via environment variables.

## Solution

Soketi needs apps to be configured via a JSON config file instead of (or in addition to) environment variables.

## Files Created/Updated

1. **`soketi.config.json`** - Soketi configuration file with app definition
2. **`Dockerfile.soketi`** - Updated to include and use config file
3. **`akili-websocket.service`** - Updated to mount config file
4. **`fix-soketi-app-key.sh`** - Script to apply the fix

## Steps to Fix

### 1. Rebuild Docker Image

```bash
cd /home/deploy_user_dagi/services/akili_chat_backend
sudo bash build-soketi-docker.sh
```

This rebuilds the Docker image with the updated Dockerfile that includes the config file.

### 2. Apply Configuration

```bash
sudo bash fix-soketi-app-key.sh
```

This script will:
- Copy the updated service file
- Reload systemd
- Restart the Soketi service
- Verify it's working

### 3. Test Connection

```bash
php test/test_websocket_live_connection.php
```

## Configuration Details

### soketi.config.json

```json
{
  "apps": [
    {
      "id": "akili-chat",
      "key": "akili-chat-key",
      "secret": "akili-chat-secret",
      "maxConnections": 100,
      "enableUserAuthentication": false,
      "enableStats": false
    }
  ],
  "cors": {
    "credentials": true,
    "origin": ["*"],
    "methods": ["GET", "POST", "PUT", "DELETE", "OPTIONS"],
    "headers": ["*"]
  },
  "debug": true
}
```

### Service File Changes

The service file now mounts the config file:
```ini
-v /home/deploy_user_dagi/services/akili_chat_backend/soketi.config.json:/app/soketi.config.json:ro
-e SOKETI_CONFIG_FILE=/app/soketi.config.json
```

### Dockerfile Changes

The Dockerfile now:
- Copies the config file into the image
- Starts Soketi with `--config /app/soketi.config.json`

## Verification

After applying the fix, check:

1. **Service Status:**
   ```bash
   sudo systemctl status akili-websocket
   ```

2. **Soketi Logs:**
   ```bash
   tail -f /var/log/akili-websocket.log
   ```
   Look for: "akili-chat-key" in the logs

3. **Test Connection:**
   ```bash
   curl http://127.0.0.1:6001
   ```

4. **Frontend Connection:**
   - Refresh browser
   - Check console for WebSocket connection success
   - Should see "WebSocket connected" instead of errors

## Expected Result

After the fix:
- ✅ Soketi recognizes `akili-chat-key`
- ✅ WebSocket connections succeed
- ✅ Frontend can connect and subscribe to channels
- ✅ Notifications are delivered via WebSocket

---

**Last Updated:** 2025-12-01  
**Status:** Ready to apply

