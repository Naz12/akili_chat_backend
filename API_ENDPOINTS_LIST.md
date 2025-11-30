# Complete Client API Endpoints List

**Base URL:** `https://chat.akmicroservice.com/api/v1`

**Regions:** `local` or `intl` (replace `{region}` in URLs below)

**Authentication:** 
- 🔐 **Requires Auth**: Endpoints marked with 🔐 require `Authorization: Bearer {token}` header
- 👤 **Guest Accessible**: Endpoints marked with 👤 work for both authenticated users and guest users
- 🌐 **Public**: Endpoints marked with 🌐 require no authentication

---

## 🔐 Authentication Endpoints (No Region Prefix, No Auth Required)

These endpoints are available at `/api/v1/` directly (no region prefix):

- 🌐 `POST /api/v1/register` - Register new user
- 🌐 `POST /api/v1/login` - Login user
- 🌐 `POST /api/v1/refresh-token` - Refresh JWT token
- 🌐 `POST /api/v1/login/google` - Google OAuth login
- 🌐 `POST /api/v1/send-reset-code` - Send password reset code (throttled: 3/min)
- 🌐 `POST /api/v1/reset-password-code` - Reset password with code

---

## 🌍 Region-Specific Endpoints

All endpoints below are available at `/api/v1/{region}/` where `{region}` is either `local` or `intl`.

### 📱 App Version

- 🌐 `GET /api/v1/{region}/check-version` - Check app version

---

## 💬 Chat & Sessions

### 👤 Guest Accessible Endpoints

These endpoints work for both authenticated users and guest users. Guest users are identified by device fingerprint (optional `X-Guest-UUID` header or cookie).

- 👤 `POST /api/v1/{region}/chat` - Send chat message (requires quota check)
- 👤 `POST /api/v1/{region}/chat/upload` - Upload file attachment (requires quota check)
- 👤 `GET /api/v1/{region}/chat/sessions` - Get chat sessions
- 👤 `GET /api/v1/{region}/chat/messages/{sessionId}` - Get messages for a session
- 👤 `PUT /api/v1/{region}/chat/sessions/{sessionId}` - Rename a session
- 👤 `DELETE /api/v1/{region}/chat/sessions/{sessionId}` - Delete a session

**Note:** 
- Authenticated users can access their own sessions and guest sessions (pre-login sessions)
- Guest users can only access sessions created with their device fingerprint
- Guest sessions expire after 24 hours

### 🔐 Chat Session Sharing (Authenticated Only)

- 🔐 `POST /api/v1/{region}/chat/sessions/{sessionId}/share` - Share a chat session
- 🔐 `GET /api/v1/{region}/chat/shares/incoming` - Get incoming shares
- 🔐 `GET /api/v1/{region}/chat/shares/outgoing` - Get outgoing shares
- 🔐 `GET /api/v1/{region}/chat/shares/{shareId}` - Get share details
- 🔐 `POST /api/v1/{region}/chat/shares/{shareId}/accept` - Accept a share
- 🔐 `POST /api/v1/{region}/chat/shares/{shareId}/decline` - Decline a share

---

## 👤 User Profile (Authenticated Only)

- 🔐 `GET /api/v1/{region}/user` - Get user profile
- 🔐 `POST /api/v1/{region}/logout` - Logout user
- 🔐 `POST /api/v1/{region}/fcm-token` - Store FCM token for push notifications

---

## 🗂️ Plans & Subscriptions (Authenticated Only)

