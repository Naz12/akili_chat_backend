# Dockerfile Fix - Remove Config File Reference

## Problem Found

The Dockerfile was still trying to use a config file:
```dockerfile
CMD ["soketi", "start", "--host", "0.0.0.0", "--port", "6001", "--config", "/app/config.json", "--debug"]
```

But we're using **environment variables** (`SOKETI_APP_ID`, `SOKETI_APP_KEY`, `SOKETI_APP_SECRET`), not a config file.

This caused Soketi to:
- Look for a config file that doesn't exist
- Not read the environment variables properly
- Fail to recognize the app key

## Solution

Removed the `--config /app/config.json` flag from the Dockerfile:
```dockerfile
CMD ["soketi", "start", "--host", "0.0.0.0", "--port", "6001", "--debug"]
```

Now Soketi will use environment variables directly.

## To Apply

```bash
cd /home/deploy_user_dagi/services/akili_chat_backend

# 1. Rebuild Docker image
sudo bash build-soketi-docker.sh

# 2. Restart Soketi service
sudo systemctl restart akili-websocket

# 3. Check logs
tail -f /var/log/akili-websocket.log
```

## Expected Result

After rebuild and restart:
- ✅ Soketi starts without config file errors
- ✅ Reads environment variables (`SOKETI_APP_ID`, `SOKETI_APP_KEY`, `SOKETI_APP_SECRET`)
- ✅ Recognizes `akili-chat-key`
- ✅ WebSocket connections succeed

---

**Last Updated:** 2025-12-01  
**Status:** Dockerfile fixed, ready to rebuild

