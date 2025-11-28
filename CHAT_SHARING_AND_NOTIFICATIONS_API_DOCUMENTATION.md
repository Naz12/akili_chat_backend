# Chat Session Sharing & Notifications API Documentation

## Base URL

```
https://chat.akmicroservice.com/api/v1/{region}
```

**Regions:**
- `local` - For local/Ethiopian region
- `intl` - For international region

## Authentication

All endpoints require JWT authentication. Include the token in the Authorization header:

```
Authorization: Bearer {your_jwt_token}
```

**How to get JWT token:**
1. Login via `POST /api/v1/login` with email and password
2. Response includes `token` field
3. Use this token in all subsequent requests

---

## Chat Session Sharing Endpoints

### 1. Share a Chat Session

Share one of your chat sessions with another user. The recipient will receive a notification and can accept the share to get their own copy of the session.

**Endpoint:** `POST /{region}/chat/sessions/{sessionId}/share`

**Path Parameters:**
- `sessionId` (string, required) - UUID of the chat session to share

**Request Body:**
```json
{
  "email": "user@example.com",      // Required if user_id not provided: Email of user to share with
  "user_id": 123,                    // Required if email not provided: ID of user to share with
  "message": "Check this out!"       // Optional: Personal message (max 500 chars)
}
```

**Field Details:**
- `email`: Must be a valid user email address. Required if `user_id` is not provided. Cannot be your own email (self-sharing prevented)
- `user_id`: Must be a valid user ID. Required if `email` is not provided. Cannot be your own user ID (self-sharing prevented)
- `message`: Optional personal message to include with the share notification
- **Note:** You must provide either `email` OR `user_id`, but not both

**Example Requests:**

**Using Email:**
```bash
curl -X POST "https://chat.akmicroservice.com/api/v1/local/chat/sessions/550e8400-e29b-41d4-a716-446655440000/share" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "jane@example.com",
    "message": "This conversation might help with your project!"
  }'
```

**Using User ID:**
```bash
curl -X POST "https://chat.akmicroservice.com/api/v1/local/chat/sessions/550e8400-e29b-41d4-a716-446655440000/share" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 456,
    "message": "This conversation might help with your project!"
  }'
```

**Success Response (201):**
```json
{
  "message": "Chat session shared successfully.",
  "share": {
    "id": 1,
    "shared_by_user_id": 123,
    "shared_to_user_id": 456,
    "original_chat_session_id": "550e8400-e29b-41d4-a716-446655440000",
    "duplicated_chat_session_id": null,
    "status": "pending",
    "message": "This conversation might help with your project!",
    "created_at": "2025-11-28T12:00:00.000000Z",
    "updated_at": "2025-11-28T12:00:00.000000Z",
    "shared_by": {
      "id": 123,
      "name": "John Doe",
      "email": "john@example.com"
    },
    "shared_to": {
      "id": 456,
      "name": "Jane Smith",
      "email": "jane@example.com"
    },
    "original_session": {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "title": "Project Discussion",
      "user_id": 123,
      "created_at": "2025-11-27T10:00:00.000000Z"
    }
  }
}
```

**Error Responses:**

**409 Conflict - Already Shared:**
```json
{
  "message": "This session has already been shared with this user and is pending acceptance.",
  "share": { /* existing share object */ }
}
```

**404 Not Found - Session Not Found:**
```json
{
  "message": "No query results for model [App\\Models\\ChatSession] {sessionId}"
}
```

**422 Validation Error:**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required when user id is not present."],
    "user_id": ["The user id field is required when email is not present."],
    "message": ["The message must not be greater than 500 characters."]
  }
}
```

**422 Self-Share Prevention:**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["You cannot share a session with yourself."]
  }
}
```

**404 Not Found - User Not Found:**
```json
{
  "message": "No query results for model [App\\Models\\User] {email or user_id}"
}
```

---

### 2. Accept a Shared Chat Session

Accept a shared chat session. This creates a duplicate of the session in your account with all messages and documents, allowing you to continue the conversation independently.

