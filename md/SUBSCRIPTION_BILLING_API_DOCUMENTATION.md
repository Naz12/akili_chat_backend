# Subscription, Billing, Usage & Payment API Documentation

**Base URL:** `https://chat.akmicroservice.com/api/v1`

**Regions:** `local` or `intl` (replace `{region}` in URLs below)

**Authentication:** All endpoints require `Authorization: Bearer {token}` header (except public endpoints)

---

## Table of Contents

1. [Plans API](#plans-api)
2. [Subscription API](#subscription-api)
3. [Usage & Metering API](#usage--metering-api)
4. [Payment API](#payment-api)
5. [Billing API](#billing-api)
6. [Payment Method Selection](#payment-method-selection)

---

## Plans API

### Get All Plans

Retrieve all available plans for the user's region.

**Endpoint:** `GET /{region}/plans`

**Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
- `price_min` (optional): Minimum price filter
- `price_max` (optional): Maximum price filter
- `features` (optional): Comma-separated feature names

**Request Example:**
```http
GET /api/v1/local/plans?price_min=0&price_max=100
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response (200 OK):**
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
    },
    {
      "id": 2,
      "name": "Pro Plan",
      "monthly_price": 30,
      "currency": "ETB",
      "region": "local",
      "max_tokens": 50000,
      "daily_message_limit": 50,
      "ads_enabled": false,
      "is_default": false,
      "description": "Professional plan with advanced features",
      "image_url": "https://example.com/pro-plan.jpg",
      "tag": "Pro",
      "trial_days": 7,
      "billing_cycle": "monthly",
      "engine_id": 1,
      "badge": "Recommended",
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

**Headers:**
```
Authorization: Bearer {token}
```

**Request Example:**
```http
GET /api/v1/local/plans/2
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response (200 OK):**
```json
{
  "id": 2,
  "name": "Pro Plan",
  "monthly_price": 30,
  "currency": "ETB",
  "region": "local",
  "max_tokens": 50000,
  "daily_message_limit": 50,
  "ads_enabled": false,
  "is_default": false,
  "description": "Professional plan with advanced features",
  "image_url": "https://example.com/pro-plan.jpg",
  "tag": "Pro",
  "trial_days": 7,
  "billing_cycle": "monthly",
  "engine_id": 1,
  "badge": "Recommended",
  "engine": {
    "name": "DeepSeek",
    "provider": "deepseek",
    "max_tokens": 32000,
    "price_per_1k": 0.14,
    "is_vision_support": false
  }
}
```

---

## Subscription API

### Subscribe to a Plan

Subscribe the authenticated user to a plan. Payment method is **automatically selected** based on user's region:
- **International users** (`region = 'intl'`) → **Stripe** (auto-selected)
- **Local users** (`region = 'local'`) → **Chapa** (auto-selected)

**Endpoint:** `POST /{region}/subscribe`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "plan_id": 2,
  "payment_method": "chapa"  // Optional: Auto-selected if not provided (stripe for intl, chapa for local)
}
```

**Request Example (Local User - Chapa auto-selected):**
```http
POST /api/v1/local/subscribe
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
Content-Type: application/json

{
  "plan_id": 2
}
```

**Request Example (International User - Stripe auto-selected):**
```http
POST /api/v1/intl/subscribe
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
Content-Type: application/json

{
  "plan_id": 6
}
```

**Response for Free Plan (200 OK):**
```json
{
  "message": "Free subscription activated.",
  "subscription": {
    "id": 123,
    "start_date": "2025-11-30 10:00:00",
    "end_date": "2025-12-30 10:00:00",
    "tokens_used": 0,
    "is_active": true,
    "tokens_available": 10000,
    "days_left": 30,
    "is_expired": false,
    "plan": {
      "name": "Free Plan",
      "monthly_price": 0,
      "max_tokens": 10000,
      "daily_message_limit": 10,
      "ads_enabled": true
    },
    "engine": {
      "name": "DeepSeek",
      "provider": "deepseek",
      "max_tokens": 32000,
      "price_per_1k": 0.14,
      "is_vision_support": false
    }
  }
}
```

**Response for Paid Plan (200 OK):**
```json
{
  "payment_method": "chapa",
  "payment_id": 456,
  "reference": "chapa_ref_123456789",
  "checkout_url": "https://checkout.chapa.co/checkout/payment/chapa_ref_123456789",
  "subscription_id": 124,
  "status": "pending"
}
```

**Error Responses:**

**422 Unprocessable Entity - Plan not found:**
```json
{
  "message": "Plan not found."
}
```

**422 Unprocessable Entity - Plan not available for region:**
```json
{
  "message": "Plan not available for your region. Please select a plan for local region."
}
```

**403 Forbidden - Trial already used:**
```json
{
  "message": "Trial plan already used. Please select a paid plan."
}
```

### Get Current Subscription

Get the user's current active subscription.

**Endpoint:** `GET /{region}/subscription`

**Headers:**
```
Authorization: Bearer {token}
```

**Request Example:**
```http
GET /api/v1/local/subscription
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response (200 OK) - With Active Subscription:**
```json
{
  "id": 123,
  "start_date": "2025-11-01 10:00:00",
  "end_date": "2025-12-01 10:00:00",
  "tokens_used": 5000,
  "is_active": true,
  "tokens_available": 45000,
  "days_left": 1,
  "is_expired": false,
  "plan": {
    "name": "Pro Plan",
    "monthly_price": 30,
    "max_tokens": 50000,
    "daily_message_limit": 50,
    "ads_enabled": false
  },
  "engine": {
    "name": "DeepSeek",
    "provider": "deepseek",
    "max_tokens": 32000,
    "price_per_1k": 0.14,
    "is_vision_support": false
  }
}
```

**Response (200 OK) - No Active Subscription (Returns Default Free Plan):**
```json
{
  "subscription": null,
  "plan": {
    "id": 1,
    "name": "Free Plan",
    "max_tokens": 10000,
    "daily_message_limit": 10,
    "ads_enabled": true,
    "is_guest_mode": true,
    "engine": {
      "name": "DeepSeek",
      "provider": "deepseek",
      "max_tokens": 32000,
      "price_per_1k": 0.14,
      "is_vision_support": false
    }
  }
}
```

### Check if User Has Subscription

Quick check to see if user has an active subscription.

**Endpoint:** `GET /{region}/user/has-subscription`

**Headers:**
```
Authorization: Bearer {token}
```

**Request Example:**
```http
GET /api/v1/local/user/has-subscription
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response (200 OK) - Has Subscription:**
```json
{
  "has_active_subscription": true,
  "is_vision_support": true
}
```

**Response (200 OK) - No Subscription:**
```json
{
  "has_active_subscription": false,
  "is_vision_support": false
}
```

### List All Subscriptions

Get all subscriptions (active and inactive) for the user.

**Endpoint:** `GET /{region}/subscriptions`

**Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
- `page` (optional): Page number for pagination
- `per_page` (optional): Items per page (default: 20)

**Request Example:**
```http
GET /api/v1/local/subscriptions?page=1&per_page=10
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response (200 OK):**
```json
{
  "data": [
    {
      "id": 123,
      "start_date": "2025-11-01 10:00:00",
      "end_date": "2025-12-01 10:00:00",
      "tokens_used": 5000,
      "is_active": true,
      "tokens_available": 45000,
      "days_left": 1,
      "is_expired": false,
      "plan": {
        "name": "Pro Plan",
        "monthly_price": 30,
        "max_tokens": 50000,
        "daily_message_limit": 50,
        "ads_enabled": false
      }
    }
  ],
  "current_page": 1,
  "per_page": 10,
  "total": 1,
  "last_page": 1
}
```

### Get Single Subscription

Get details of a specific subscription.

**Endpoint:** `GET /{region}/subscriptions/{id}`

**Headers:**
```
Authorization: Bearer {token}
```

**Request Example:**
```http
GET /api/v1/local/subscriptions/123
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response (200 OK):**
```json
{
  "id": 123,
  "start_date": "2025-11-01 10:00:00",
  "end_date": "2025-12-01 10:00:00",
  "tokens_used": 5000,
  "is_active": true,
  "tokens_available": 45000,
  "days_left": 1,
  "is_expired": false,
  "plan": {
    "name": "Pro Plan",
    "monthly_price": 30,
    "max_tokens": 50000,
    "daily_message_limit": 50,
    "ads_enabled": false
  },
  "engine": {
    "name": "DeepSeek",
    "provider": "deepseek",
    "max_tokens": 32000,
    "price_per_1k": 0.14,
    "is_vision_support": false
  }
}
```

### Update Subscription

Update subscription settings (auto_renew, payment_method).

**Endpoint:** `PUT /{region}/subscriptions/{id}`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "auto_renew": true,
  "payment_method": "chapa"  // Optional
}
```

**Request Example:**
```http
PUT /api/v1/local/subscriptions/123
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
Content-Type: application/json

{
  "auto_renew": false
}
```

**Response (200 OK):**
```json
{
  "id": 123,
  "start_date": "2025-11-01 10:00:00",
  "end_date": "2025-12-01 10:00:00",
  "tokens_used": 5000,
  "is_active": true,
  "tokens_available": 45000,
  "days_left": 1,
  "is_expired": false,
  "plan": {
    "name": "Pro Plan",
    "monthly_price": 30,
    "max_tokens": 50000,
    "daily_message_limit": 50,
    "ads_enabled": false
  }
}
```

### Cancel Subscription

Cancel an active subscription.

**Endpoint:** `DELETE /{region}/subscriptions/{id}`

**Headers:**
```
Authorization: Bearer {token}
```

**Request Example:**
```http
DELETE /api/v1/local/subscriptions/123
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response (200 OK):**
```json
{
  "message": "Subscription cancelled successfully",
  "subscription": {
    "id": 123,
    "start_date": "2025-11-01 10:00:00",
    "end_date": "2025-11-30 10:00:00",
    "tokens_used": 5000,
    "is_active": false,
    "tokens_available": 45000,
    "days_left": 0,
    "is_expired": true,
    "plan": {
      "name": "Pro Plan",
      "monthly_price": 30,
      "max_tokens": 50000,
      "daily_message_limit": 50,
      "ads_enabled": false
    }
  }
}
```

### Get Renewal Status

Get information about subscription renewal.

**Endpoint:** `GET /{region}/subscriptions/renewal-status`

**Headers:**
```
Authorization: Bearer {token}
```

**Request Example:**
```http
GET /api/v1/local/subscriptions/renewal-status
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response (200 OK):**
```json
{
  "subscription_id": 123,
  "auto_renew": true,
  "next_renewal_date": "2025-12-01T10:00:00Z",
  "days_until_renewal": 1,
  "payment_method": "chapa",
  "payment_method_status": "configured",
  "grace_period_ends_at": null,
  "payment_failure_count": 0
}
```

### Manually Renew Subscription

Manually trigger subscription renewal.

**Endpoint:** `POST /{region}/subscriptions/{id}/renew`

**Headers:**
```
Authorization: Bearer {token}
```

**Request Example:**
```http
POST /api/v1/local/subscriptions/123/renew
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response (200 OK):**
```json
{
  "message": "Renewal initiated successfully",
  "subscription": {
    "id": 123,
    "start_date": "2025-11-01 10:00:00",
    "end_date": "2026-01-01 10:00:00",
    "tokens_used": 5000,
    "is_active": true,
    "tokens_available": 45000,
    "days_left": 32,
    "is_expired": false,
    "plan": {
      "name": "Pro Plan",
      "monthly_price": 30,
      "max_tokens": 50000,
      "daily_message_limit": 50,
      "ads_enabled": false
    }
  }
}
```

### Get Upgrade Options

Get available plans to upgrade to.

**Endpoint:** `GET /{region}/subscriptions/upgrade-options`

**Headers:**
```
Authorization: Bearer {token}
```

**Request Example:**
```http
GET /api/v1/local/subscriptions/upgrade-options
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response (200 OK):**
```json
{
  "current_plan": {
    "id": 2,
    "name": "Pro Plan",
    "monthly_price": 30
  },
  "upgrade_options": [
    {
      "id": 5,
      "name": "Gold Plan",
      "monthly_price": 60,
      "max_tokens": 100000,
      "daily_message_limit": 100,
      "description": "Premium plan with unlimited features"
    }
  ]
}
```

### Get Downgrade Options

Get available plans to downgrade to.

**Endpoint:** `GET /{region}/subscriptions/downgrade-options`

**Headers:**
```
Authorization: Bearer {token}
```

**Request Example:**
```http
GET /api/v1/local/subscriptions/downgrade-options
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response (200 OK):**
```json
{
  "current_plan": {
    "id": 5,
    "name": "Gold Plan",
    "monthly_price": 60
  },
  "downgrade_options": [
    {
      "id": 2,
      "name": "Pro Plan",
      "monthly_price": 30,
      "max_tokens": 50000,
      "daily_message_limit": 50,
      "description": "Professional plan with advanced features"
    },
    {
      "id": 1,
      "name": "Free Plan",
      "monthly_price": 0,
      "max_tokens": 10000,
      "daily_message_limit": 10,
      "description": "Free tier with basic features"
    }
  ]
}
```

### Get Payment Failure History

Get payment failure information for subscription.

**Endpoint:** `GET /{region}/subscriptions/payment-failure-history`

**Headers:**
```
Authorization: Bearer {token}
```

**Request Example:**
```http
GET /api/v1/local/subscriptions/payment-failure-history
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response (200 OK):**
```json
{
  "subscription_id": 123,
  "payment_failure_count": 1,
  "grace_period_ends_at": "2025-12-03T10:00:00Z",
  "is_in_grace_period": true,
  "has_grace_period_expired": false
}
```

---

## Usage & Metering API

### Get Usage Statistics

Get current usage statistics for the active subscription.

**Endpoint:** `GET /{region}/token-usage/stats`

**Headers:**
```
Authorization: Bearer {token}
```

**Request Example:**
```http
GET /api/v1/local/token-usage/stats
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response (200 OK):**
```json
{
  "total_tokens_used": 5000,
  "total_token_quota": 50000,
  "daily_messages_used": 5,
  "daily_message_limit": 50,
  "ads_enabled": false
}
```

**Error Response (404 Not Found):**
```json
{
  "message": "No active local subscription."
}
```

### Get Quota Status

Get detailed quota status with warnings and percentages.

**Endpoint:** `GET /{region}/token-usage/quota-status`

**Headers:**
```
Authorization: Bearer {token}
```

**Request Example:**
```http
GET /api/v1/local/token-usage/quota-status
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response (200 OK):**
```json
{
  "subscription_id": 123,
  "plan": {
    "id": 2,
    "name": "Pro Plan",
    "max_tokens": 50000,
    "daily_message_limit": 50
  },
  "tokens": {
    "used": 5000,
    "quota": 50000,
    "remaining": 45000,
    "percentage_used": 10.0,
    "percentage_remaining": 90.0
  },
  "messages": {
    "used_today": 5,
    "limit": 50,
    "remaining": 45,
    "percentage_used": 10.0,
    "percentage_remaining": 90.0
  },
  "subscription": {
    "days_remaining": 1,
    "is_active": true,
    "is_expired": false
  },
  "warnings": []
}
```

**Response with Warnings (200 OK):**
```json
{
  "subscription_id": 123,
  "plan": {
    "id": 2,
    "name": "Pro Plan",
    "max_tokens": 50000,
    "daily_message_limit": 50
  },
  "tokens": {
    "used": 45000,
    "quota": 50000,
    "remaining": 5000,
    "percentage_used": 90.0,
    "percentage_remaining": 10.0
  },
  "messages": {
    "used_today": 45,
    "limit": 50,
    "remaining": 5,
    "percentage_used": 90.0,
    "percentage_remaining": 10.0
  },
  "subscription": {
    "days_remaining": 1,
    "is_active": true,
    "is_expired": false
  },
  "warnings": [
    "token_quota_warning",
    "message_limit_warning"
  ]
}
```

### Get Daily Usage

Get daily usage breakdown for the last 30 days.

**Endpoint:** `GET /{region}/token-usage/daily`

**Headers:**
```
Authorization: Bearer {token}
```

**Request Example:**
```http
GET /api/v1/local/token-usage/daily
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response (200 OK):**
```json
{
  "daily_usage": [
    {
      "date": "2025-11-01",
      "tokens_used": 1500,
      "messages_count": 10
    },
    {
      "date": "2025-11-02",
      "tokens_used": 2000,
      "messages_count": 15
    },
    {
      "date": "2025-11-30",
      "tokens_used": 500,
      "messages_count": 5
    }
  ]
}
```

### Get Weekly Usage

Get weekly usage summary for the last 12 weeks.

**Endpoint:** `GET /{region}/token-usage/weekly`

**Headers:**
```
Authorization: Bearer {token}
```

**Request Example:**
```http
GET /api/v1/local/token-usage/weekly
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response (200 OK):**
```json
{
  "weekly_usage": [
    {
      "week_start": "2025-09-01",
      "week_end": "2025-09-07",
      "tokens_used": 10000,
      "messages_count": 50
    },
    {
      "week_start": "2025-11-24",
      "week_end": "2025-11-30",
      "tokens_used": 15000,
      "messages_count": 75
    }
  ]
}
```

### Get Monthly Usage

Get monthly usage summary for the last 12 months.

**Endpoint:** `GET /{region}/token-usage/monthly`

**Headers:**
```
Authorization: Bearer {token}
```

**Request Example:**
```http
GET /api/v1/local/token-usage/monthly
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response (200 OK):**
```json
{
  "monthly_usage": [
    {
      "month": "2025-01",
      "month_name": "January 2025",
      "tokens_used": 50000,
      "messages_count": 250
    },
    {
      "month": "2025-11",
      "month_name": "November 2025",
      "tokens_used": 45000,
      "messages_count": 200
    }
  ]
}
```

### Get Usage Analytics

Get analytics data for charts and graphs.

**Endpoint:** `GET /{region}/token-usage/analytics`

**Headers:**
```
Authorization: Bearer {token}
```

**Request Example:**
```http
GET /api/v1/local/token-usage/analytics
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response (200 OK):**
```json
{
  "tokens_over_time": [
    {
      "date": "2025-11-01",
      "tokens": 1500
    },
    {
      "date": "2025-11-02",
      "tokens": 2000
    },
    {
      "date": "2025-11-30",
      "tokens": 500
    }
  ],
  "peak_hours": [
    {
      "hour": 0,
      "messages_count": 5
    },
    {
      "hour": 9,
      "messages_count": 25
    },
    {
      "hour": 14,
      "messages_count": 30
    },
    {
      "hour": 23,
      "messages_count": 10
    }
  ]
}
```

### Get Usage History

Get paginated list of token usage records.

**Endpoint:** `GET /{region}/token-usage`

**Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
- `date_from` (optional): Start date (YYYY-MM-DD)
- `date_to` (optional): End date (YYYY-MM-DD)
- `page` (optional): Page number
- `per_page` (optional): Items per page (default: 50)

**Request Example:**
```http
GET /api/v1/local/token-usage?date_from=2025-11-01&date_to=2025-11-30&page=1&per_page=20
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response (200 OK):**
```json
{
  "data": [
    {
      "id": 1,
      "user_id": 5,
      "chat_session_id": "abc-123-def-456",
      "subscription_id": 123,
      "engine_id": 1,
      "tokens_used": 150,
      "cost": 0.021,
      "source": "chat",
      "created_at": "2025-11-30T10:00:00.000000Z"
    },
    {
      "id": 2,
      "user_id": 5,
      "chat_session_id": "abc-123-def-456",
      "subscription_id": 123,
      "engine_id": 1,
      "tokens_used": 200,
      "cost": 0.028,
      "source": "chat",
      "created_at": "2025-11-30T10:05:00.000000Z"
    }
  ],
  "current_page": 1,
  "per_page": 20,
  "total": 100,
  "last_page": 5
}
```

### Export Usage Data

Export usage data (CSV format).

**Endpoint:** `GET /{region}/token-usage/export`

**Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
- `date_from` (optional): Start date (YYYY-MM-DD)
- `date_to` (optional): End date (YYYY-MM-DD)
- `format` (optional): Export format (csv, json) - default: csv

**Request Example:**
```http
GET /api/v1/local/token-usage/export?date_from=2025-11-01&date_to=2025-11-30&format=csv
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response (200 OK):**
```
Content-Type: text/csv
Content-Disposition: attachment; filename="usage_export_2025-11-30.csv"

Date,Tokens Used,Cost,Source
2025-11-01,1500,0.21,chat
2025-11-02,2000,0.28,chat
...
```

---

## Payment API

### Create Payment

Create a new payment. Can be used for subscriptions or standalone payments.

**Endpoint:** `POST /{region}/payments/create`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "plan_id": 2,  // Optional: If provided, amount and currency are auto-filled
  "amount": 30,  // Required if plan_id not provided
  "currency": "ETB",  // Required: USD or ETB
  "provider": "chapa",  // Required: stripe or chapa
  "description": "Subscription payment",  // Optional
  "success_url": "https://akili.akmicroservice.com/payment/success",  // Optional
  "cancel_url": "https://akili.akmicroservice.com/payment/cancel",  // Optional
  "return_url": "https://akili.akmicroservice.com/payment/success"  // Optional
}
```

**Request Example:**
```http
POST /api/v1/local/payments/create
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
Content-Type: application/json

{
  "plan_id": 2,
  "provider": "chapa"
}
```

**Response (201 Created):**
```json
{
  "message": "Payment created successfully",
  "payment": {
    "id": 456,
    "reference": "chapa_ref_123456789",
    "amount": 30.00,
    "currency": "ETB",
    "provider": "chapa",
    "status": "pending",
    "checkout_url": "https://checkout.chapa.co/checkout/payment/chapa_ref_123456789",
    "created_at": "2025-11-30T10:00:00.000Z"
  }
}
```

### Get Payment Status

Get the current status of a payment.

**Endpoint:** `GET /{region}/payments/status`

**Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
- `reference` (required): Payment reference

**Request Example:**
```http
GET /api/v1/local/payments/status?reference=chapa_ref_123456789
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response (200 OK):**
```json
{
  "payment": {
    "id": 456,
    "reference": "chapa_ref_123456789",
    "amount": 30.00,
    "currency": "ETB",
    "provider": "chapa",
    "status": "success",
    "transaction_id": "txn_chapa_123456",
    "created_at": "2025-11-30T10:00:00.000Z",
    "updated_at": "2025-11-30T10:05:00.000Z"
  }
}
```

### Verify Payment

Manually verify a payment with the payment gateway.

**Endpoint:** `POST /{region}/payments/verify`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "reference": "chapa_ref_123456789"
}
```

**Request Example:**
```http
POST /api/v1/local/payments/verify
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
Content-Type: application/json

{
  "reference": "chapa_ref_123456789"
}
```

**Response (200 OK):**
```json
{
  "verified": true,
  "payment": {
    "id": 456,
    "reference": "chapa_ref_123456789",
    "status": "success",
    "transaction_id": "txn_chapa_123456"
  }
}
```

### Get Payment History

Get user's payment history with filters.

**Endpoint:** `GET /{region}/payments/history`

**Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
- `status` (optional): Filter by status (pending, success, failed, refunded)
- `provider` (optional): Filter by provider (stripe, chapa)
- `date_from` (optional): Start date (YYYY-MM-DD)
- `date_to` (optional): End date (YYYY-MM-DD)
- `page` (optional): Page number
- `per_page` (optional): Items per page

**Request Example:**
```http
GET /api/v1/local/payments/history?status=success&page=1&per_page=10
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response (200 OK):**
```json
{
  "data": [
    {
      "id": 456,
      "reference": "chapa_ref_123456789",
      "amount": 30.00,
      "currency": "ETB",
      "provider": "chapa",
      "status": "success",
      "transaction_id": "txn_chapa_123456",
      "created_at": "2025-11-30T10:00:00.000Z"
    },
    {
      "id": 455,
      "reference": "chapa_ref_987654321",
      "amount": 60.00,
      "currency": "ETB",
      "provider": "chapa",
      "status": "success",
      "transaction_id": "txn_chapa_654321",
      "created_at": "2025-11-25T10:00:00.000Z"
    }
  ]
}
```

### Get Single Payment

Get details of a specific payment.

**Endpoint:** `GET /{region}/payments/{id}`

**Headers:**
```
Authorization: Bearer {token}
```

**Request Example:**
```http
GET /api/v1/local/payments/456
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response (200 OK):**
```json
{
  "id": 456,
  "reference": "chapa_ref_123456789",
  "transaction_id": "txn_chapa_123456",
  "amount": 30.00,
  "currency": "ETB",
  "provider": "chapa",
  "status": "success",
  "gateway_response": {
    "checkout_url": "https://checkout.chapa.co/checkout/payment/chapa_ref_123456789",
    "status": "success"
  },
  "metadata": {
    "plan_id": 2,
    "description": "Subscription to Pro Plan"
  },
  "created_at": "2025-11-30T10:00:00.000Z",
  "updated_at": "2025-11-30T10:05:00.000Z"
}
```

### Retry Failed Payment

Retry a failed payment.

**Endpoint:** `POST /{region}/payments/{id}/retry`

**Headers:**
```
Authorization: Bearer {token}
```

**Request Example:**
```http
POST /api/v1/local/payments/456/retry
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response (200 OK):**
```json
{
  "message": "Payment retry initiated",
  "payment": {
    "id": 457,
    "reference": "chapa_ref_new_123456",
    "amount": 30.00,
    "currency": "ETB",
    "provider": "chapa",
    "status": "pending",
    "checkout_url": "https://checkout.chapa.co/checkout/payment/chapa_ref_new_123456"
  }
}
```

### Cancel Pending Payment

Cancel a pending payment.

**Endpoint:** `POST /{region}/payments/{id}/cancel`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "reason": "User cancelled"  // Optional
}
```

**Request Example:**
```http
POST /api/v1/local/payments/456/cancel
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
Content-Type: application/json

{
  "reason": "Changed my mind"
}
```

**Response (200 OK):**
```json
{
  "message": "Payment cancelled successfully",
  "payment": {
    "id": 456,
    "status": "cancelled"
  }
}
```

### Request Refund

Request a refund for a successful payment.

**Endpoint:** `POST /{region}/payments/{id}/refund`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "amount": 30.00,  // Optional: Partial refund amount (null = full refund)
  "reason": "Customer requested refund"  // Optional
}
```

**Request Example:**
```http
POST /api/v1/local/payments/456/refund
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
Content-Type: application/json

{
  "amount": 15.00,
  "reason": "Partial refund requested"
}
```

**Response (200 OK):**
```json
{
  "message": "Refund request processed",
  "payment": {
    "id": 456,
    "status": "refunded",
    "refund_amount": 15.00
  }
}
```

### Get Refund Status

Check refund status for a payment.

**Endpoint:** `GET /{region}/payments/{id}/refund-status`

**Headers:**
```
Authorization: Bearer {token}
```

**Request Example:**
```http
GET /api/v1/local/payments/456/refund-status
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response (200 OK):**
```json
{
  "payment_id": 456,
  "is_refunded": true,
  "refund_status": "completed",
  "refund_details": {
    "amount": 15.00,
    "refunded_at": "2025-11-30T11:00:00.000Z",
    "reason": "Partial refund requested"
  }
}
```

### Download Payment Receipt

Download payment receipt (JSON format, can be converted to PDF on frontend).

**Endpoint:** `GET /{region}/payments/{id}/receipt`

**Headers:**
```
Authorization: Bearer {token}
```

**Request Example:**
```http
GET /api/v1/local/payments/456/receipt
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response (200 OK):**
```json
{
  "receipt_number": "RCP-00000456",
  "payment_id": 456,
  "reference": "chapa_ref_123456789",
  "transaction_id": "txn_chapa_123456",
  "amount": 30.00,
  "currency": "ETB",
  "provider": "chapa",
  "date": "2025-11-30T10:00:00.000Z",
  "user": {
    "name": "John Doe",
    "email": "john@example.com"
  }
}
```

### Get Payment Summary

Get payment summary statistics.

**Endpoint:** `GET /{region}/payments/summary`

**Headers:**
```
Authorization: Bearer {token}
```

**Request Example:**
```http
GET /api/v1/local/payments/summary
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response (200 OK):**
```json
{
  "total_spent": 150.00,
  "successful_count": 5,
  "failed_count": 1,
  "pending_count": 0,
  "last_payment_date": "2025-11-30T10:00:00.000Z"
}
```

### Get Upcoming Payments

Get scheduled upcoming payments (for auto-renewal subscriptions).

**Endpoint:** `GET /{region}/payments/upcoming`

**Headers:**
```
Authorization: Bearer {token}
```

**Request Example:**
```http
GET /api/v1/local/payments/upcoming
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response (200 OK):**
```json
{
  "upcoming_payments": [
    {
      "subscription_id": 123,
      "plan_name": "Pro Plan",
      "amount": 30.00,
      "currency": "ETB",
      "payment_date": "2025-12-01T10:00:00.000Z",
      "payment_method": "chapa",
      "days_until_payment": 1
    }
  ]
}
```

---

## Billing API

### Get Payment Methods

Get user's saved payment methods.

**Endpoint:** `GET /{region}/billing/payment-methods`

**Headers:**
```
Authorization: Bearer {token}
```

**Request Example:**
```http
GET /api/v1/local/billing/payment-methods
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response (200 OK):**
```json
{
  "payment_methods": [
    {
      "id": 1,
      "type": "chapa",
      "is_default": true,
      "last_four": null,
      "provider": "chapa"
    }
  ]
}
```

### Add Payment Method

Add a new payment method.

**Endpoint:** `POST /{region}/billing/payment-methods`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "payment_method": "chapa",  // Required: stripe, chapa, or telebirr
  "payment_details": {  // Optional: Additional payment details
    "card_last_four": "1234"
  }
}
```

**Request Example:**
```http
POST /api/v1/local/billing/payment-methods
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
Content-Type: application/json

{
  "payment_method": "chapa"
}
```

**Response (200 OK):**
```json
{
  "message": "Payment method saved successfully",
  "payment_method": "chapa"
}
```

### Remove Payment Method

Remove a payment method.

**Endpoint:** `DELETE /{region}/billing/payment-methods/{id}`

**Headers:**
```
Authorization: Bearer {token}
```

**Request Example:**
```http
DELETE /api/v1/local/billing/payment-methods/1
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response (200 OK):**
```json
{
  "message": "Payment method removed"
}
```

### Set Default Payment Method

Set a payment method as default.

**Endpoint:** `PUT /{region}/billing/payment-methods/{id}/default`

**Headers:**
```
Authorization: Bearer {token}
```

**Request Example:**
```http
PUT /api/v1/local/billing/payment-methods/1/default
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response (200 OK):**
```json
{
  "message": "Default payment method updated"
}
```

---

## Payment Method Selection

### Automatic Payment Method Selection

The system **automatically selects** the payment method based on the user's region:

- **International Users** (`region = 'intl'`):
  - **Payment Method:** `stripe`
  - **Currency:** `USD`
  - **Provider:** Stripe (international payment gateway)

- **Local Users** (`region = 'local'`):
  - **Payment Method:** `chapa`
  - **Currency:** `ETB`
  - **Provider:** Chapa (Ethiopian payment gateway)

### How It Works

1. **User subscribes to a plan:**
   ```http
   POST /api/v1/{region}/subscribe
   {
     "plan_id": 2
     // payment_method is optional - auto-selected based on region
   }
   ```

2. **Backend automatically selects payment method:**
   - Checks user's `region` from database
   - If `region = 'intl'` → Uses `stripe`
   - If `region = 'local'` → Uses `chapa`
   - Falls back to route region if user region not set

3. **Payment initiated:**
   - Payment created with selected provider
   - Checkout URL returned to frontend
   - User redirected to payment gateway

### Manual Override

You can still manually specify the payment method:

```json
{
  "plan_id": 2,
  "payment_method": "stripe"  // Override auto-selection
}
```

**Note:** Manual override must be one of: `stripe`, `chapa`, or `telebirr`

---

## Complete Subscription Flow Example

### Step 1: Get Available Plans

```http
GET /api/v1/local/plans
Authorization: Bearer {token}
```

**Response:**
```json
{
  "region": "local",
  "currency": "ETB",
  "plans": [
    {
      "id": 2,
      "name": "Pro Plan",
      "monthly_price": 30,
      "max_tokens": 50000,
      "daily_message_limit": 50
    }
  ]
}
```

### Step 2: Subscribe to Plan

```http
POST /api/v1/local/subscribe
Authorization: Bearer {token}
Content-Type: application/json

{
  "plan_id": 2
}
```

**Response:**
```json
{
  "payment_method": "chapa",
  "payment_id": 456,
  "reference": "chapa_ref_123456789",
  "checkout_url": "https://checkout.chapa.co/checkout/payment/chapa_ref_123456789",
  "subscription_id": 124,
  "status": "pending"
}
```

### Step 3: User Completes Payment

User is redirected to `checkout_url` and completes payment.

### Step 4: Webhook Processes Payment

Backend receives webhook from payment gateway and activates subscription.

### Step 5: Verify Subscription

```http
GET /api/v1/local/subscription
Authorization: Bearer {token}
```

**Response:**
```json
{
  "id": 124,
  "start_date": "2025-11-30 10:00:00",
  "end_date": "2025-12-30 10:00:00",
  "tokens_used": 0,
  "is_active": true,
  "tokens_available": 50000,
  "days_left": 30,
  "is_expired": false,
  "plan": {
    "name": "Pro Plan",
    "monthly_price": 30,
    "max_tokens": 50000,
    "daily_message_limit": 50
  }
}
```

### Step 6: Check Usage

```http
GET /api/v1/local/token-usage/stats
Authorization: Bearer {token}
```

**Response:**
```json
{
  "total_tokens_used": 0,
  "total_token_quota": 50000,
  "daily_messages_used": 0,
  "daily_message_limit": 50,
  "ads_enabled": false
}
```

---

## Error Responses

### Common Error Codes

**400 Bad Request:**
```json
{
  "message": "Free plan does not require payment."
}
```

**401 Unauthorized:**
```json
{
  "error": "Unauthorized"
}
```

**403 Forbidden:**
```json
{
  "message": "Trial plan already used. Please select a paid plan."
}
```

**404 Not Found:**
```json
{
  "message": "Plan not found."
}
```

**422 Unprocessable Entity:**
```json
{
  "message": "Validation failed",
  "errors": {
    "plan_id": ["The plan id field is required."]
  }
}
```

**500 Internal Server Error:**
```json
{
  "message": "Failed to create payment: {error_message}"
}
```

---

## Best Practices

### 1. Payment Method Selection

- **Don't send `payment_method`** - Let the backend auto-select based on region
- **Only override** if you have a specific requirement
- **Use Stripe for international** users (better support, lower fees)
- **Use Chapa for local** users (better local payment options)

### 2. Subscription Management

- Always check subscription status before allowing premium features
- Use `GET /{region}/subscription` to get current subscription
- Check `is_active` and `is_expired` flags
- Monitor `tokens_available` to show quota warnings

### 3. Usage Tracking

- Poll `GET /{region}/token-usage/quota-status` regularly to show quota
- Display warnings when `percentage_used >= 80%`
- Block usage when `percentage_used >= 100%`
- Show daily message limit progress

### 4. Payment Handling

- Store `payment.reference` for status checking
- Poll `GET /{region}/payments/status?reference={ref}` after redirect
- Handle webhook callbacks (backend processes automatically)
- Show payment status in UI (pending, success, failed)

### 5. Error Handling

- Always check for `422` errors (validation failures)
- Handle `403` errors (trial already used, region mismatch)
- Show user-friendly error messages
- Retry failed payments using `POST /{region}/payments/{id}/retry`

---

## Frontend Integration Examples

### React/Next.js Example

```typescript
// Subscribe to a plan
async function subscribeToPlan(planId: number) {
  const token = localStorage.getItem('auth_token');
  const region = getUserRegion(); // 'local' or 'intl'
  
  const response = await fetch(
    `https://chat.akmicroservice.com/api/v1/${region}/subscribe`,
    {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        plan_id: planId,
        // payment_method is optional - auto-selected
      }),
    }
  );
  
  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.message || 'Subscription failed');
  }
  
  const data = await response.json();
  
  // If paid plan, redirect to checkout
  if (data.checkout_url) {
    window.location.href = data.checkout_url;
  } else {
    // Free plan - subscription activated
    console.log('Subscription activated:', data.subscription);
  }
  
  return data;
}

