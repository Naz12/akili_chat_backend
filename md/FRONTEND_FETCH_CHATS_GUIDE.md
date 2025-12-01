# Frontend Guide: How to Fetch Chats for Logged-In Users

## 🚨 CRITICAL ISSUE

**If you're getting guest sessions instead of user sessions after login, the JWT token is not being sent correctly in the request.**

### ⚠️ Common Problem: Token Too Short

**Backend logs show:** `"auth_header_length":10,"token_length":10`

**This means:** The frontend is sending a token that's only 10 characters long, which is invalid!

**A valid JWT token should be:**
- **300+ characters long**
- Format: `eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwczovL2NoYXQuYWttaWNyb3NlcnZpY2UuY29tIiwiaWF0IjoxNzY0NDE3NTAyLCJleHAiOjE3NjcwMDk1MDIsIm5iZiI6MTc2NDQxNzUwMiwianRpIjoiUjVJZ3oxVFVENzZSMlh3SCIsInN1YiI6IjUiLCJwcnYiOiIyM2JkNWM4OTQ5ZjYwMGFkYjM5ZTcwMWM0MDA4NzJkYjdhNTk3NmY3In0.yny3GgieNa6mE-rgVsZ5aZcbVDdgvMzuSq5QxVzvO1M`

**Check your code:**
```javascript
// ❌ WRONG - Token is being truncated or not stored correctly
const token = localStorage.getItem('auth_token');
console.log('Token length:', token?.length);  // Should be 300+, not 10!

// ✅ CORRECT - Full token should be stored
const response = await login(email, password);
if (response.access_token && response.access_token.length > 200) {
  localStorage.setItem('auth_token', response.access_token);
  console.log('Token stored, length:', response.access_token.length);
}
```

---

## ✅ Correct Implementation

### Step 1: Store Token After Login

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
    localStorage.setItem('user', JSON.stringify(data.user));
  }
  
  return data;
}
```

### Step 2: Fetch Chat Sessions with Token

```javascript
async function fetchChatSessions(region = 'intl') {
  const token = localStorage.getItem('auth_token');
  
  if (!token) {
    console.error('No auth token found!');
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
  
  return await response.json();
}
```

### Step 3: Using Axios (Recommended)

```javascript
import axios from 'axios';

// Set up interceptor to automatically include token
axios.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Then simply call the endpoint
async function fetchChatSessions(region = 'intl') {
  const response = await axios.get(
    `https://chat.akmicroservice.com/api/v1/${region}/chat/sessions?with_messages=true`
  );
  return response.data;
}
```

---

## 🔍 How to Verify

### 1. Check Browser DevTools → Network Tab

- Find the `/chat/sessions` request
- Click on it
- Go to "Headers" tab
- Look for "Request Headers"
- You should see: `Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...`

### 2. Check the Response

- ✅ **If token is sent:** You'll get all user sessions (e.g., 7 sessions)
- ❌ **If token is missing:** You'll get guest sessions (e.g., 4 sessions) or empty array

### 3. Check Console Logs

```javascript
// Verify token is stored
console.log('Token:', localStorage.getItem('auth_token'));

// Verify token is being sent
// Check Network tab → Request Headers → Authorization
```

---

## ❌ Common Mistakes

### Mistake 1: Not Storing Token After Login
```javascript
// ❌ WRONG
const response = await login(email, password);
// Token is lost!

// ✅ CORRECT
const { access_token } = await login(email, password);
localStorage.setItem('auth_token', access_token);
```

### Mistake 2: Not Sending Token in Request
```javascript
// ❌ WRONG
fetch('/api/v1/intl/chat/sessions');  // Missing Authorization header

// ✅ CORRECT
fetch('/api/v1/intl/chat/sessions', {
  headers: {
    'Authorization': `Bearer ${token}`  // ✅
  }
});
```

### Mistake 3: Wrong Token Format
```javascript
// ❌ WRONG
headers: { 'Authorization': token }  // Missing "Bearer "

// ✅ CORRECT
headers: { 'Authorization': `Bearer ${token}` }
```

---

## 🐛 Troubleshooting

### Problem: Getting Guest Sessions Instead of User Sessions

**Checklist:**
1. ✅ **Is token stored after login?** 
   ```javascript
   const token = localStorage.getItem('auth_token');
   console.log('Token:', token);
   console.log('Token length:', token?.length);  // ⚠️ Should be 300+, not 10!
   ```

2. ✅ **Is token the full length?**
   - Valid JWT tokens are **300+ characters**
   - If your token is only 10-50 characters, it's wrong!
   - Check the login response: `response.access_token` should be the full token

3. ✅ **Is token sent in request?** 
   - Check Network tab → Request Headers → Authorization
   - Should see: `Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...` (300+ chars)

4. ✅ **Is token format correct?** 
   - Should be `Bearer {token}`, not just `{token}`
   - Should NOT be `Bearer Bearer {token}`

5. ✅ **Is token expired?** 
   - Tokens expire after 30 days
   - Try logging in again to get a fresh token

6. ✅ **Debug the actual token being sent:**
   ```javascript
   // Add this before making the request
   const token = localStorage.getItem('auth_token');
   console.log('Token being sent:', token);
   console.log('Token length:', token?.length);
   console.log('Token preview:', token?.substring(0, 50) + '...');
   
   // Should see something like:
   // Token preview: eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJod...
   ```

### Problem: Empty Sessions Array

**Possible Causes:**
- Token is invalid or expired
- User has no sessions
- Token is not being validated by backend

**Solution:**
- Check backend logs for authentication errors
- Verify token is valid (not expired)
- Try refreshing the token

---

## 📝 Complete Example

```javascript
// 1. Login and store token
async function login(email, password) {
  const response = await fetch('https://chat.akmicroservice.com/api/v1/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password })
  });
  
  const data = await response.json();
  
  if (data.access_token) {
    localStorage.setItem('auth_token', data.access_token);
    localStorage.setItem('user', JSON.stringify(data.user));
  }
  
  return data;
}

// 2. Fetch chat sessions
async function fetchChatSessions() {
  const token = localStorage.getItem('auth_token');
  const user = JSON.parse(localStorage.getItem('user'));
  
  if (!token || !user) {
    return [];
  }
  
  const response = await fetch(
    `https://chat.akmicroservice.com/api/v1/${user.region}/chat/sessions?with_messages=true`,
    {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      }
    }
  );
  
  return await response.json();
}

// 3. Use in component
useEffect(() => {
  async function loadSessions() {
    const sessions = await fetchChatSessions();
    setChatSessions(sessions);
  }
  
  if (isAuthenticated) {
    loadSessions();
  }
}, [isAuthenticated]);
```

---

## 📚 Additional Resources

- Full guide: `GUEST_USER_AUTHENTICATION_GUIDE.md`
- API endpoints: `API_ENDPOINTS_LIST.md`
- API documentation: `CLIENT_API_DOCUMENTATION.md`

---

**Last Updated:** November 29, 2025