**Endpoint:** `POST /{region}/chat/shares/{shareId}/accept`

**Path Parameters:**
- `shareId` (integer, required) - ID of the share to accept

**Example Request:**
```bash
curl -X POST "https://chat.akmicroservice.com/api/v1/local/chat/shares/1/accept" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"
```

**Success Response (200):**
```json
{
  "message": "Chat session accepted and duplicated successfully.",
  "share": {
    "id": 1,
    "status": "accepted",
    "duplicated_chat_session_id": "660e8400-e29b-41d4-a716-446655440001",
    "duplicated_session": {
      "id": "660e8400-e29b-41d4-a716-446655440001",
      "title": "Project Discussion (Shared)",
      "user_id": 456,
      "created_at": "2025-11-28T12:05:00.000000Z"
    }
  },
  "session_id": "660e8400-e29b-41d4-a716-446655440001",
  "session": {
    "id": "660e8400-e29b-41d4-a716-446655440001",
    "title": "Project Discussion (Shared)",
    "user_id": 456,
    "created_at": "2025-11-28T12:05:00.000000Z",
    "updated_at": "2025-11-28T12:05:00.000000Z"
  }
}
```

**Error Responses:**

**404 Not Found - Share Not Found:**
```json
{
  "message": "No query results for model [App\\Models\\ChatSessionShare] {shareId}"
}
```

**403 Forbidden - Not Your Share:**
```json
{
  "message": "This action is unauthorized."
}
```

**409 Conflict - Already Accepted:**
```json
{
  "message": "This share has already been accepted.",
  "share": { /* share object with duplicated_session */ },
  "session_id": "660e8400-e29b-41d4-a716-446655440001"
}
```

**500 Server Error:**
```json
{
  "error": "Failed to accept chat session share."
}
```

**Important Notes:**
- Accepting a share creates a **complete duplicate** of the original session
- All messages are copied with their original timestamps
- All linked documents are also copied
- The new session is independent - changes don't affect the original
- The session title is automatically appended with " (Shared)"

---

### 3. Decline a Shared Chat Session

Decline a shared chat session. This marks the share as declined and removes it from your pending shares list.

**Endpoint:** `POST /{region}/chat/shares/{shareId}/decline`

**Path Parameters:**
- `shareId` (integer, required) - ID of the share to decline

**Example Request:**
```bash
curl -X POST "https://chat.akmicroservice.com/api/v1/local/chat/shares/1/decline" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"
```

**Success Response (200):**
```json
{
  "message": "Chat session share declined.",
  "share": {
    "id": 1,
    "status": "declined",
    "shared_by_user_id": 123,
    "shared_to_user_id": 456,
    "original_chat_session_id": "550e8400-e29b-41d4-a716-446655440000",
    "updated_at": "2025-11-28T12:10:00.000000Z",
    "original_session": {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "title": "Project Discussion"
    }
  }
}
```

**Error Responses:**

**404 Not Found:**
```json
{
  "message": "No query results for model [App\\Models\\ChatSessionShare] {shareId}"
}
```

**403 Forbidden:**
```json
{
  "message": "This action is unauthorized."
}
```

---

### 4. Get Incoming Shares

Get all chat sessions that have been shared with you (received shares).

**Endpoint:** `GET /{region}/chat/shares/incoming`

**Query Parameters:** None

**Example Request:**
```bash
curl -X GET "https://chat.akmicroservice.com/api/v1/local/chat/shares/incoming" \
  -H "Authorization: Bearer {token}"
```

