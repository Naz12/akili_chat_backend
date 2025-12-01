# Client API Documentation

This document provides comprehensive API documentation for all client-facing endpoints used by the frontend application.

**Base URL:** `https://chat.akmicroservice.com/api/v1/{region}`

**Regions:** `local` or `intl`

**Authentication:** All endpoints (except public ones) require a Bearer token in the Authorization header:
```
Authorization: Bearer {your_jwt_token}
```

**Guest Users:** Some endpoints are accessible without authentication. Guest users are identified by device fingerprinting and have the same free plan limits with time-based restrictions (24-hour expiration).

---

## Table of Contents

1. [Guest Users](#guest-users)
2. [Plans](#plans)
3. [Subscriptions](#subscriptions)
4. [Token Usage](#token-usage)
5. [Billing](#billing)
6. [Payments](#payments)
7. [Notifications](#notifications)
8. [Chat & Sessions](#chat--sessions)
9. [User Profile](#user-profile)

---

## Guest Users

### Overview

The API supports **guest users** who can use the chat functionality without creating an account. Guest users are automatically identified using device fingerprinting and have access to the same free plan features as registered users, with a 24-hour session expiration.

### Key Features

- **No Authentication Required**: Guest users can chat without signing up
- **Device Fingerprinting**: Automatic identification using browser/device characteristics
- **Free Plan Access**: Same limits as the default free plan
- **Session Expiration**: Guest sessions expire after 24 hours
- **Automatic Migration**: All guest sessions and chat history are automatically transferred to the user's account when they sign up

### Device Fingerprinting

Guest users are identified using a combination of:
- **User Agent** (browser and device information)
- **IP Address**
- **Accept-Language** header
- **Client UUID** (optional, can be provided via cookie or header for better persistence)

The system generates a SHA-256 hash of these values to create a unique device fingerprint.

### Guest Session Management

#### How It Works

1. **First Visit**: When a guest user first accesses the API, a guest session is automatically created
2. **Session Tracking**: All chat sessions and messages are associated with the guest session
3. **Usage Limits**: Guest users get the same free plan limits (e.g., 10,000 tokens, 10 daily messages)
4. **Expiration**: Guest sessions expire after 24 hours of inactivity
5. **Migration**: When a guest user signs up, all their sessions and messages are automatically transferred to their new account

#### Client Implementation

**Option 1: Automatic Fingerprinting (Recommended)**
The backend automatically generates a fingerprint from request headers. No client-side action needed.

**Option 2: Client UUID (Better Persistence)**
For better session persistence across browser restarts, you can provide a UUID:

**Via Cookie:**
```javascript
// Set a cookie on first visit
document.cookie = "guest_uuid=your-unique-uuid-here; path=/; max-age=31536000"; // 1 year
```

**Via Header:**
```javascript
// Include in API requests
headers: {
  'X-Guest-UUID': 'your-unique-uuid-here'
}
```

**Via Request Body:**
```json
{
  "guest_uuid": "your-unique-uuid-here",
  "message": "Hello"
}
```

### Guest-Accessible Endpoints

The following endpoints are accessible to guest users **without authentication**:

#### Chat Endpoints

- `POST /{region}/chat` - Send chat messages
- `POST /{region}/chat/upload` - Upload file attachments
- `GET /{region}/chat/sessions` - Get chat sessions (guest sessions only)
- `GET /{region}/chat/messages/{sessionId}` - Get messages for a session
- `PUT /{region}/chat/sessions/{sessionId}` - Rename a session
- `DELETE /{region}/chat/sessions/{sessionId}` - Delete a session

#### Public Endpoints

- `GET /{region}/check-version` - Check app version
- `POST /register` - Register new user (triggers guest session migration)
- `POST /login` - Login (triggers guest session migration)

### Guest User Limits

Guest users receive the same limits as the default free plan:

- **Token Limit**: 10,000 tokens (or as configured for default plan)
- **Daily Message Limit**: 10 messages per day (or as configured)
- **Session Expiration**: 24 hours from last activity
- **No Subscription Features**: Cannot upgrade, no payment methods, no billing history

### Guest Session Expiration

Guest sessions expire after **24 hours** of inactivity. When a session expires:

- The guest session is marked as expired
- New chat requests will create a new guest session
- Previous sessions and messages remain but are not accessible until the user signs up
- Upon signup, all expired and active guest sessions are migrated to the user account

### Automatic Session Migration

When a guest user **registers** or **logs in**, the system automatically:

1. **Identifies Guest Sessions**: Finds all guest sessions associated with the device fingerprint
2. **Transfers Chat Sessions**: Moves all chat sessions from guest to user account
3. **Transfers Messages**: Moves all chat messages to the user account
4. **Transfers Token Usage**: Optionally transfers usage metadata
5. **Cleans Up**: Removes the guest session record

**Migration happens automatically** - no API call needed. The migration occurs during the registration/login process.

### Example: Guest User Flow

#### 1. Guest User Sends First Message

```http
POST /v1/local/chat
Content-Type: application/json

{
  "message": "Hello, how can I help?",
  "session_id": null
}
```

**Response:**
```json
{
  "reply": "Hello! I'm here to help...",
  "session_id": "abc-123-def-456",
  "usage": {
    "total_tokens": 25
  }
}
```

The backend automatically:
- Creates a guest session (if not exists)
- Associates the chat session with the guest session
- Tracks token usage in the guest session

#### 2. Guest User Continues Chatting

```http
POST /v1/local/chat
Content-Type: application/json

{
  "message": "Tell me more",
  "session_id": "abc-123-def-456"
}
```

The backend uses the existing guest session.

#### 3. Guest User Signs Up

```http
POST /register
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "securepassword",
  "region": "local"
}
```

**Response:**
```json
{
  "message": "User registered successfully",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com"
  },
  "token": "jwt-token-here",
  "migrated_sessions": 3
}
```

The backend automatically:
- Creates the user account
- Migrates all guest sessions to the user account
- Returns the number of migrated sessions

#### 4. User Now Has Access to All Sessions

```http
GET /v1/local/chat/sessions
Authorization: Bearer jwt-token-here
```

**Response:**
```json
[
  {
    "id": "abc-123-def-456",
    "title": "New Chat",
    "user_id": 1,
    "is_guest": false,
    "created_at": "2025-11-28T00:00:00.000000Z"
  },
  {
    "id": "xyz-789-ghi-012",
    "title": "Another Chat",
    "user_id": 1,
    "is_guest": false,
    "created_at": "2025-11-27T12:00:00.000000Z"
  }
]
```

All previous guest sessions are now accessible as authenticated user sessions.

### Error Handling for Guests

#### Expired Session

If a guest session has expired:

```json
{
  "error": "Guest session expired. Please sign up to continue."
}
```

**Status Code:** `401 Unauthorized`

**Solution:** Prompt the user to sign up or create a new guest session.

#### Quota Exceeded

If a guest user exceeds their limits:

```json
{
  "error": true,
  "message": "Hi there, you've reached your token quota (10000/10000). Please upgrade your plan to continue using AI. 😊"
}
```

**Status Code:** `403 Forbidden`

**Solution:** Prompt the user to sign up for a paid plan.

### Best Practices for Frontend

1. **Store Guest UUID**: Generate and store a UUID in localStorage/cookie for better session persistence
2. **Handle Expiration**: Check for 401 errors and prompt users to sign up
3. **Show Migration Message**: After signup, inform users that their chat history has been saved
4. **Track Usage**: Display remaining quota to guest users (same as free plan)
5. **Encourage Signup**: Show benefits of signing up (no expiration, upgrade options, etc.)

### Guest User vs Authenticated User

| Feature | Guest User | Authenticated User |
|---------|-----------|-------------------|
| Chat Access | ✅ Yes | ✅ Yes |
| Free Plan Limits | ✅ Yes | ✅ Yes |
| Session Expiration | ⏰ 24 hours | ❌ No expiration |
| Upgrade Plans | ❌ No | ✅ Yes |
| Payment Methods | ❌ No | ✅ Yes |
| Billing History | ❌ No | ✅ Yes |
| Notifications | ❌ No | ✅ Yes |
| Share Sessions | ❌ No | ✅ Yes |
| Multiple Devices | ⚠️ Limited | ✅ Yes |

---

## Plans

### Get All Plans

Retrieve all available plans for the user's region.

**Endpoint:** `GET /{region}/plans`

**Query Parameters:**
- `price_min` (optional): Minimum price filter
- `price_max` (optional): Maximum price filter
- `features` (optional): Comma-separated feature names

**Response:**
```json
{
  "region": "local",
  "currency": "ETB",
  "plans": [
    {
      "id": 1,
      "name": "Free Plan",
      "monthly_price": 0,
      "currency": "ETB",
      "region": "local",
      "max_tokens": 10000,
      "daily_message_limit": 10,
      "ads_enabled": true,
      "is_default": true,
      "description": "Free tier with basic features",
      "image_url": "https://example.com/plan-image.jpg",
      "tag": "Free",
      "trial_days": 0,
      "engine_id": 1,
      "badge": "Free",
      "engine": {
        "name": "DeepSeek",
        "provider": "deepseek",
        "max_tokens": 32000,
        "price_per_1k": 0.14,
        "is_vision_support": false
      }
    }
  ]
}
```

### Get Single Plan

Retrieve details of a specific plan.

**Endpoint:** `GET /{region}/plans/{id}`

**Response:**
```json
{
  "id": 1,
  "name": "Free Plan",
  "monthly_price": 0,
  "currency": "ETB",
  "region": "local",
  "max_tokens": 10000,
  "daily_message_limit": 10,
  "ads_enabled": true,
  "is_default": true,
  "description": "Free tier with basic features",
  "image_url": "https://example.com/plan-image.jpg",
  "tag": "Free",
  "trial_days": 0,
  "billing_cycle": "monthly",
  "engine_id": 1,
  "badge": "Free",
  "engine": {
    "name": "DeepSeek",
    "provider": "deepseek",
    "max_tokens": 32000,
    "price_per_1k": 0.14,
    "is_vision_support": false
  }
}
```

### Compare Plans

Compare multiple plans side by side.

**Endpoint:** `POST /{region}/plans/compare`

**Request Body:**
```json
{
  "plan_ids": [1, 2, 3]
}
```

**Response:**
```json
{
  "plans": [
    {
      "id": 1,
      "name": "Free Plan",
      "monthly_price": 0,
      "currency": "ETB",
      "billing_cycle": "monthly",
      "max_tokens": 10000,
      "daily_message_limit": 10,
      "ads_enabled": true,
      "description": "Free tier",
      "trial_days": 0,
      "engine": {
        "name": "DeepSeek",
        "provider": "deepseek",
        "is_vision_support": false
      }
    }
  ]
}
```

---

## Subscriptions

### Create Subscription

Subscribe to a plan. For free plans, subscription is created immediately. For paid plans, a payment is initiated.

**Endpoint:** `POST /{region}/subscribe`

**Request Body:**
```json
{
  "plan_id": 1,
  "payment_method": "stripe" // Optional, required for paid plans
}
```

**Response (Free Plan):**
```json
{
  "message": "Free subscription activated.",
  "subscription": {
    "id": 1,
    "user_id": 1,
    "plan_id": 1,
    "start_date": "2025-11-28T00:00:00.000000Z",
    "end_date": "2025-12-28T00:00:00.000000Z",
    "tokens_used": 0,
    "is_active": true,
    "auto_renew": false,
    "tokens_available": 10000,
    "plan": {
      "name": "Free Plan",
      "monthly_price": 0,
      "max_tokens": 10000,
      "daily_message_limit": 10,
      "ads_enabled": true
    }
  }
}
```

**Response (Paid Plan):**
```json
{
  "message": "Payment initiated. Please complete payment to activate subscription.",
  "payment": {
    "id": 1,
    "reference": "pay_abc123",
    "amount": 29.99,
    "currency": "USD",
    "provider": "stripe",
    "status": "pending",
    "checkout_url": "https://checkout.stripe.com/pay/..."
  }
}
```

### Get Current Subscription

Get the user's active subscription.

**Endpoint:** `GET /{region}/subscription`

**Response:**
```json
{
  "id": 1,
  "user_id": 1,
  "plan_id": 1,
  "start_date": "2025-11-28T00:00:00.000000Z",
  "end_date": "2025-12-28T00:00:00.000000Z",
  "tokens_used": 5000,
  "is_active": true,
  "auto_renew": true,
  "tokens_available": 5000,
  "plan": {
    "name": "Pro Plan",
    "monthly_price": 29.99,
    "max_tokens": 10000,
    "daily_message_limit": 100,
    "ads_enabled": false
  }
}
```

### Check if User Has Subscription

Quick check if user has an active subscription.

**Endpoint:** `GET /{region}/user/has-subscription`

**Response:**
```json
{
  "has_subscription": true,
  "subscription_id": 1,
  "plan_name": "Pro Plan"
}
```

### Get All Subscriptions

Get all subscriptions (active and inactive) for the user.

**Endpoint:** `GET /{region}/subscriptions`

**Query Parameters:**
- `per_page` (optional): Items per page (default: 10)
- `page` (optional): Page number

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "user_id": 1,
      "plan_id": 1,
      "start_date": "2025-11-28T00:00:00.000000Z",
      "end_date": "2025-12-28T00:00:00.000000Z",
      "tokens_used": 5000,
      "is_active": true,
      "auto_renew": true,
      "plan": {
        "name": "Pro Plan",
        "monthly_price": 29.99
      }
    }
  ],
  "links": {...},
  "meta": {...}
}
```

### Get Subscription Details

Get details of a specific subscription.

**Endpoint:** `GET /{region}/subscriptions/{id}`

**Response:** Same as subscription object in list response.

### Update Subscription

Update subscription settings (e.g., auto-renew).

**Endpoint:** `PUT /{region}/subscriptions/{id}`

**Request Body:**
```json
{
  "auto_renew": true,
  "payment_method": "stripe" // Optional, for updating payment method
}
```

**Response:** Updated subscription object.

### Cancel Subscription

Cancel a subscription.

**Endpoint:** `DELETE /{region}/subscriptions/{id}`

**Response:**
```json
{
  "message": "Subscription cancelled."
}
```

### Get Renewal Status

Get renewal information for active subscription.

**Endpoint:** `GET /{region}/subscriptions/renewal-status`

**Response:**
```json
{
  "auto_renew": true,
  "next_renewal_date": "2025-12-28T00:00:00.000000Z",
  "grace_period_ends_at": null,
  "payment_failure_count": 0
}
```

### Renew Subscription

Manually trigger subscription renewal.

**Endpoint:** `POST /{region}/subscriptions/{id}/renew`

**Response:**
```json
{
  "message": "Renewal process initiated."
}
```

### Get Upgrade Options

Get plans that are upgrades from current plan.

**Endpoint:** `GET /{region}/subscriptions/upgrade-options`

**Response:** Array of plan objects (higher price than current).

### Get Downgrade Options

Get plans that are downgrades from current plan.

**Endpoint:** `GET /{region}/subscriptions/downgrade-options`

**Response:** Array of plan objects (lower price than current).

### Get Payment Failure History

Get history of failed payments for subscriptions.

**Endpoint:** `GET /{region}/subscriptions/payment-failure-history`

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "reference": "pay_abc123",
      "amount": 29.99,
      "currency": "USD",
      "provider": "stripe",
      "status": "failed",
      "created_at": "2025-11-28T00:00:00.000000Z"
    }
  ]
}
```

---

## Token Usage

### Get Usage Logs

Get paginated list of token usage records.

**Endpoint:** `GET /{region}/token-usage`

**Query Parameters:**
- `date_from` (optional): Start date (YYYY-MM-DD)
- `date_to` (optional): End date (YYYY-MM-DD)
- `per_page` (optional): Items per page (default: 50)
- `page` (optional): Page number

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "user_id": 1,
      "subscription_id": 1,
      "engine_id": 1,
      "tokens_used": 150,
      "cost": 0.021,
      "created_at": "2025-11-28T00:00:00.000000Z"
    }
  ],
  "links": {...},
  "meta": {...}
}
```

### Get Usage Statistics

Get overall usage statistics for active subscription.

**Endpoint:** `GET /{region}/token-usage/stats`

**Response:**
```json
{
  "total_tokens_used": 5000,
  "total_token_quota": 10000,
  "daily_messages_used": 5,
  "daily_message_limit": 100,
  "ads_enabled": false
}
```

### Get Daily Usage

Get daily usage breakdown for the last 30 days.

**Endpoint:** `GET /{region}/token-usage/daily`

**Response:**
```json
[
  {
    "date": "2025-11-28",
    "tokens": 150,
    "messages": 3
  },
  {
    "date": "2025-11-27",
    "tokens": 200,
    "messages": 5
  }
]
```

### Get Weekly Usage

Get weekly usage aggregation.

**Endpoint:** `GET /{region}/token-usage/weekly`

**Response:**
```json
[
  {
    "week": "2025-W48",
    "tokens": 1050,
    "messages": 25
  }
]
```

### Get Monthly Usage

Get monthly usage aggregation.

**Endpoint:** `GET /{region}/token-usage/monthly`

**Response:**
```json
[
  {
    "month": "2025-11",
    "tokens": 5000,
    "messages": 120
  }
]
```

### Get Usage Analytics

Get detailed analytics with trends and insights.

**Endpoint:** `GET /{region}/token-usage/analytics`

**Response:**
```json
{
  "total_tokens": 5000,
  "total_messages": 120,
  "average_tokens_per_message": 41.67,
  "peak_usage_day": "2025-11-28",
  "trend": "increasing",
  "projected_monthly_usage": 5500
}
```

### Get Quota Status

Get current quota status with warnings.

**Endpoint:** `GET /{region}/token-usage/quota-status`

**Response:**
```json
{
  "tokens_used": 5000,
  "max_tokens": 10000,
  "tokens_remaining": 5000,
  "percentage_used": 50,
  "daily_messages_used": 5,
  "daily_message_limit": 100,
  "days_remaining": 30,
  "status_message": "✅ Quota OK",
  "is_quota_exceeded": false
}
```

### Export Usage Data

Export usage data as CSV.

**Endpoint:** `GET /{region}/token-usage/export`

**Query Parameters:**
- `format` (optional): `csv` or `json` (default: `csv`)
- `date_from` (optional): Start date
- `date_to` (optional): End date

**Response:** CSV file download or JSON response.

---

## Billing

### Get Current Billing

Get current active subscription billing information.

**Endpoint:** `GET /{region}/billing/current`

**Response:** Same as subscription object.

### Get Billing History

Get all past and current subscriptions.

**Endpoint:** `GET /{region}/billing/history`

**Response:**
```json
{
  "history": [
    {
      "id": 1,
      "plan": {
        "name": "Pro Plan",
        "monthly_price": 29.99
      },
      "start_date": "2025-11-28T00:00:00.000000Z",
      "end_date": "2025-12-28T00:00:00.000000Z",
      "is_active": true
    }
  ]
}
```

### Get Usage Summary

Get token usage summary for current subscription.

**Endpoint:** `GET /{region}/billing/usage`

**Response:**
```json
{
  "plan": "Pro Plan",
  "tokens_used": 5000,
  "tokens_limit": 10000,
  "remaining": 5000
}
```

### Toggle Auto-Renew

Toggle automatic renewal for active subscription.

**Endpoint:** `POST /{region}/billing/toggle-renew`

**Response:**
```json
{
  "message": "Auto-renew toggled.",
  "auto_renew": true
}
```

### Get Invoices

Get list of payment invoices (successful payments).

**Endpoint:** `GET /{region}/billing/invoices`

**Query Parameters:**
- `per_page` (optional): Items per page (default: 10)
- `page` (optional): Page number

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "reference": "pay_abc123",
      "amount": 29.99,
      "currency": "USD",
      "provider": "stripe",
      "status": "success",
      "created_at": "2025-11-28T00:00:00.000000Z"
    }
  ],
  "links": {...},
  "meta": {...}
}
```

### Get Invoice Details

Get details of a specific invoice.

**Endpoint:** `GET /{region}/billing/invoices/{id}`

**Response:** Payment object with full details.

### Get Upcoming Charges

Get upcoming subscription renewal charges.

**Endpoint:** `GET /{region}/billing/upcoming-charges`

**Response:**
```json
{
  "next_charge_date": "2025-12-28T00:00:00.000000Z",
  "amount": 29.99,
  "currency": "USD",
  "plan_name": "Pro Plan"
}
```

### Get Payment Methods

Get user's saved payment methods.

**Endpoint:** `GET /{region}/billing/payment-methods`

**Response:**
```json
{
  "payment_methods": [
    {
      "id": 1,
      "type": "card",
      "last4": "4242",
      "brand": "visa",
      "exp_month": 12,
      "exp_year": 2025,
      "is_default": true
    }
  ]
}
```

### Add Payment Method

Add a new payment method.

**Endpoint:** `POST /{region}/billing/payment-methods`

**Request Body:**
```json
{
  "provider": "stripe",
  "payment_method_id": "pm_abc123",
  "is_default": false
}
```

**Response:**
```json
{
  "message": "Payment method added successfully.",
  "payment_method": {
    "id": 1,
    "type": "card",
    "last4": "4242"
  }
}
```

### Remove Payment Method

Remove a payment method.

**Endpoint:** `DELETE /{region}/billing/payment-methods/{id}`

**Response:**
```json
{
  "message": "Payment method removed successfully."
}
```

### Set Default Payment Method

Set a payment method as default.

**Endpoint:** `PUT /{region}/billing/payment-methods/{id}/default`

**Response:**
```json
{
  "message": "Default payment method updated."
}
```

### Get Billing Address

Get user's billing address.

**Endpoint:** `GET /{region}/billing/address`

**Response:**
```json
{
  "address_line1": "123 Main St",
  "address_line2": "Apt 4B",
  "city": "Addis Ababa",
  "state": "Addis Ababa",
  "postal_code": "1000",
  "country": "ET"
}
```

### Update Billing Address

Update user's billing address.

**Endpoint:** `PUT /{region}/billing/address`

**Request Body:**
```json
{
  "address_line1": "123 Main St",
  "address_line2": "Apt 4B",
  "city": "Addis Ababa",
  "state": "Addis Ababa",
  "postal_code": "1000",
  "country": "ET"
}
```

**Response:**
```json
{
  "message": "Billing address updated successfully."
}
```

### Get Tax Information

Get user's tax information.

**Endpoint:** `GET /{region}/billing/tax-information`

**Response:**
```json
{
  "tax_id": "TAX123456",
  "tax_name": "VAT",
  "rate": 0.15
}
```

### Update Tax Information

Update user's tax information.

**Endpoint:** `PUT /{region}/billing/tax-information`

**Request Body:**
```json
{
  "tax_id": "TAX123456",
  "tax_name": "VAT"
}
```

**Response:**
```json
{
  "message": "Tax information updated successfully."
}
```

---

## Payments

### Create Payment

Create a new payment (for subscription or one-time payment).

**Endpoint:** `POST /{region}/payments/create`

**Request Body:**
```json
{
  "plan_id": 1, // Optional, if subscribing to a plan
  "amount": 29.99, // Required if plan_id not provided
  "currency": "USD",
  "provider": "stripe",
  "description": "Subscription payment", // Optional
  "success_url": "https://yourapp.com/success", // Optional
  "cancel_url": "https://yourapp.com/cancel", // Optional
  "return_url": "https://yourapp.com/return" // Optional
}
```

**Response:**
```json
{
  "message": "Payment created successfully",
  "payment": {
    "id": 1,
    "reference": "pay_abc123",
    "amount": 29.99,
    "currency": "USD",
    "provider": "stripe",
    "status": "pending",
    "checkout_url": "https://checkout.stripe.com/pay/...",
    "created_at": "2025-11-28T00:00:00.000000Z"
  }
}
```

### Get Payment Status

Get status of a payment by reference.

**Endpoint:** `GET /{region}/payments/status`

**Query Parameters:**
- `reference` (required): Payment reference

**Response:**
```json
{
  "payment": {
    "id": 1,
    "reference": "pay_abc123",
    "status": "success",
    "transaction_id": "txn_xyz789"
  }
}
```

### Verify Payment

Manually verify a payment.

**Endpoint:** `POST /{region}/payments/verify`

**Request Body:**
```json
{
  "reference": "pay_abc123"
}
```

**Response:**
```json
{
  "message": "Payment verified successfully.",
  "payment": {
    "id": 1,
    "status": "success"
  }
}
```

### Get Payment History

Get user's payment history with filters.

**Endpoint:** `GET /{region}/payments/history`

**Query Parameters:**
- `status` (optional): Filter by status (pending, success, failed, refunded)
- `provider` (optional): Filter by provider (stripe, chapa)
- `date_from` (optional): Start date (YYYY-MM-DD)
- `date_to` (optional): End date (YYYY-MM-DD)
- `amount_min` (optional): Minimum amount
- `amount_max` (optional): Maximum amount
- `per_page` (optional): Items per page (default: 10)
- `page` (optional): Page number

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "reference": "pay_abc123",
      "amount": 29.99,
      "currency": "USD",
      "provider": "stripe",
      "status": "success",
      "transaction_id": "txn_xyz789",
      "created_at": "2025-11-28T00:00:00.000000Z"
    }
  ],
  "links": {...},
  "meta": {...}
}
```

### Get Payment Details

Get details of a specific payment.

**Endpoint:** `GET /{region}/payments/{id}`

**Response:**
```json
{
  "id": 1,
  "reference": "pay_abc123",
  "user_id": 1,
  "amount": 29.99,
  "currency": "USD",
  "provider": "stripe",
  "status": "success",
  "transaction_id": "txn_xyz789",
  "metadata": {
    "plan_id": 1,
    "description": "Subscription payment"
  },
  "created_at": "2025-11-28T00:00:00.000000Z",
  "updated_at": "2025-11-28T00:00:00.000000Z"
}
```

### Retry Failed Payment

Retry a failed payment.

**Endpoint:** `POST /{region}/payments/{id}/retry`

**Response:**
```json
{
  "message": "Payment retry initiated."
}
```

### Cancel Payment

Cancel a pending payment.

**Endpoint:** `POST /{region}/payments/{id}/cancel`

**Response:**
```json
{
  "message": "Payment cancelled successfully."
}
```

### Request Refund

Request a refund for a successful payment.

**Endpoint:** `POST /{region}/payments/{id}/refund`

**Request Body:**
```json
{
  "amount": 15.00, // Optional, leave empty for full refund
  "reason": "Customer requested refund" // Optional
}
```

**Response:**
```json
{
  "message": "Refund initiated.",
  "refund": {
    "id": 1,
    "amount": 15.00,
    "status": "pending"
  }
}
```

### Get Refund Status

Get status of a refund.

**Endpoint:** `GET /{region}/payments/{id}/refund-status`

**Response:**
```json
{
  "refunded": true,
  "refund_amount": 15.00,
  "refund_date": "2025-11-28T00:00:00.000000Z",
  "status": "completed"
}
```

### Download Receipt

Download payment receipt as PDF.

**Endpoint:** `GET /{region}/payments/{id}/receipt`

**Response:** PDF file download.

### Get Payment Summary

Get payment summary statistics.

**Endpoint:** `GET /{region}/payments/summary`

**Response:**
```json
{
  "total_payments": 10,
  "total_amount": 299.90,
  "successful_payments": 9,
  "failed_payments": 1,
  "pending_payments": 0,
  "total_refunded": 0
}
```

### Get Upcoming Payments

Get upcoming scheduled payments (for subscriptions).

**Endpoint:** `GET /{region}/payments/upcoming`

**Response:**
```json
{
  "upcoming": [
    {
      "date": "2025-12-28T00:00:00.000000Z",
      "amount": 29.99,
      "currency": "USD",
      "plan_name": "Pro Plan",
      "auto_renew": true
    }
  ]
}
```

---

## Notifications

> **NEW:** Real-time WebSocket notifications and Web Push API are now available! See [Frontend Notifications Integration Guide](./FRONTEND_NOTIFICATIONS_INTEGRATION.md) for complete setup instructions.

### Get Notifications

Get user's notifications.

**Endpoint:** `GET /{region}/notifications`

**Query Parameters:**
- `per_page` (optional): Items per page (default: 20)
- `unread_only` (optional): Filter unread only (true/false)

**Response:**
```json
[
  {
    "id": "uuid-here",
    "type": "App\\Notifications\\ChatSessionSharedNotification",
    "data": {
      "title": "Chat Session Shared",
      "message": "John shared \"My Chat\" with you",
      "type": "chat_session_shared",
      "share_id": 1,
      "share_status": "pending",
      "original_session_id": "session-uuid",
      "duplicated_session_id": null,
      "action_url": "/chat/shares/1"
    },
    "read_at": null,
    "created_at": "2025-11-28T00:00:00.000000Z"
  }
]
```

### Mark Notification as Read

Mark a single notification as read.

**Endpoint:** `POST /{region}/notifications/{notificationId}/read`

**Response:**
```json
{
  "status": "marked as read"
}
```

### Mark All Notifications as Read

Mark all notifications as read.

**Endpoint:** `POST /{region}/notifications/read`

**Response:**
```json
{
  "status": "all marked as read"
}
```

---

## WebSocket Real-Time Notifications (NEW)

### Get WebSocket Configuration

Get WebSocket connection details for real-time notifications.

**Endpoint:** `GET /{region}/websocket/config`

**Authentication:** Required

**Response:**
```json
{
  "websocket_url": "wss://chat.akmicroservice.com/app/",
  "user_id": 1,
  "channel": "private-user.1"
}
```

**Usage:**
- Use this to get the WebSocket URL and user channel
- Connect to the WebSocket server using Laravel Echo or similar
- Listen to the private channel for real-time notifications

### Authenticate WebSocket Connection

Authenticate WebSocket connection (called automatically by WebSocket client libraries).

**Endpoint:** `POST /{region}/websocket/authenticate`

**Authentication:** Required

**Body:**
```json
{
  "channel_name": "private-user.1",
  "socket_id": "123.456"
}
```

**Response:**
```json
{
  "auth": "akili-chat-key:signature"
}
```

---

## Web Push API (NEW)

### Get VAPID Public Key

Get the VAPID public key required for Web Push subscription. This key must be retrieved from the backend and used when subscribing to push notifications.

**Endpoint:** `GET /{region}/webpush/vapid-key`

**Authentication:** Required

**Response:**
```json
{
  "vapid_public_key": "BKxVx..."
}
```

**Error Response (if not configured):**
```json
{
  "error": "VAPID public key not configured",
  "message": "Web Push is not available. Please contact support."
}
```

**Usage:**
```javascript
// Get VAPID key before subscribing
const response = await fetch('/api/v1/local/webpush/vapid-key', {
  headers: { 'Authorization': `Bearer ${token}` }
});
const { vapid_public_key } = await response.json();