- 🔐 `GET /api/v1/{region}/plans` - Get all plans
- 🔐 `POST /api/v1/{region}/subscribe` - Subscribe to a plan
- 🔐 `GET /api/v1/{region}/subscription` - Get current subscription
- 🔐 `GET /api/v1/{region}/user/has-subscription` - Check if user has subscription
- 🔐 `GET /api/v1/{region}/subscriptions` - Get all user subscriptions
- 🔐 `GET /api/v1/{region}/subscriptions/{id}` - Get details of a specific subscription
- 🔐 `PUT /api/v1/{region}/subscriptions/{id}` - Update a subscription (e.g., auto-renew)
- 🔐 `DELETE /api/v1/{region}/subscriptions/{id}` - Cancel a subscription
- 🔐 `GET /api/v1/{region}/subscriptions/renewal-status` - Get renewal status of active subscription
- 🔐 `POST /api/v1/{region}/subscriptions/{id}/renew` - Manually renew a subscription
- 🔐 `GET /api/v1/{region}/subscriptions/upgrade-options` - Get available upgrade plans
- 🔐 `GET /api/v1/{region}/subscriptions/downgrade-options` - Get available downgrade plans
- 🔐 `GET /api/v1/{region}/subscriptions/payment-failure-history` - Get history of failed subscription payments

---

## 📊 Token Usage (Authenticated Only)

- 🔐 `GET /api/v1/{region}/token-usage` - Get usage logs (paginated)
- 🔐 `GET /api/v1/{region}/token-usage/stats` - Get usage statistics
- 🔐 `GET /api/v1/{region}/token-usage/daily` - Get daily usage breakdown
- 🔐 `GET /api/v1/{region}/token-usage/weekly` - Get weekly usage aggregation
- 🔐 `GET /api/v1/{region}/token-usage/monthly` - Get monthly usage aggregation
- 🔐 `GET /api/v1/{region}/token-usage/analytics` - Get usage analytics
- 🔐 `GET /api/v1/{region}/token-usage/quota-status` - Get quota status
- 🔐 `GET /api/v1/{region}/token-usage/export` - Export usage data (CSV/JSON)

---

## 🔔 Notifications (Authenticated Only)

- 🔐 `GET /api/v1/{region}/notifications` - Get notifications
- 🔐 `POST /api/v1/{region}/notifications/{notificationId}/read` - Mark notification as read
- 🔐 `POST /api/v1/{region}/notifications/read` - Mark all notifications as read

---

## 📄 Billing (Authenticated Only)

- 🔐 `GET /api/v1/{region}/billing/current` - Get current billing info
- 🔐 `GET /api/v1/{region}/billing/history` - Get billing history
- 🔐 `GET /api/v1/{region}/billing/usage` - Get usage summary
- 🔐 `POST /api/v1/{region}/billing/toggle-renew` - Toggle auto-renew
- 🔐 `GET /api/v1/{region}/billing/invoices` - Get invoices list
- 🔐 `GET /api/v1/{region}/billing/invoices/{id}` - Get invoice details
- 🔐 `GET /api/v1/{region}/billing/upcoming-charges` - Get upcoming charges
- 🔐 `GET /api/v1/{region}/billing/payment-methods` - Get payment methods
- 🔐 `POST /api/v1/{region}/billing/payment-methods` - Add payment method
- 🔐 `DELETE /api/v1/{region}/billing/payment-methods/{id}` - Remove payment method
- 🔐 `PUT /api/v1/{region}/billing/payment-methods/{id}/default` - Set default payment method
- 🔐 `GET /api/v1/{region}/billing/address` - Get billing address
- 🔐 `PUT /api/v1/{region}/billing/address` - Update billing address
- 🔐 `GET /api/v1/{region}/billing/tax-information` - Get tax information
- 🔐 `PUT /api/v1/{region}/billing/tax-information` - Update tax information

---

## 💰 Payments (Authenticated Only)

