# Guest User Authentication & Session Migration Guide

**For Frontend Developers**

This guide explains how guest users work, how sessions are migrated when users log in, and how to properly fetch chat sessions for authenticated users.

---

## 🚨 CRITICAL: How to Fetch Chats for Logged-In Users

### The Problem
**If the frontend does not send the JWT token after login, the backend will treat the request as a guest request and return guest sessions instead of user sessions.**

### The Solution

#### Step 1: Store JWT Token After Login/Registration

```javascript
// After successful login/registration
async function handleLogin(email, password) {
  const response = await fetch('https://chat.akmicroservice.com/api/v1/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password })
  });
  
  const data = await response.json();
  
  // ⚠️ CRITICAL: Store the token immediately
  if (data.access_token) {
    localStorage.setItem('auth_token', data.access_token);
    // Also store user info
    localStorage.setItem('user', JSON.stringify(data.user));
  }
  
  return data;
}
```

#### Step 2: Send Token in Authorization Header When Fetching Chats

```javascript
// Fetch chat sessions for logged-in user
async function fetchChatSessions(region = 'intl') {
  // Get token from storage
  const token = localStorage.getItem('auth_token');
  
  if (!token) {
    console.error('No auth token found! User must be logged in.');
    return [];
  }
  
  const response = await fetch(
    `https://chat.akmicroservice.com/api/v1/${region}/chat/sessions?with_messages=true`,
    {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${token}`,  // ⚠️ CRITICAL: Must include this!
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      }
    }
  );
  
  if (!response.ok) {
    throw new Error(`HTTP error! status: ${response.status}`);
  }
  
  const sessions = await response.json();
  return sessions;
}
```

#### Step 3: Using Axios (Recommended)

```javascript
import axios from 'axios';

// Set up axios interceptor to include token in all requests
axios.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;  // ⚠️ CRITICAL
  }
  return config;
});

// Then simply call the endpoint
async function fetchChatSessions(region = 'intl') {
  try {
    const response = await axios.get(
      `https://chat.akmicroservice.com/api/v1/${region}/chat/sessions?with_messages=true`
    );
    return response.data;
  } catch (error) {
    console.error('Error fetching chat sessions:', error);
    return [];
  }
}
```

### How to Verify It's Working

1. **Check Request Headers in Browser DevTools:**
   - Open Network tab → Find `/chat/sessions` request
   - Check Request Headers
   - You should see: `Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...`

2. **Check the Response:**
   - ✅ **If token is sent correctly:** You'll get all user sessions (e.g., 7 sessions)
   - ❌ **If token is missing:** You'll get guest sessions (e.g., 4 sessions) or empty array

3. **Check Backend Logs** (if you have access):
   - Look for: `"has_user":true,"user_id":5` (should be true for logged-in users)
   - If you see: `"has_user":false` → Token is not being sent or validated

### Common Mistakes

❌ **Wrong:**
```javascript
// Not storing token after login
const response = await login(email, password);
// Token is lost!

// Not sending token in request
fetch('/api/v1/intl/chat/sessions');  // Missing Authorization header
```

✅ **Correct:**
```javascript
// Store token after login
const { access_token } = await login(email, password);
localStorage.setItem('auth_token', access_token);