**Success Response (200):**
```json
{
  "shares": [
    {
      "id": 1,
      "shared_by_user_id": 123,
      "shared_to_user_id": 456,
      "original_chat_session_id": "550e8400-e29b-41d4-a716-446655440000",
      "duplicated_chat_session_id": null,
      "status": "pending",
      "message": "Check this out!",
      "created_at": "2025-11-28T12:00:00.000000Z",
      "updated_at": "2025-11-28T12:00:00.000000Z",
      "shared_by": {
        "id": 123,
        "name": "John Doe",
        "email": "john@example.com"
      },
      "original_session": {
        "id": "550e8400-e29b-41d4-a716-446655440000",
        "title": "Project Discussion",
        "user_id": 123
      },
      "duplicated_session": null
    },
    {
      "id": 2,
      "status": "accepted",
      "duplicated_chat_session_id": "660e8400-e29b-41d4-a716-446655440001",
      "shared_by": {
        "id": 789,
        "name": "Alice Johnson",
        "email": "alice@example.com"
      },
      "original_session": {
        "id": "770e8400-e29b-41d4-a716-446655440002",
        "title": "Code Review"
      },
      "duplicated_session": {
        "id": "660e8400-e29b-41d4-a716-446655440001",
        "title": "Code Review (Shared)"
      }
    }
  ]
}
```

**Response Fields:**
- `shares`: Array of share objects
- Each share includes:
  - Share metadata (id, status, message, timestamps)
  - `shared_by`: User who shared the session
  - `original_session`: The original session that was shared
  - `duplicated_session`: Your copy (null if not accepted yet)

**Status Values:**
- `pending`: Share is waiting for your action
- `accepted`: You've accepted and have a copy
- `declined`: You've declined the share

---

### 5. Get Outgoing Shares

Get all chat sessions that you have shared with others (sent shares).

**Endpoint:** `GET /{region}/chat/shares/outgoing`

**Query Parameters:** None

**Example Request:**
```bash
curl -X GET "https://chat.akmicroservice.com/api/v1/local/chat/shares/outgoing" \
  -H "Authorization: Bearer {token}"
```

**Success Response (200):**
```json
{
  "shares": [
    {
      "id": 1,
      "shared_by_user_id": 123,
      "shared_to_user_id": 456,
      "original_chat_session_id": "550e8400-e29b-41d4-a716-446655440000",
      "duplicated_chat_session_id": "660e8400-e29b-41d4-a716-446655440001",
      "status": "accepted",
      "message": "Check this out!",
      "created_at": "2025-11-28T12:00:00.000000Z",
      "updated_at": "2025-11-28T12:05:00.000000Z",
      "shared_to": {
        "id": 456,
        "name": "Jane Smith",
        "email": "jane@example.com"
      },
      "original_session": {
        "id": "550e8400-e29b-41d4-a716-446655440000",
        "title": "Project Discussion",
        "user_id": 123
      },
      "duplicated_session": {
        "id": "660e8400-e29b-41d4-a716-446655440001",
        "title": "Project Discussion (Shared)",
        "user_id": 456
      }
    }
  ]
}
```

**Response Fields:**
- Similar structure to incoming shares, but includes `shared_to` instead of `shared_by`
- Shows who you shared with and whether they accepted/declined

---

### 6. Get Specific Share

Get details of a specific share (works for both incoming and outgoing shares).

**Endpoint:** `GET /{region}/chat/shares/{shareId}`

**Path Parameters:**
- `shareId` (integer, required) - ID of the share

**Example Request:**
```bash
curl -X GET "https://chat.akmicroservice.com/api/v1/local/chat/shares/1" \
  -H "Authorization: Bearer {token}"
```

**Success Response (200):**
```json
{
  "share": {
    "id": 1,
    "shared_by_user_id": 123,
    "shared_to_user_id": 456,
    "original_chat_session_id": "550e8400-e29b-41d4-a716-446655440000",
    "duplicated_chat_session_id": "660e8400-e29b-41d4-a716-446655440001",
    "status": "accepted",
    "message": "Check this out!",
    "created_at": "2025-11-28T12:00:00.000000Z",
    "updated_at": "2025-11-28T12:05:00.000000Z",
    "shared_by": {
      "id": 123,
      "name": "John Doe",
      "email": "john@example.com"
    },
    "shared_to": {
      "id": 456,
      "name": "Jane Smith",
      "email": "jane@example.com"
    },
    "original_session": {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "title": "Project Discussion",
      "user_id": 123
    },
    "duplicated_session": {
      "id": "660e8400-e29b-41d4-a716-446655440001",
      "title": "Project Discussion (Shared)",
      "user_id": 456
    }
  }
}
```

