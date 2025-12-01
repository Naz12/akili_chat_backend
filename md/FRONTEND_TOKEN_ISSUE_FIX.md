# 🚨 URGENT: Frontend Token Issue - Getting Guest Sessions Instead of User Sessions

## Problem Summary

**Backend logs show:**
```
"auth_header_length":10
"token_length":10
"has_user":false
```

**This means:** The frontend is sending a JWT token that's only **10 characters long**, which is invalid!

**A valid JWT token should be:**
- **300+ characters long** (typically 316 characters)
- Format: `eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwczovL2NoYXQuYWttaWNyb3NlcnZpY2UuY29tIiwiaWF0IjoxNzY0NDE3NTAyLCJleHAiOjE3NjcwMDk1MDIsIm5iZiI6MTc2NDQxNzUwMiwianRpIjoiUjVJZ3oxVFVENzZSMlh3SCIsInN1YiI6IjUiLCJwcnYiOiIyM2JkNWM4OTQ5ZjYwMGFkYjM5ZTcwMWM0MDA4NzJkYjdhNTk3NmY3In0.yny3GgieNa6mE-rgVsZ5aZcbVDdgvMzuSq5QxVzvO1M`

---

## Root Cause

**CRITICAL ISSUE:** The frontend is sending **"Basic" authentication** instead of **"Bearer" authentication**!

**Backend logs show:**
- `"auth_header_preview":"Basic Og==..."` - This is Basic auth, not Bearer!
- `"token_starts_with_bearer":false` - Header doesn't start with "Bearer"
- `"auth_header_length":10` - Only 10 characters (Basic Og== is base64 for ":")

**"Basic Og==" decodes to ":" (empty username:password)** - This is what browsers send when the Authorization header is malformed or missing.

**The frontend MUST send:**
- `Authorization: Bearer {token}` (300+ characters)

**NOT:**
- `Authorization: Basic Og==` (10 characters)
- `Authorization: {token}` (missing "Bearer ")

---

## Solution

### Step 1: Verify Login Response

The login endpoint returns:
```json
{
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",  // 316 characters
  "user": { ... }
}
```

**Check your login handler:**
```javascript
async function handleLogin(email, password) {
  const response = await fetch('https://chat.akmicroservice.com/api/v1/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password })
  });
  
  const data = await response.json();
  
  // ⚠️ CRITICAL: Check the token length
  console.log('Login response:', data);
  console.log('Token field:', data.access_token);
  console.log('Token length:', data.access_token?.length);  // Should be 300+
  
  if (data.access_token && data.access_token.length > 200) {
    localStorage.setItem('auth_token', data.access_token);
    console.log('✅ Token stored successfully, length:', data.access_token.length);
  } else {
    console.error('❌ Token is too short or missing!', data);
  }
  
  return data;
}
```

### Step 2: Verify Token Storage

**Before making any API request, check:**
```javascript
const token = localStorage.getItem('auth_token');
console.log('Stored token:', token);
console.log('Token length:', token?.length);  // ⚠️ Should be 300+, not 10!

if (!token || token.length < 200) {
  console.error('❌ Token is invalid or missing!');
  // User needs to login again
  return;
}
```

### Step 3: Verify Token in Request

**Check Network tab in browser DevTools:**
1. Open DevTools → Network tab
2. Find the `/chat/sessions` request
3. Click on it → Headers tab
4. Look for `Authorization` header
5. Should see: `Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...` (300+ chars)

**If you see:**
- `Authorization: Bearer abc123` (only 10 chars) → ❌ Token is truncated
- `Authorization: Bearer` (no token) → ❌ Token not being sent
- No `Authorization` header → ❌ Header not being added

### Step 4: Fix Your Code