// Send token in request
fetch('/api/v1/intl/chat/sessions', {
  headers: {
    'Authorization': `Bearer ${access_token}`  // ✅
  }
});
```

### Troubleshooting

**Problem:** Getting guest sessions instead of user sessions after login

**Solutions:**
1. ✅ Verify token is stored after login: `console.log(localStorage.getItem('auth_token'))`
2. ✅ Verify token is sent in request: Check Network tab → Request Headers → Authorization
3. ✅ Verify token format: Should be `Bearer {token}`, not just `{token}`
4. ✅ Check token expiration: Tokens expire after 30 days, refresh if needed
5. ✅ Clear browser cache and try again

---

## 📋 Table of Contents

1. [Guest User Flow](#guest-user-flow)
2. [Login/Registration Flow](#loginregistration-flow)
3. [Session Migration Process](#session-migration-process)
4. [Fetching Chat Sessions](#fetching-chat-sessions)
5. [Common Issues & Troubleshooting](#common-issues--troubleshooting)
6. [Implementation Checklist](#implementation-checklist)

---

## 🔍 Guest User Flow

### How Guest Users Work

1. **First Visit (No Login)**
   - User visits the app without logging in
   - Backend automatically creates a **guest session** using device fingerprint
   - Device fingerprint is generated from:
     - IP address
     - User-Agent (browser)
     - Accept-Language header
     - Optional: `X-Guest-UUID` header or cookie (for persistence)

2. **Creating Chat Sessions as Guest**
   - All chat sessions created are linked to the **guest_session_id**
   - Sessions have `user_id = NULL` and `guest_session_id = {id}`
   - Sessions expire after 24 hours
   - Guest users can create, view, rename, and delete their own sessions

3. **Guest Session Identification**
   - Backend identifies guest by device fingerprint
   - Optional: Frontend can provide `X-Guest-UUID` header for consistency
   - Guest sessions are stored in `guest_sessions` table

### Guest User Limitations

- ✅ Can create chat sessions
- ✅ Can send messages
- ✅ Can view their own sessions
- ❌ Cannot share sessions
- ❌ Sessions expire after 24 hours
- ❌ No access to billing/subscriptions
- ❌ No access to usage statistics

---

## 🔐 Login/Registration Flow

### Step-by-Step Process

#### 1. User Initiates Login/Registration

**Login Request:**
```http
POST /api/v1/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password123"
}
```

**Registration Request:**
```http
POST /api/v1/register
Content-Type: application/json

{
  "name": "User Name",
  "email": "user@example.com",
  "password": "password123",
  "region": "local"  // Optional: "local" or "intl", defaults to "local"
}
```

#### 2. Backend Response

**Success Response:**
```json
{
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "user": {
    "id": 5,
    "name": "User Name",
    "email": "user@example.com",
    "region": "intl",  // ✅ Region is included
    "email_verified_at": "2025-11-29T10:00:00.000000Z",
    "created_at": "2025-11-29T10:00:00.000000Z",
    "updated_at": "2025-11-29T10:00:00.000000Z"
  }
}
```

#### 3. ⚠️ CRITICAL: Frontend Must Store Token

**What to Store:**
- `access_token` - The JWT token
- `user` object - Contains user ID, email, region, etc.

**Where to Store:**
- localStorage or sessionStorage (recommended)
- Secure storage (for production apps)
- State management (Redux, Zustand, etc.)

**Example:**
```javascript
// After successful login
const response = await login(email, password);
localStorage.setItem('auth_token', response.data.access_token);
localStorage.setItem('user', JSON.stringify(response.data.user));
```

#### 4. Automatic Session Migration

**What Happens Automatically:**
1. Backend finds guest sessions for the device fingerprint
2. All guest chat sessions are migrated to the user account
3. Sessions are updated:
   - `user_id` is set to the logged-in user's ID
   - `guest_session_id` is cleared (set to NULL)
   - `is_guest` is set to false
4. All chat messages are also migrated
5. Migration happens **automatically** - no additional API call needed

**Migration Logs (Backend):**
```
Guest sessions migrated {
  "user_id": 5,
  "guest_session_id": 3,
  "sessions_migrated": 2
}
```

---

## 📤 Fetching Chat Sessions

### For Authenticated Users (After Login)

#### ⚠️ CRITICAL: Must Send JWT Token

**Request:**
```http
GET /api/v1/{region}/chat/sessions?with_messages=true
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Headers Required:**
- `Authorization: Bearer {token}` - **MUST be included**
- `Content-Type: application/json`

**What Happens:**
1. Backend validates JWT token
2. Extracts user ID from token
3. Queries: `WHERE user_id = {authenticated_user_id}`
4. Returns all sessions where `user_id` matches

**Response:**
```json
[
  {
    "id": "2be79cf2-076a-4491-b572-7845ffc4f92f",
    "title": "this is a guest user",
    "created_at": "2025-11-29T10:57:35.000000Z",
    "is_guest": false
  },
  {
    "id": "2ddebe0f-be69-41ff-b495-6da6c98199cf",
    "title": "hi, what does akili means",
    "created_at": "2025-11-29T10:49:43.000000Z",
    "is_guest": false
  }
  // ... up to 10 most recent sessions
]
```

### For Guest Users (No Login)