- 🔐 `POST /api/v1/{region}/payments/create` - Create payment
- 🔐 `GET /api/v1/{region}/payments/status` - Get payment status
- 🔐 `POST /api/v1/{region}/payments/verify` - Verify payment
- 🔐 `GET /api/v1/{region}/payments/history` - Get payment history
- 🔐 `GET /api/v1/{region}/payments/{id}` - Get payment details
- 🔐 `POST /api/v1/{region}/payments/{id}/retry` - Retry failed payment
- 🔐 `POST /api/v1/{region}/payments/{id}/cancel` - Cancel payment
- 🔐 `POST /api/v1/{region}/payments/{id}/refund` - Request refund
- 🔐 `GET /api/v1/{region}/payments/{id}/refund-status` - Get refund status
- 🔐 `GET /api/v1/{region}/payments/{id}/receipt` - Download receipt (PDF)
- 🔐 `GET /api/v1/{region}/payments/summary` - Get payment summary
- 🔐 `GET /api/v1/{region}/payments/upcoming` - Get upcoming payments

---

## 🧠 Workflow Insights (Authenticated Only)

- 🔐 `GET /api/v1/{region}/insights/summary` - Get insights summary
- 🔐 `GET /api/v1/{region}/insights/recommendations` - Get recommendations

---

## 🔄 Regional Subscription (Authenticated Only)

- 🔐 `POST /api/v1/{region}/regional-subscribe` - Regional subscription
- 🔐 `POST /api/v1/{region}/payment/telebirr/callback` - Telebirr payment callback

---

## 🔗 Webhooks (Public, No Auth Required)

These are webhook endpoints for payment providers. They verify signatures but don't require authentication:

- 🌐 `POST /api/v1/payments/webhook/stripe` - Stripe webhook
- 🌐 `GET|POST /api/v1/payments/webhook/chapa` - Chapa webhook
- 🌐 `GET /api/v1/payments/webhook/chapa/redirect` - Chapa redirect handler

---

## 📝 Authentication & Guest User Notes

### Authentication

1. **JWT Token**: Endpoints marked with 🔐 require `Authorization: Bearer {token}` header
2. **Token Format**: The token is obtained from `/api/v1/login` or `/api/v1/register`
3. **Token Refresh**: Use `/api/v1/refresh-token` to refresh expired tokens
4. **⚠️ CRITICAL - Token Must Be Sent After Login**: 
   - After successful login, the frontend **MUST** store the JWT token from the response
   - The token **MUST** be sent in the `Authorization: Bearer {token}` header for ALL authenticated requests
   - If the token is not sent, the backend will treat the user as a guest and return guest sessions instead of user sessions
   - Example: `Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...`

### Guest Users

1. **Identification**: Guest users are identified by device fingerprint (generated from IP, User-Agent, Accept-Language, and optional `X-Guest-UUID` header)
2. **Session Lifetime**: Guest sessions expire after 24 hours
3. **Access**: Guest users can only access their own sessions (created with their device fingerprint)
4. **Migration**: When a guest user registers/logs in, their guest sessions are automatically migrated to their user account
5. **Limits**: Guest users get the same free plan limits as authenticated users but with time-based restrictions

### ⚠️ Important: Authenticated vs Guest Behavior

**For Chat Sessions Endpoint (`GET /api/v1/{region}/chat/sessions`):**

- **If JWT token is sent** (authenticated user):
  - Returns all sessions where `user_id` matches the authenticated user
  - Includes migrated guest sessions (now with `user_id` set)
  - Returns up to 10 most recent sessions
  - Example: User with 7 sessions will see all 7 sessions

- **If JWT token is NOT sent** (treated as guest):
  - Returns sessions where `guest_session_id` matches the device fingerprint
  - Only returns guest sessions (no `user_id` set)
  - Returns up to 10 most recent guest sessions
  - Example: May show 2 guest sessions instead of 7 user sessions

**Frontend Implementation Requirement:**
- After login, store the JWT token from the response
- Include the token in ALL subsequent API requests: `Authorization: Bearer {token}`
- Without the token, users will see guest sessions instead of their actual user sessions

### Guest User Headers

- `X-Guest-UUID` (optional): Client-provided UUID for consistent guest identification across devices
- `X-Client-UUID` (optional): Alternative header name for guest UUID

---

## 📋 Quick Reference by Authentication Type