// Check subscription status
async function getCurrentSubscription() {
  const token = localStorage.getItem('auth_token');
  const region = getUserRegion();
  
  const response = await fetch(
    `https://chat.akmicroservice.com/api/v1/${region}/subscription`,
    {
      headers: {
        'Authorization': `Bearer ${token}`,
      },
    }
  );
  
  return await response.json();
}

// Get usage stats
async function getUsageStats() {
  const token = localStorage.getItem('auth_token');
  const region = getUserRegion();
  
  const response = await fetch(
    `https://chat.akmicroservice.com/api/v1/${region}/token-usage/stats`,
    {
      headers: {
        'Authorization': `Bearer ${token}`,
      },
    }
  );
  
  return await response.json();
}

// Check quota status
async function getQuotaStatus() {
  const token = localStorage.getItem('auth_token');
  const region = getUserRegion();
  
  const response = await fetch(
    `https://chat.akmicroservice.com/api/v1/${region}/token-usage/quota-status`,
    {
      headers: {
        'Authorization': `Bearer ${token}`,
      },
    }
  );
  
  return await response.json();
}
```

### JavaScript/Vanilla Example

```javascript
// Subscribe to plan
async function subscribe(planId) {
  const token = localStorage.getItem('auth_token');
  const region = 'local'; // or 'intl'
  
  try {
    const response = await fetch(
      `https://chat.akmicroservice.com/api/v1/${region}/subscribe`,
      {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ plan_id: planId }),
      }
    );
    
    const data = await response.json();
    
    if (!response.ok) {
      throw new Error(data.message || 'Subscription failed');
    }
    
    // Redirect to payment if checkout_url exists
    if (data.checkout_url) {
      window.location.href = data.checkout_url;
    }
    
    return data;
  } catch (error) {
    console.error('Subscription error:', error);
    throw error;
  }
}