**Request:**
```http
GET /api/v1/{region}/chat/sessions
X-Guest-UUID: optional-uuid-here  // Optional for consistency
```

**No Authorization Header Required**

**What Happens:**
1. Backend identifies guest by device fingerprint
2. Queries: `WHERE guest_session_id = {guest_session_id} AND user_id IS NULL`
3. Returns only guest sessions (not migrated yet)

**Response:**
```json
[
  {
    "id": "3e6f5f0d-40e2-4cdf-b278-6adb5cf19040",
    "title": "hello there",
    "created_at": "2025-11-29T10:24:03.000000Z",
    "is_guest": true
  }
  // ... guest sessions only
]
```

---

## 🔄 Session Migration Process

### What Gets Migrated

When a guest user logs in or registers:

1. **Chat Sessions**
   - All sessions with `guest_session_id` matching device fingerprint
   - Sessions are updated: `user_id` set, `guest_session_id` cleared

2. **Chat Messages**
   - All messages in migrated sessions
   - Messages are updated: `user_id` set, `guest_session_id` cleared

3. **Session Metadata**
   - Titles, timestamps, etc. are preserved
   - Nothing is lost in the migration

### Migration Timing

- **Automatic**: Happens during login/registration
- **Immediate**: Sessions are available right after login
- **No API Call Needed**: Backend handles it automatically

### What Users See

**Before Login:**
- Guest sessions (temporary, expire in 24 hours)

**After Login:**
- Same sessions, now permanent (no expiration)
- All previous guest sessions appear in user account
- New sessions created after login also appear

---

## ⚠️ Common Issues & Troubleshooting

### Issue 1: Getting Guest Sessions Instead of User Sessions

**Symptoms:**
- User logs in successfully
- Frontend shows `userId: 5` and `isGuest: false`
- But API returns guest sessions (4 sessions) instead of user sessions (7 sessions)

**Root Cause:**
- JWT token is **not being sent** in the `Authorization` header
- OR token is invalid/expired
- Backend treats request as guest and returns guest sessions

**Solution:**
1. Verify token is stored after login
2. Verify token is sent in `Authorization: Bearer {token}` header
3. Check token is not expired
4. Verify token format is correct

**Check in Browser DevTools:**
```javascript
// Check if token is stored
localStorage.getItem('auth_token')

// Check request headers in Network tab
// Should see: Authorization: Bearer eyJ0eXAi...
```

### Issue 2: Empty Sessions Array After Login

**Symptoms:**
- User logs in
- API returns empty array: `[]`
- But user has sessions in database

**Root Cause:**
- Token is being sent but not validated
- OR user_id in token doesn't match database
- OR sessions haven't been migrated yet

**Solution:**
1. Check backend logs for authentication errors
2. Verify token is valid (not expired)
3. Check if migration happened (check logs)
4. Verify user_id matches

### Issue 3: Sessions Not Migrating

**Symptoms:**
- User logs in
- Guest sessions don't appear in user account
- Migration logs show 0 sessions migrated

**Root Cause:**
- Device fingerprint changed (different browser/IP)
- Guest sessions expired (24 hours)
- Guest sessions already migrated

**Solution:**
1. Check migration logs in backend
2. Verify device fingerprint is consistent
3. Check if guest sessions exist before login
4. Ensure guest sessions haven't expired

### Issue 4: Wrong Region Endpoint

**Symptoms:**
- User has region "intl" but frontend calls "/api/v1/local/..."
- OR user has region "local" but frontend calls "/api/v1/intl/..."

**Root Cause:**
- Frontend not using `user.region` from login response
- Hardcoded region in frontend

**Solution:**
1. Use `user.region` from login response
2. Store region with user data
3. Use correct region in all API calls

---

## ✅ Implementation Checklist

### After Login/Registration

- [ ] Store `access_token` from response
- [ ] Store `user` object (contains id, email, region)
- [ ] Use `user.region` for all subsequent API calls
- [ ] Include token in `Authorization: Bearer {token}` header
- [ ] Clear token on logout

### For Chat Sessions Endpoint

- [ ] **If user is logged in:**
  - [ ] Include `Authorization: Bearer {token}` header
  - [ ] Use correct region: `/api/v1/{user.region}/chat/sessions`
  - [ ] Expect user sessions (with `user_id` set)