**Error Responses:**

**404 Not Found:**
```json
{
  "message": "No query results for model [App\\Models\\ChatSessionShare] {shareId}"
}
```

**403 Forbidden - Not Your Share:**
```json
{
  "message": "This action is unauthorized."
}
```

---

## Notification Endpoints

### 1. Get Notifications

Get all notifications for the authenticated user. Notifications include chat session shares, system messages, and other alerts.

**Endpoint:** `GET /{region}/notifications`

**Query Parameters:** None (returns latest 20 notifications)

**Example Request:**
```bash
curl -X GET "https://chat.akmicroservice.com/api/v1/local/notifications" \
  -H "Authorization: Bearer {token}"
```

**Success Response (200):**
```json
[
  {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "type": "App\\Notifications\\ChatSessionSharedNotification",
    "notifiable_type": "App\\Models\\User",
    "notifiable_id": 456,
    "data": {
      "title": "Chat Session Shared",
      "message": "John Doe shared \"Project Discussion\" with you",
      "type": "chat_session_shared",
      "share_id": 1,
      "session_title": "Project Discussion",
      "sharer_name": "John Doe",
      "sharer_message": "Check this out!",
      "action_url": "/chat/shares/1"
    },
    "read_at": null,
    "created_at": "2025-11-28T12:00:00.000000Z",
    "updated_at": "2025-11-28T12:00:00.000000Z"
  },
  {
    "id": "660e8400-e29b-41d4-a716-446655440001",
    "type": "App\\Notifications\\TokenQuotaWarningNotification",
    "data": {
      "title": "Token Quota Warning",
      "message": "You've used 85% of your token quota.",
      "type": "quota_warning",
      "percentage": 85
    },
    "read_at": "2025-11-28T11:00:00.000000Z",
    "created_at": "2025-11-28T10:00:00.000000Z"
  }
]
```

**Response Fields:**
- `id`: Notification UUID
- `type`: Full class name of the notification
- `data`: Notification payload (varies by notification type)
- `read_at`: Timestamp when notification was read (null if unread)
- `created_at`: When notification was created
- `updated_at`: When notification was last updated

**Notification Types:**

**Chat Session Shared:**
```json
{
  "title": "Chat Session Shared",
  "message": "{sharer_name} shared \"{session_title}\" with you",
  "type": "chat_session_shared",
  "share_id": 1,
  "share_status": "accepted",
  "original_session_id": "550e8400-e29b-41d4-a716-446655440000",
  "duplicated_session_id": "660e8400-e29b-41d4-a716-446655440001",
  "session_title": "Project Discussion",
  "sharer_name": "John Doe",
  "sharer_message": "Optional message",
  "action_url": "/chat/sessions/660e8400-e29b-41d4-a716-446655440001"
}
```

**Notification Data Fields:**
- `share_id`: ID of the share record
- `share_status`: Status of the share (`pending`, `accepted`, `declined`)
- `original_session_id`: UUID of the original session that was shared
- `duplicated_session_id`: UUID of your copy (null if not accepted yet)
- `action_url`: 
  - If `accepted`: Points to `/chat/{duplicated_session_id}` - navigate directly to your copy (frontend should construct the correct route)
  - If `pending`: Points to `/chat/shares/{share_id}` - navigate to share details to accept/decline
- **Note:** The `action_url` format may vary based on your frontend routing. You can also use `duplicated_session_id` directly to construct the URL in your frontend router.

**Token Quota Warning:**
```json
{
  "title": "Token Quota Warning",
  "message": "You've used {percentage}% of your token quota.",
  "type": "quota_warning",
  "percentage": 85
}
```

**Read/Unread Status:**
- **Unread**: `read_at` field is `null`
- **Read**: `read_at` field contains a timestamp (e.g., `"2025-11-28T13:30:00.000000Z"`)

**Usage in Frontend:**
1. Poll this endpoint periodically (e.g., every 30 seconds) or use WebSocket if available
2. Filter unread notifications: `notifications.filter(n => n.read_at === null)`
3. When user clicks/opens a notification:
   - Call `POST /notifications/{notificationId}/read` to mark it as read
   - Then navigate using `data.action_url`