// Get usage quota
async function getQuota() {
  const token = localStorage.getItem('auth_token');
  const region = 'local';
  
  const response = await fetch(
    `https://chat.akmicroservice.com/api/v1/${region}/token-usage/quota-status`,
    {
      headers: {
        'Authorization': `Bearer ${token}`,
      },
    }
  );
  
  return await response.json();
}
```

---

## Payment Gateway Integration

### Stripe (International Users)

**Auto-selected for:** `region = 'intl'`

**Features:**
- Credit/debit cards
- International payments
- Multiple currencies (USD, EUR, etc.)
- Webhook support

**Checkout Flow:**
1. User subscribes → Payment created
2. Redirect to Stripe Checkout
3. User completes payment
4. Stripe webhook → Subscription activated

### Chapa (Local Users)

**Auto-selected for:** `region = 'local'`

**Features:**
- Mobile money (M-Pesa, etc.)
- Bank transfers
- Local payment methods
- ETB currency

**Checkout Flow:**
1. User subscribes → Payment created
2. Redirect to Chapa Checkout
3. User completes payment
4. Chapa webhook → Subscription activated

---

## Usage Metering Details

### Token Usage Tracking

**How tokens are counted:**
- Each chat message counts tokens used
- Tokens include: prompt tokens + response tokens
- Tracked in `TokenUsage` table
- Atomically incremented in `subscription.tokens_used`

**Token calculation:**
- Based on AI engine pricing
- Stored per request in `TokenUsage` records
- Aggregated in subscription `tokens_used` field

### Daily Message Limit

**How messages are counted:**
- Only `user` role messages count (not assistant responses)
- Counted per day (resets at midnight)
- Tracked in `ChatMessage` table
- Quota checked before each chat request

**Message limit enforcement:**
- Soft limit: Warning at 80%
- Hard limit: Blocked at 100%
- Resets daily at midnight (user's timezone)

### Usage Quota Checks

**When quota is checked:**
1. **Before each chat request** (middleware + controller)
2. **Subscription check:** Active and not expired
3. **Token quota check:** `tokens_used < max_tokens`
4. **Daily limit check:** `messages_today < daily_message_limit`

**Quota exceeded behavior:**
- Returns error message
- Blocks chat request
- Suggests plan upgrade

---

## Subscription States

### Active Subscription
- `is_active = true`
- `end_date >= now()`
- User can use service
- Quota checks apply

### Pending Subscription
- `is_active = false`
- Payment not completed
- User cannot use service
- Waiting for payment

### Expired Subscription
- `is_active = true` (may be false)
- `end_date < now()`
- User cannot use service
- Needs renewal

### Grace Period
- `is_active = true`
- `grace_period_ends_at > now()`
- Payment failed but still active
- User can use service temporarily
- Will downgrade after grace period

---

## Webhook Processing

### Payment Webhooks

**Stripe Webhook:**
- Endpoint: `POST /api/v1/payments/webhook/stripe`
- Events: `payment_intent.succeeded`, `payment_intent.payment_failed`
- Automatically processes and activates subscription

**Chapa Webhook:**
- Endpoint: `POST /api/v1/payments/webhook/chapa`
- Events: `charge.success`, `charge.failure`
- Automatically processes and activates subscription

**Webhook Flow:**
1. Payment gateway sends webhook
2. Backend verifies signature
3. Payment status updated
4. `PaymentSucceeded` event fired
5. Subscription activated automatically
6. User can now use service

---

## Notes

1. **Region-based routing:** All endpoints use `/{region}/` prefix (`local` or `intl`)
2. **Auto payment selection:** Payment method is automatically selected based on user's region
3. **One active subscription:** Users can only have one active subscription at a time
4. **Token tracking:** Tokens are tracked atomically to prevent race conditions
5. **Grace period:** 3-day grace period on payment failure before downgrade
6. **Webhook idempotency:** Webhooks are processed only once (duplicate prevention)
7. **CORS headers:** All responses include CORS headers automatically

---

## Support

For issues or questions:
- Check error messages in API responses
- Review logs for detailed error information
- Ensure payment gateway credentials are configured
- Verify user region matches subscription region

