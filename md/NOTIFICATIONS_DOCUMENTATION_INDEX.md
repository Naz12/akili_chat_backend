# Notifications Documentation Index

## 📚 Documentation Files for Frontend Team

### 1. **FRONTEND_NOTIFICATIONS_INTEGRATION.md** ⭐ START HERE
**Complete frontend integration guide with code examples**

- WebSocket setup and connection
- Web Push API setup
- Complete code examples
- NotificationManager class
- Service worker setup
- Best practices
- Troubleshooting

**Use this for:** Complete implementation guide with copy-paste code examples

---

### 2. **CLIENT_API_DOCUMENTATION.md**
**Updated main API documentation**

- All existing endpoints (still work)
- New WebSocket endpoints
- New Web Push endpoints
- Request/response examples
- Authentication requirements

**Use this for:** Quick API reference

---

### 3. **NOTIFICATIONS_API_CHANGELOG.md**
**What changed and migration guide**

- New features summary
- What changed vs what stayed the same
- Migration guide from polling to WebSocket
- Breaking changes (none!)
- Quick start guide

**Use this for:** Understanding what's new and how to migrate

---

### 4. **API_ENDPOINTS_LIST.md**
**Quick reference of all endpoints**

- List of all notification endpoints
- List of all WebSocket endpoints
- List of all Web Push endpoints
- Authentication requirements

**Use this for:** Quick endpoint lookup

---

## 🎯 Quick Start for Frontend Developers

### Step 1: Read the Integration Guide
Start with: **FRONTEND_NOTIFICATIONS_INTEGRATION.md**

This has everything you need:
- Complete code examples
- Step-by-step setup
- Error handling
- Best practices

### Step 2: Understand the Changes
Read: **NOTIFICATIONS_API_CHANGELOG.md**

Understand:
- What's new
- What stayed the same
- How to migrate existing code

### Step 3: Reference API Details
Use: **CLIENT_API_DOCUMENTATION.md**

For:
- Exact endpoint URLs
- Request/response formats
- Error codes

---

## 📋 Key Information

### Required Keys & Credentials

#### WebSocket Keys (Hardcoded - Use Directly in Frontend)
- **WebSocket URL**: `wss://chat.akmicroservice.com/app/`
- **App ID**: `akili-chat`
- **App Key**: `akili-chat-key` ⚠️ **Required for frontend connection**
- **App Secret**: `akili-chat-secret` (backend only, not needed by frontend)

**Note:** These values are hardcoded on the backend. Use them directly in your frontend code.

#### VAPID Public Key (Dynamic - Must Retrieve from API)
- **Endpoint**: `GET /api/v1/{region}/webpush/vapid-key`
- **Authentication**: Required (Bearer token)
- **Response**: `{ "vapid_public_key": "BKxVx..." }`

**Important:** 
- This key **MUST be retrieved from the backend API** - it cannot be hardcoded
- If you get a 503 error, Web Push is not configured on the backend
- See `FRONTEND_NOTIFICATIONS_INTEGRATION.md` for code examples

### WebSocket Connection Details
- **URL**: `wss://chat.akmicroservice.com/app/`
- **App Key**: `akili-chat-key`
- **Channel Format**: `private-user.{userId}`
- **Event Name**: `.notification`

### Web Push Details
- **Requires**: User permission + Service worker
- **VAPID Key**: Retrieved via `GET /api/v1/{region}/webpush/vapid-key`
- **Endpoints**: Subscribe, Unsubscribe, List subscriptions

### Existing Endpoints
- All existing notification endpoints work the same
- No breaking changes
- Add WebSocket/Web Push as enhancements

---

## 🔗 Related Documentation

- **Backend Implementation**: `WEBSOCKET_WEBPUSH_IMPLEMENTATION.md` (for backend reference)
- **Setup Instructions**: `WEBSOCKET_SETUP_INSTRUCTIONS.md` (for server setup)
- **Test Results**: `test/TEST_RESULTS.md` (test verification)

---

## 💡 Recommended Reading Order

1. **NOTIFICATIONS_API_CHANGELOG.md** - Understand what's new
2. **FRONTEND_NOTIFICATIONS_INTEGRATION.md** - Full implementation guide
3. **CLIENT_API_DOCUMENTATION.md** - API reference (as needed)
4. **API_ENDPOINTS_LIST.md** - Quick endpoint lookup

---

**Last Updated:** 2025-12-01  
**For:** Frontend Development Team

