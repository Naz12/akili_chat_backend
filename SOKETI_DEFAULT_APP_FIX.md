# Soketi DEFAULT_APP Format Fix

## Problem Found

Soketi logs showed it was using **default** app values:
- `id: 'app-id'`
- `key: 'app-key'`
- `secret: 'app-secret'`

Instead of our values (`akili-chat`, `akili-chat-key`, `akili-chat-secret`).

This means Soketi was **not reading** the environment variables `SOKETI_APP_ID`, `SOKETI_APP_KEY`, `SOKETI_APP_SECRET`.

## Solution

According to Soketi documentation, for the array driver with a default app, use:
- `SOKETI_DEFAULT_APP_ID` (not `SOKETI_APP_ID`)
- `SOKETI_DEFAULT_APP_KEY` (not `SOKETI_APP_KEY`)
- `SOKETI_DEFAULT_APP_SECRET` (not `SOKETI_APP_SECRET`)

## Updated Configuration

```ini
ExecStart=/usr/bin/docker run --rm --name akili-websocket \
  -p 6001:6001 -p 9601:9601 \
  -e SOKETI_DEBUG=1 \
  -e SOKETI_DEFAULT_APP_ID=akili-chat \
  -e SOKETI_DEFAULT_APP_KEY=akili-chat-key \
  -e SOKETI_DEFAULT_APP_SECRET=akili-chat-secret \
  akili-soketi:latest
```

## To Apply

```bash
cd /home/deploy_user_dagi/services/akili_chat_backend

# 1. Rebuild Docker image (if Dockerfile was changed)
sudo bash build-soketi-docker.sh

# 2. Copy updated service file
sudo cp akili-websocket.service /etc/systemd/system/akili-websocket.service

# 3. Reload systemd
sudo systemctl daemon-reload

# 4. Restart Soketi
sudo systemctl restart akili-websocket

# 5. Check logs
tail -f /var/log/akili-websocket.log
```

## Expected Result

After restart, check logs for:
- ✅ App with `id: 'akili-chat'` (not `'app-id'`)
- ✅ App with `key: 'akili-chat-key'` (not `'app-key'`)
- ✅ No more "App key does not exist" errors

---

**Last Updated:** 2025-12-01  
**Status:** Using DEFAULT_APP format