- [ ] **If user is NOT logged in:**
  - [ ] No Authorization header needed
  - [ ] Optional: Include `X-Guest-UUID` header
  - [ ] Expect guest sessions (with `guest_session_id` set)

### Token Management

- [ ] Store token securely (localStorage/sessionStorage)
- [ ] Check token expiration
- [ ] Refresh token if expired (use `/api/v1/refresh-token`)
- [ ] Clear token on logout
- [ ] Handle 401 errors (token invalid/expired)

### Error Handling

- [ ] Handle 401 Unauthorized (invalid/expired token)
- [ ] Handle 404 Not Found (no sessions)
- [ ] Handle empty array response
- [ ] Show appropriate messages to user

---

## 📝 Example Implementation

### Login Flow

```javascript
// 1. Login
const loginResponse = await axios.post('/api/v1/login', {
  email: 'user@example.com',
  password: 'password123'
});

// 2. Store token and user data
const { access_token, user } = loginResponse.data;
localStorage.setItem('auth_token', access_token);
localStorage.setItem('user', JSON.stringify(user));

// 3. Use region from user object
const region = user.region; // "local" or "intl"
```

### Fetching Chat Sessions

```javascript
// Get token and user from storage
const token = localStorage.getItem('auth_token');
const user = JSON.parse(localStorage.getItem('user'));

// Set up axios instance with token
const apiClient = axios.create({
  baseURL: `https://chat.akmicroservice.com/api/v1/${user.region}`,
  headers: {
    'Authorization': `Bearer ${token}`,  // ✅ CRITICAL
    'Content-Type': 'application/json'
  }
});

// Fetch sessions
const response = await apiClient.get('/chat/sessions?with_messages=true');
const sessions = response.data; // Array of user sessions
```

### Checking Authentication Status

```javascript
function isAuthenticated() {
  const token = localStorage.getItem('auth_token');
  const user = localStorage.getItem('user');
  return !!(token && user);
}

function getAuthHeaders() {
  const token = localStorage.getItem('auth_token');
  return {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  };
}
```

---

## 🔍 Debugging Tips

### Check Backend Logs

Look for these log entries:
```
✅ JWT login success
Guest sessions migrated { user_id: 5, sessions_migrated: 2 }
Chat sessions request { has_user: true, user_id: 5 }
Filtering sessions for authenticated user { user_id: 5 }
```

### Check Frontend Network Tab

1. Open Browser DevTools → Network tab
2. Find the `/chat/sessions` request
3. Check **Request Headers**:
   - Should see: `Authorization: Bearer eyJ0eXAi...`
4. Check **Response**:
   - Should return user sessions (with `user_id` set)
   - NOT guest sessions (with `guest_session_id` set)

### Verify Token

```javascript
// Decode JWT token (for debugging)
function decodeToken(token) {
  const base64Url = token.split('.')[1];
  const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
  const jsonPayload = decodeURIComponent(atob(base64).split('').map(function(c) {
    return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
  }).join(''));
  return JSON.parse(jsonPayload);
}

const token = localStorage.getItem('auth_token');
const decoded = decodeToken(token);
console.log('User ID from token:', decoded.sub);
```

---

## 📚 Key Takeaways

1. **JWT Token is Critical**: Without it, backend treats user as guest
2. **Region Matters**: Use `user.region` from login response
3. **Migration is Automatic**: No additional API call needed
4. **Token Must Be Sent**: Include in `Authorization` header for all authenticated requests
5. **Guest vs User Sessions**: Backend returns different data based on authentication status

---

## 🆘 Still Having Issues?

If you're still seeing guest sessions after login:

1. **Verify Token is Stored**: Check localStorage/sessionStorage
2. **Verify Token is Sent**: Check Network tab → Request Headers
3. **Check Backend Logs**: Look for authentication errors
4. **Verify Token Format**: Should be `Bearer {token}`
5. **Check Token Expiration**: Token might be expired

**Common Mistake:**
```javascript
// ❌ WRONG - Missing "Bearer " prefix
headers: { 'Authorization': token }

// ✅ CORRECT
headers: { 'Authorization': `Bearer ${token}` }
```

---

**Last Updated:** November 29, 2025