4. Use `data.action_url` to navigate to relevant pages
5. Use `data.type` to determine notification icon/styling
6. Show unread badge count: `notifications.filter(n => n.read_at === null).length`

---

### 2. Mark a Single Notification as Read

Mark a specific notification as read when the user opens/clicks on it.

**Endpoint:** `POST /{region}/notifications/{notificationId}/read`

**Path Parameters:**
- `notificationId` (string, required) - UUID of the notification to mark as read

**Example Request:**
```bash
curl -X POST "https://chat.akmicroservice.com/api/v1/local/notifications/c1de8994-93f6-4cff-8f94-6117989069ca/read" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"
```

**Success Response (200):**
```json
{
  "status": "marked as read",
  "notification": {
    "id": "c1de8994-93f6-4cff-8f94-6117989069ca",
    "read_at": "2025-11-28T13:30:00.000000Z",
    "data": { /* notification data */ }
  }
}
```

**Error Responses:**

**404 Not Found:**
```json
{
  "message": "No query results for model [Illuminate\\Notifications\\DatabaseNotification] {notificationId}"
}
```

**403 Forbidden:**
```json
{
  "message": "This action is unauthorized."
}
```

**Notes:**
- Call this endpoint when a user clicks/opens a notification
- If the notification is already read, it will still return success
- The `read_at` field will be set to the current timestamp

---

### 3. Mark All Notifications as Read

Mark all unread notifications as read for the authenticated user.

**Endpoint:** `POST /{region}/notifications/read`

**Example Request:**
```bash
curl -X POST "https://chat.akmicroservice.com/api/v1/local/notifications/read" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"
```

**Success Response (200):**
```json
{
  "status": "all marked as read"
}
```