**Using Fetch:**
```javascript
async function fetchChatSessions(region = 'intl') {
  const token = localStorage.getItem('auth_token');
  
  // ⚠️ Verify token before using
  if (!token || token.length < 200) {
    console.error('Invalid token! Length:', token?.length);
    throw new Error('Invalid or missing token');
  }
  
  console.log('Sending token, length:', token.length);
  console.log('Token preview:', token.substring(0, 50) + '...');
  
  const response = await fetch(
    `https://chat.akmicroservice.com/api/v1/${region}/chat/sessions?with_messages=true`,
    {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${token}`,  // Full 300+ char token
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      }
    }
  );
  
  return await response.json();
}
```

**Using Axios:**
```javascript
import axios from 'axios';

// Set up interceptor
axios.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token');
  
  // ⚠️ Verify token before adding
  if (token && token.length > 200) {
    config.headers.Authorization = `Bearer ${token}`;
    console.log('✅ Token added to request, length:', token.length);
  } else {
    console.error('❌ Invalid token, length:', token?.length);
  }
  
  return config;
});

// Then use normally
const response = await axios.get('/api/v1/intl/chat/sessions');
```

---

## Debugging Checklist

1. ✅ **Check login response:**
   ```javascript
   const data = await login(email, password);
   console.log('access_token length:', data.access_token?.length);  // Should be 300+
   ```

2. ✅ **Check token storage:**
   ```javascript
   const token = localStorage.getItem('auth_token');
   console.log('Stored token length:', token?.length);  // Should be 300+
   ```

3. ✅ **Check token in request:**
   - Open Network tab
   - Find request → Headers → Authorization
   - Should be 300+ characters

4. ✅ **Check backend logs:**
   - Look for: `"auth_header_length":10` → ❌ Token too short
   - Look for: `"auth_header_length":340` → ✅ Token correct length
   - Look for: `"has_user":true` → ✅ User authenticated
   - Look for: `"has_user":false` → ❌ Token not validated

---

## Common Mistakes

### ❌ Mistake 1: Storing Wrong Field
```javascript
// WRONG - Maybe the response has a different field name?
localStorage.setItem('auth_token', data.token);  // Wrong field
localStorage.setItem('auth_token', data.jwt);    // Wrong field

// CORRECT
localStorage.setItem('auth_token', data.access_token);  // ✅
```

### ❌ Mistake 2: Truncating Token
```javascript
// WRONG - Don't truncate!
localStorage.setItem('auth_token', data.access_token.substring(0, 10));

// CORRECT - Store full token
localStorage.setItem('auth_token', data.access_token);  // ✅
```

### ❌ Mistake 3: Not Checking Token Length
```javascript
// WRONG - Not verifying token
localStorage.setItem('auth_token', data.access_token);

// CORRECT - Verify token
if (data.access_token && data.access_token.length > 200) {
  localStorage.setItem('auth_token', data.access_token);
} else {
  console.error('Invalid token!');
}
```

---

## Expected Behavior

**When token is sent correctly:**
- Backend logs: `"auth_header_length":340,"token_length":316,"has_user":true,"user_id":5`
- Response: All user sessions (e.g., 7 sessions)
- Sessions have: `"is_guest": false`

**When token is missing or invalid:**
- Backend logs: `"auth_header_length":10,"token_length":10,"has_user":false`
- Response: Guest sessions (e.g., 4 sessions) or empty array
- Sessions have: `"is_guest": true`

---

## Quick Test

Run this in your browser console after login:

```javascript
// 1. Check token storage
const token = localStorage.getItem('auth_token');
console.log('Token length:', token?.length);
console.log('Token preview:', token?.substring(0, 50) + '...');

// 2. Test API call
fetch('https://chat.akmicroservice.com/api/v1/intl/chat/sessions', {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  }
})
.then(r => r.json())
.then(data => {
  console.log('Sessions returned:', data.length);
  console.log('First session:', data[0]);
  console.log('Is guest session?', data[0]?.is_guest);
});
```

**Expected output:**
- Token length: 316 (or 300+)
- Sessions returned: 7 (or your actual count)
- Is guest session?: false

---

**Last Updated:** November 29, 2025

