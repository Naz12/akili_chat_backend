# Soketi App Key - Final Fix Guide

## Problem
Soketi returns: `App key akili-chat-key does not exist.`

## Approaches Tried

### Approach 1: Config File (Current)
- Created `soketi.config.json` with app definition
- Mounted in Docker container at `/app/config.json`
- Using `--config /app/config.json` flag

### Approach 2: Environment Variables (Array Driver)
- Using `SOKETI_APP_MANAGER_DRIVER=array`
- Apps defined via `SOKETI_APP_MANAGER_ARRAY_0_*` variables

## Current Configuration

The service file is now configured to use the **array driver** approach:

```ini
ExecStart=/usr/bin/docker run --rm --name akili-websocket \
  -p 6001:6001 -p 9601:9601 \
  --network host \
  -e SOKETI_DEBUG=1 \
  -e SOKETI_APP_MANAGER_DRIVER=array \
  -e SOKETI_APP_MANAGER_ARRAY_0_ID=akili-chat \
  -e SOKETI_APP_MANAGER_ARRAY_0_KEY=akili-chat-key \
  -e SOKETI_APP_MANAGER_ARRAY_0_SECRET=akili-chat-secret \
  akili-soketi:latest
```

## Steps to Apply

1. **Rebuild Docker Image:**
   ```bash
   cd /home/deploy_user_dagi/services/akili_chat_backend
   sudo bash build-soketi-docker.sh
   ```

2. **Apply Configuration:**
   ```bash
   sudo bash fix-soketi-env-vars.sh
   ```

3. **Test Connection:**
   ```bash
   php test/test_websocket_live_connection.php
   ```

## Diagnostic Commands

If it still doesn't work, run diagnostics:

```bash
# Check what's in the container
sudo bash test-soketi-config.sh

# Check Soketi logs
tail -f /var/log/akili-websocket.log

# Check if Soketi is running
sudo systemctl status akili-websocket

# Check container logs directly
docker logs akili-websocket
```

## Alternative: Use Redis Backend

If the array driver doesn't work, we can configure Soketi to use Redis to store apps:

1. **Store app in Redis:**
   ```bash
   redis-cli SET "soketi:apps:akili-chat" '{"id":"akili-chat","key":"akili-chat-key","secret":"akili-chat-secret"}'
   ```

2. **Update service to use Redis:**
   ```ini
   -e SOKETI_APP_MANAGER_DRIVER=redis
   -e SOKETI_REDIS_HOST=127.0.0.1
   -e SOKETI_REDIS_PORT=6379
   ```

## Check Soketi Version

The configuration method might depend on Soketi version:

```bash
docker exec akili-websocket soketi --version
```

Or check what's installed:
```bash
docker exec akili-websocket npm list -g @soketi/soketi
```

## Next Steps if Still Failing

1. **Check Soketi Documentation:**
   - Visit: https://docs.soketi.app
   - Look for "App Configuration" or "App Manager" section

2. **Check Soketi Source Code:**
   - GitHub: https://github.com/soketi/soketi
   - Look for how apps are registered

3. **Try Redis Backend:**
   - Since Redis is already running, this might be more reliable
   - Apps stored in Redis are persistent

4. **Contact Soketi Support:**
   - Open an issue on GitHub
   - Ask in Soketi Discord/community

## Expected Result

After applying the fix:
- ✅ Soketi recognizes `akili-chat-key`
- ✅ WebSocket connections succeed
- ✅ No more "App key does not exist" errors
- ✅ Frontend can connect and receive notifications

---

**Last Updated:** 2025-12-01  
**Status:** Ready to test with array driver approach