### 🌐 Public (No Authentication Required)
- Register, Login, Password Reset (`/api/v1/register`, `/api/v1/login`, etc.)
- Check Version (`/api/v1/{region}/check-version`)
- Webhooks (`/api/v1/payments/webhook/*`)

### 👤 Guest Accessible (No Auth Required, but Guest Session Needed)
- Chat endpoints:
  - `POST /api/v1/{region}/chat`
  - `POST /api/v1/{region}/chat/upload`
  - `GET /api/v1/{region}/chat/sessions`
  - `GET /api/v1/{region}/chat/messages/{sessionId}`
  - `PUT /api/v1/{region}/chat/sessions/{sessionId}`
  - `DELETE /api/v1/{region}/chat/sessions/{sessionId}`

### 🔐 Authenticated Only (Requires JWT Token)
- User Profile (`/api/v1/{region}/user`, `/api/v1/{region}/logout`, etc.)
- Plans & Subscriptions (all `/api/v1/{region}/plans/*` and `/api/v1/{region}/subscriptions/*`)
- Token Usage (all `/api/v1/{region}/token-usage/*`)
- Notifications (all `/api/v1/{region}/notifications/*`)
- Billing (all `/api/v1/{region}/billing/*`)
- Payments (all `/api/v1/{region}/payments/*`)
- Chat Session Sharing (all `/api/v1/{region}/chat/shares/*`)
- Workflow Insights (all `/api/v1/{region}/insights/*`)
- Regional Subscription (`/api/v1/{region}/regional-subscribe`)

---

## ⚙️ Technical Details

### Rate Limiting
- Chat endpoints: 60 requests/minute
- Password reset: 3 requests/minute
- Other endpoints: Default Laravel throttling

### CORS
- All endpoints support CORS
- Allowed headers: `Content-Type`, `Authorization`, `X-Requested-With`, `Accept`, `Origin`, `X-Guest-UUID`
- Allowed methods: `GET`, `POST`, `PUT`, `DELETE`, `OPTIONS`

### Error Responses
- `401 Unauthorized`: Missing or invalid authentication token (for 🔐 endpoints) or no guest session (for 👤 endpoints)
- `404 Not Found`: Resource not found or access denied
- `422 Unprocessable Entity`: Validation errors
- `429 Too Many Requests`: Rate limit exceeded
- `500 Internal Server Error`: Server error

### Region Matching
- Most authenticated endpoints require the user's region to match the endpoint region
- Users are assigned a region during registration
- Region mismatch returns appropriate error

---

## ⚠️ Important Notes

1. **Login Endpoint**: The login endpoint is at `/api/v1/login` (no region prefix)
2. **Region Prefix**: All other endpoints use `/api/v1/{region}/` prefix where `{region}` is `local` or `intl`
3. **Guest Sessions**: Guest users can access chat endpoints without authentication, but sessions are tied to device fingerprint
4. **Session Migration**: When a guest user logs in, their guest sessions are automatically transferred to their user account
5. **Mixed Access**: Authenticated users can access both their user sessions and guest sessions (pre-login sessions)
6. **JWT Token Storage**: Frontend MUST store and send JWT token after login - without it, users will see guest sessions instead of their user sessions

## 🔧 Frontend Implementation Checklist

### After Login/Registration:
- [ ] Store JWT token from response (usually in `token` field)
- [ ] Save token to localStorage/sessionStorage or secure storage
- [ ] Include token in `Authorization: Bearer {token}` header for all authenticated requests
- [ ] Clear token on logout

### For Chat Sessions:
- [ ] Always send JWT token if user is logged in
- [ ] Handle both authenticated and guest scenarios
- [ ] Show appropriate sessions based on authentication status

### Example Request Headers:
```javascript
// Authenticated request
headers: {
  'Authorization': 'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...',
  'Content-Type': 'application/json',
  'X-Guest-UUID': 'optional-for-guest-persistence'
}

// Guest request (no Authorization header)
headers: {
  'Content-Type': 'application/json',
  'X-Guest-UUID': 'optional-uuid-for-consistency'
}
```
