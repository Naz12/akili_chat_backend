# 🚨 URGENT: Frontend Sending Basic Auth Instead of Bearer

## Problem

**Backend is receiving:** `Authorization: Basic Og==` (10 characters)
**Backend expects:** `Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...` (300+ characters)

**"Basic Og==" is base64 encoded ":" (empty credentials)** - This happens when:
1. Authorization header is not set correctly
2. Browser auto-adds Basic auth when header is malformed
3. Proxy/load balancer converts Bearer to Basic

---

## Solution

### Check Your Request Headers

**In your frontend code, verify:**

```javascript
// ❌ WRONG - This might result in Basic auth
fetch('/api/v1/intl/chat/sessions', {
  headers: {
    'Authorization': token  // Missing "Bearer " prefix!
  }
});

// ✅ CORRECT
fetch('/api/v1/intl/chat/sessions', {
  headers: {
    'Authorization': `Bearer ${token}`  // Must include "Bearer "
  }
});
```

### Using Axios

```javascript
import axios from 'axios';

// ❌ WRONG - Might send Basic auth
axios.defaults.headers.common['Authorization'] = token;

// ✅ CORRECT
axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;

// OR use interceptor
axios.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;  // ✅ Must include "Bearer "
  }
  return config;
});
```

### Verify in Browser DevTools

1. Open DevTools → Network tab
2. Find the `/chat/sessions` request
3. Click on it → Headers tab
4. Look at "Request Headers"
5. Check the `Authorization` header:

**✅ CORRECT:**
```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwczovL2NoYXQuYWttaWNyb3NlcnZpY2UuY29tIiwiaWF0IjoxNzY0NDE5ODM1LCJleHAiOjE3NjcwMTE4MzUsIm5iZiI6MTc2NDQxOTgzNSwianRpIjoiNTgxRFVuVVBNUUFQb2pmNCIsInN1YiI6IjUiLCJwcnYiOiIyM2JkNWM4OTQ5ZjYwMGFkYjM5ZTcwMWM0MDA4NzJkYjdhNTk3NmY3In0.NzQB4LC1gOTGHKNBoCpT-cvpYL81mUvMc2ez81xyLsM
```

**❌ WRONG:**
```
Authorization: Basic Og==
```

---

## Common Causes

### 1. Missing "Bearer " Prefix

```javascript
// ❌ WRONG
headers: { 'Authorization': token }

// ✅ CORRECT
headers: { 'Authorization': `Bearer ${token}` }
```

### 2. Double "Bearer "

```javascript
// ❌ WRONG - Results in "Bearer Bearer token"
const token = 'Bearer ' + actualToken;
headers: { 'Authorization': `Bearer ${token}` }

// ✅ CORRECT
const token = actualToken;  // Token without "Bearer "
headers: { 'Authorization': `Bearer ${token}` }
```

### 3. Browser Auto-Authentication

Some browsers automatically add Basic auth if the Authorization header is malformed. To prevent this:

```javascript
// Explicitly set the header
const headers = new Headers();
headers.set('Authorization', `Bearer ${token}`);
headers.set('Content-Type', 'application/json');

fetch(url, {
  method: 'GET',
  headers: headers
});
```

### 4. Proxy/Load Balancer Issue

If you're behind a proxy that converts headers, you may need to:
- Use a different header name (backend now checks `X-Authorization` as fallback)
- Configure the proxy to preserve the Authorization header
- Contact your DevOps team

---

## Quick Test

Run this in your browser console:

```javascript
// 1. Get token
const token = localStorage.getItem('auth_token');
console.log('Token:', token);
console.log('Token length:', token?.length);

// 2. Test request
fetch('https://chat.akmicroservice.com/api/v1/intl/chat/sessions', {
  headers: {
    'Authorization': `Bearer ${token}`,  // ✅ Must include "Bearer "
    'Content-Type': 'application/json'
  }
})
.then(r => r.json())
.then(data => {
  console.log('Sessions:', data.length);
  console.log('First session:', data[0]);
});
```

**Check Network tab:**
- Request Headers → Authorization
- Should see: `Bearer eyJ0eXAi...` (300+ chars)
- Should NOT see: `Basic Og==` (10 chars)

---

## Backend Behavior

**When Bearer token is sent correctly:**
- Backend logs: `"auth_header_preview":"Bearer eyJ0eXAi..."`
- Backend logs: `"has_user":true,"user_id":5`
- Response: All user sessions (e.g., 11 sessions)

**When Basic auth is sent:**
- Backend logs: `"auth_header_preview":"Basic Og==..."`
- Backend logs: `"has_user":false`
- Response: Guest sessions or empty array

---

**Last Updated:** November 29, 2025