**Notes:**
- This marks ALL unread notifications as read
- Use this for "Mark all as read" button in UI
- Individual notifications should be marked as read when opened (use endpoint #2)

---

## Frontend Integration Guide

### Chat Session Sharing Flow

**1. Sharing a Session:**
```javascript
// Share using email (recommended)
async function shareSessionByEmail(sessionId, targetEmail, message = '') {
  const response = await fetch(
    `/api/v1/local/chat/sessions/${sessionId}/share`,
    {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        email: targetEmail,
        message: message
      })
    }
  );
  
  if (response.ok) {
    const data = await response.json();
    showSuccess('Session shared successfully!');
    return data.share;
  } else {
    const error = await response.json();
    showError(error.message);
  }
}

// Share using user ID (alternative)
async function shareSessionByUserId(sessionId, targetUserId, message = '') {
  const response = await fetch(
    `/api/v1/local/chat/sessions/${sessionId}/share`,
    {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        user_id: targetUserId,
        message: message
      })
    }
  );
  
  if (response.ok) {
    const data = await response.json();
    showSuccess('Session shared successfully!');
    return data.share;
  } else {
    const error = await response.json();
    showError(error.message);
  }
}
```

**2. Displaying Incoming Shares:**
```javascript
// Fetch incoming shares
async function getIncomingShares() {
  const response = await fetch('/api/v1/local/chat/shares/incoming', {
    headers: {
      'Authorization': `Bearer ${token}`
    }
  });
  
  const data = await response.json();
  return data.shares.filter(share => share.status === 'pending');
}

// Display in UI
const pendingShares = await getIncomingShares();
pendingShares.forEach(share => {
  // Show notification card with:
  // - share.shared_by.name
  // - share.original_session.title
  // - share.message
  // - Accept/Decline buttons
});
```

**3. Accepting a Share:**
```javascript
async function acceptShare(shareId) {
  const response = await fetch(
    `/api/v1/local/chat/shares/${shareId}/accept`,
    {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      }
    }
  );
  
  if (response.ok) {
    const data = await response.json();
    // Navigate to the new session
    navigateToChatSession(data.session_id);
    showSuccess('Session accepted!');
  }
}
```

**4. Handling Notifications:**
```javascript
// Poll for notifications
async function fetchNotifications() {
  const response = await fetch('/api/v1/local/notifications', {
    headers: {
      'Authorization': `Bearer ${token}`
    }
  });
  
  const notifications = await response.json();
  const unread = notifications.filter(n => n.read_at === null);
  
  // Update notification badge count
  updateBadgeCount(unread.length);
  
  // Display notifications
  displayNotifications(notifications);
}

// Mark notification as read
async function markNotificationAsRead(notificationId) {
  await fetch(`/api/v1/local/notifications/${notificationId}/read`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    }
  });
}

// Handle notification click
async function handleNotificationClick(notification) {
  // Mark as read when opened
  if (notification.read_at === null) {
    await markNotificationAsRead(notification.id);
    // Update local state
    notification.read_at = new Date().toISOString();
    updateBadgeCount();
  }
  
  // Navigate based on notification type
  if (notification.data.type === 'chat_session_shared') {
    // Use action_url which points to the correct session
    navigateTo(notification.data.action_url);
  } else if (notification.data.type === 'quota_warning') {
    // Navigate to subscription/plans page
    navigateTo('/plans');
  }
}
```

### UI/UX Recommendations

**Share Button:**
- Add "Share" button to chat session list/item
- Open modal with:
  - User search/select dropdown
  - Optional message textarea
  - Share button

**Incoming Shares:**
- Show in notifications dropdown
- Display as cards with:
  - Sharer name and avatar
  - Session title
  - Optional message
  - Accept/Decline buttons
- Show in dedicated "Shared with Me" section

**Notification Badge:**
- Show unread count in header
- Update every 30 seconds or on user action
- Clear badge when all notifications read

**Accept Flow:**
- Show loading state during acceptance
- Navigate directly to duplicated session after accept
- Show success toast/notification

---

## Error Handling

### Common Error Codes

**401 Unauthorized:**
- Token missing or invalid
- Solution: Redirect to login

**403 Forbidden:**
- User trying to access share they don't own
- Solution: Show error message

**404 Not Found:**
- Share or session doesn't exist
- Solution: Show "Not found" message, refresh list

**409 Conflict:**
- Duplicate share attempt
- Already accepted share
- Solution: Show appropriate message, update UI

**422 Validation Error:**
- Invalid input data
- Solution: Display field-specific errors

**500 Server Error:**
- Internal server error
- Solution: Log error, show generic error message, retry option

---

## Rate Limiting

All chat endpoints are rate-limited to **60 requests per minute** per user.

If rate limit is exceeded, you'll receive:
```json
{
  "message": "Too Many Attempts."
}
```

**Status Code:** 429 Too Many Requests

**Solution:** Implement exponential backoff in frontend retry logic.

---

## Best Practices

1. **Polling Notifications:**
   - Poll every 30-60 seconds when app is active
   - Stop polling when app is in background
   - Use WebSocket if available for real-time updates

2. **Share Management:**
   - Refresh shares list after accept/decline
   - Show loading states during async operations
   - Handle network errors gracefully

3. **Session Navigation:**
   - After accepting share, navigate directly to new session
   - Show clear indication that session is shared/copied
   - Allow user to rename shared sessions

4. **User Experience:**
   - Show toast notifications for share actions
   - Update notification badge in real-time
   - Provide clear feedback for all actions

---

## Testing

### Test Scenarios

1. **Share Session:**
   - Share with valid user
   - Attempt self-share (should fail)
   - Share same session twice (should show conflict)

2. **Accept Share:**
   - Accept pending share
   - Verify session duplication
   - Verify messages and docs copied

3. **Decline Share:**
   - Decline pending share
   - Verify status updated

4. **Notifications:**
   - Verify notification created on share
   - Verify notification read status
   - Test mark all as read

---

## Support

For issues or questions:
- Check error responses for detailed messages
- Verify authentication token is valid
- Ensure region matches user's region
- Check rate limiting hasn't been exceeded

---

**Last Updated:** November 28, 2025
**API Version:** v1