// Use it when subscribing
const subscription = await registration.pushManager.subscribe({
  userVisibleOnly: true,
  applicationServerKey: urlBase64ToUint8Array(vapid_public_key)
});
```

---

### Subscribe to Web Push Notifications

Register a browser push subscription to receive notifications even when the browser tab is closed.

**Endpoint:** `POST /{region}/webpush/subscribe`

**Authentication:** Required

**Body:**
```json
{
  "endpoint": "https://fcm.googleapis.com/fcm/send/...",
  "keys": {
    "p256dh": "base64-encoded-p256dh-key",
    "auth": "base64-encoded-auth-key"
  }
}
```

**Response:**
```json
{
  "status": "subscribed",
  "subscription_id": 1
}
```

**How to get subscription:**
1. Request notification permission: `Notification.requestPermission()`
2. Register service worker
3. Subscribe: `registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: vapidPublicKey })`
4. Send subscription to this endpoint

### Unsubscribe from Web Push

Remove a browser push subscription.

**Endpoint:** `POST /{region}/webpush/unsubscribe`

**Authentication:** Required

**Body:**
```json
{
  "endpoint": "https://fcm.googleapis.com/fcm/send/..."
}
```

**Response:**
```json
{
  "status": "unsubscribed"
}
```

### Get Web Push Subscriptions

Get all active Web Push subscriptions for the authenticated user.

**Endpoint:** `GET /{region}/webpush/subscriptions`

**Authentication:** Required

**Response:**
```json
{
  "subscriptions": [
    {
      "id": 1,
      "endpoint": "https://fcm.googleapis.com/fcm/send/...",
      "created_at": "2025-12-01T09:00:00.000000Z"
    }
  ],
  "count": 1
}
```

---

## Chat & Sessions

> **Note:** All chat endpoints are accessible to **both authenticated users and guest users**. Guest users don't need to provide an Authorization header. See [Guest Users](#guest-users) section for more details.

### Get Chat Sessions

Get user's chat sessions (or guest sessions if not authenticated).

**Endpoint:** `GET /{region}/chat/sessions`

**Authentication:** Optional (guest users can access)

**Response:**
```json
[
  {
    "id": "session-uuid",
    "title": "My Chat",
    "user_id": 1,
    "created_at": "2025-11-28T00:00:00.000000Z",
    "updated_at": "2025-11-28T00:00:00.000000Z"
  }
]
```

### Get Chat Messages

Get messages for a specific chat session.

**Endpoint:** `GET /{region}/chat/messages/{sessionId}`

**Authentication:** Optional (guest users can access their own sessions)

**Response:**
```json
[
  {
    "id": 1,
    "role": "user",
    "content": "Hello!",
    "created_at": "2025-11-28T00:00:00.000000Z"
  },
  {
    "id": 2,
    "role": "assistant",
    "content": "Hi there! How can I help?",
    "created_at": "2025-11-28T00:00:01.000000Z"
  }
]
```

### Rename Chat Session

Rename a chat session.

**Endpoint:** `PUT /{region}/chat/sessions/{sessionId}`

**Authentication:** Optional (guest users can rename their own sessions)

**Request Body:**
```json
{
  "title": "New Title"
}
```

**Response:**
```json
{
  "message": "Session renamed successfully.",
  "session": {
    "id": "session-uuid",
    "title": "New Title"
  }
}
```

### Delete Chat Session

Delete a chat session.

**Endpoint:** `DELETE /{region}/chat/sessions/{sessionId}`

**Authentication:** Optional (guest users can delete their own sessions)

**Response:**
```json
{
  "message": "Session deleted successfully."
}
```

### Share Chat Session

Share a chat session with another user.

**Endpoint:** `POST /{region}/chat/sessions/{sessionId}/share`

**Authentication:** Required (guest users cannot share sessions)

**Request Body:**
```json
{
  "user_id": 2, // OR "email": "user@example.com"
  "message": "Check out this conversation!" // Optional
}
```

**Response:**
```json
{
  "message": "Chat session shared successfully.",
  "share": {
    "id": 1,
    "shared_by_user_id": 1,
    "shared_to_user_id": 2,
    "original_chat_session_id": "session-uuid",
    "status": "pending",
    "message": "Check out this conversation!"
  }
}
```

### Get Incoming Shares

Get chat sessions shared with the user.

**Endpoint:** `GET /{region}/chat/shares/incoming`

**Response:**
```json
[
  {
    "id": 1,
    "shared_by": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com"
    },
    "original_session": {
      "id": "session-uuid",
      "title": "My Chat"
    },
    "status": "pending",
    "message": "Check this out!",
    "created_at": "2025-11-28T00:00:00.000000Z"
  }
]
```

### Get Outgoing Shares

Get chat sessions the user has shared.

**Endpoint:** `GET /{region}/chat/shares/outgoing`

**Response:** Same format as incoming shares.

### Get Share Details

Get details of a specific share.

**Endpoint:** `GET /{region}/chat/shares/{shareId}`

**Response:** Share object with full details.

### Accept Share

Accept a shared chat session (creates a duplicate).

**Endpoint:** `POST /{region}/chat/shares/{shareId}/accept`

**Response:**
```json
{
  "message": "Share accepted. New session created.",
  "duplicated_session_id": "new-session-uuid"
}
```

### Decline Share

Decline a shared chat session.

**Endpoint:** `POST /{region}/chat/shares/{shareId}/decline`

**Response:**
```json
{
  "message": "Share declined."
}
```

---

## User Profile

### Get User Profile

Get authenticated user's profile.

**Endpoint:** `GET /{region}/user`

**Response:**
```json
{
  "id": 1,
  "name": "John Doe",
  "email": "john@example.com",
  "region": "local",
  "created_at": "2025-11-28T00:00:00.000000Z"
}
```

### Logout

Logout the user (invalidate token).

**Endpoint:** `POST /{region}/logout`

**Response:**
```json
{
  "message": "Logged out successfully."
}
```

### Store FCM Token

Store Firebase Cloud Messaging token for push notifications.

**Endpoint:** `POST /{region}/fcm-token`

**Request Body:**
```json
{
  "token": "fcm-token-here"
}
```

**Response:**
```json
{
  "message": "FCM token stored successfully."
}
```

---

## Error Responses

All endpoints may return the following error responses:

### 400 Bad Request
```json
{
  "error": "Invalid request parameters."
}
```

### 401 Unauthorized
```json
{
  "error": "Unauthenticated."
}
```

### 403 Forbidden
```json
{
  "message": "You do not have permission to perform this action."
}
```

### 404 Not Found
```json
{
  "message": "Resource not found."
}
```

### 422 Validation Error
```json
{
  "message": "Validation failed",
  "errors": {
    "plan_id": ["The plan id field is required."]
  }
}
```

### 500 Internal Server Error
```json
{
  "error": "An error occurred while processing your request."
}
```

---

## Rate Limiting

- Chat endpoints: 60 requests per minute
- Authentication endpoints: 3 requests per minute (for password reset)
- Other endpoints: Standard rate limiting applies

---

## Notes

1. **Region Detection**: The region (`local` or `intl`) is automatically detected from the URL path. Ensure you're using the correct region prefix.

2. **CORS Headers**: All responses include CORS headers for cross-origin requests.

3. **Pagination**: List endpoints support pagination. Use `per_page` and `page` query parameters.

4. **Date Formats**: All dates are returned in ISO 8601 format (e.g., `2025-11-28T00:00:00.000000Z`).

5. **Currency**: Currency codes follow ISO 4217 standard (USD, ETB, etc.).

6. **Payment Providers**: Supported providers are `stripe` and `chapa`.

7. **Subscription Status**: Subscriptions can be `active` or `inactive`. Only one active subscription per user per region.

8. **Payment Status**: Payments can be `pending`, `success`, `failed`, or `refunded`.

9. **Guest Users**: Guest users can access chat endpoints without authentication. They are identified via device fingerprinting and have 24-hour session expiration. All guest sessions are automatically migrated to the user account upon signup. See [Guest Users](#guest-users) section for details.

---

## Support

For API support or questions, please contact the development team.

