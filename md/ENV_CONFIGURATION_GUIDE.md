# .env Configuration Guide for WebSocket/Soketi

## Required Environment Variables

Add these to your `.env` file for WebSocket/Soketi to work:

```env
# ============================================
# WEBSOCKET / SOKETI CONFIGURATION
# ============================================

# WebSocket URL (base URL, without /app/ path)
# Pusher.js will add /app/ automatically
WEBSOCKET_URL=wss://chat.akmicroservice.com

# Soketi App Credentials
# These are used by the backend API to return to frontend
SOKETI_APP_ID=akili-chat
SOKETI_APP_KEY=akili-chat-key
SOKETI_APP_SECRET=akili-chat-secret

# Broadcasting Driver (for Laravel Broadcasting)
BROADCAST_DRIVER=redis

# ============================================
# WEB PUSH CONFIGURATION (Optional)
# ============================================

# VAPID Keys for Web Push Notifications
# Generate with: npx web-push generate-vapid-keys
VAPID_PUBLIC_KEY=your_vapid_public_key_here
VAPID_PRIVATE_KEY=your_vapid_private_key_here
VAPID_SUBJECT=mailto:your-email@example.com

# ============================================
# REDIS CONFIGURATION (for Broadcasting)
# ============================================

# Redis connection (should already be configured)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

## Important Notes

### 1. WEBSOCKET_URL
- **Format:** `wss://chat.akmicroservice.com` (no trailing `/app/`)
- **Why:** Pusher.js automatically adds `/app/{key}` to the URL
- **If using HTTP:** `ws://localhost:6001` (for development)

### 2. SOKETI_APP_* Variables
- These are used by the **backend API** to return config to frontend
- The **Soketi service** uses these same values via Docker environment variables
- Must match between `.env` and `akili-websocket.service`

### 3. BROADCAST_DRIVER
- Set to `redis` for Laravel Broadcasting to work
- Required for WebSocket notifications

### 4. VAPID Keys (Optional)
- Only needed if using Web Push notifications
- Can be left empty if not using Web Push
- Frontend will handle gracefully if not configured

## Verification

After adding these variables:

1. **Clear Laravel config cache:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

2. **Test backend API:**
   ```bash
   php test/test_notification_api_endpoints.php
   ```

3. **Check WebSocket config endpoint:**
   ```bash
   curl -H "Authorization: Bearer {token}" \
        https://chat.akmicroservice.com/api/v1/local/websocket/config
   ```

## Current Status

Based on the code, the backend expects:
- ✅ `SOKETI_APP_KEY` - Used in WebSocketApiController
- ✅ `SOKETI_APP_SECRET` - Used for authentication
- ✅ `WEBSOCKET_URL` - Used for WebSocket URL (defaults to `wss://chat.akmicroservice.com/app/`)

If these are missing, the backend will use defaults, but it's better to set them explicitly.

---

**Last Updated:** 2025-12-01

