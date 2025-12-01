# Payment API Documentation

## Base URL

```
https://chat.akmicroservice.com/api/v1/{region}
```

**Regions:**
- `local` - For local/Ethiopian region (uses ETB currency, Chapa payments)
- `intl` - For international region (uses USD currency, Stripe payments)

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

## Client Payment Endpoints

### 1. Create Payment

Initiate a new payment for a subscription or one-time payment.

**Endpoint:** `POST /{region}/payments/create`

**Request Body:**
```json
{
  "plan_id": 1,                    // Optional: Plan ID for subscription payment
  "amount": 100.00,                // Required if no plan_id: Payment amount
  "currency": "USD",               // Required: Currency code (USD, ETB)
  "provider": "stripe",            // Required: Payment provider (stripe, chapa)
  "description": "Optional description",
  "success_url": "https://...",    // Optional: Custom success redirect URL
  "cancel_url": "https://...",     // Optional: Custom cancel redirect URL
  "return_url": "https://..."      // Optional: Custom return redirect URL
}
```

**Field Details:**
- `plan_id`: If provided, amount and currency are automatically set from the plan
- `amount`: Required only if `plan_id` is not provided. Minimum: 0.01
- `currency`: Must be `USD` (for Stripe) or `ETB` (for Chapa)
- `provider`: Must be `stripe` (for USD) or `chapa` (for ETB)
- `success_url`, `cancel_url`, `return_url`: Optional frontend URLs for redirects after payment

**Example Requests:**

**Subscription Payment (Stripe):**
```json
{
  "plan_id": 5,
  "currency": "USD",
  "provider": "stripe"
}
```

**Subscription Payment (Chapa):**
```json
{
  "plan_id": 1,
  "currency": "ETB",
  "provider": "chapa"
}
```

**One-time Payment:**
```json
{
  "amount": 50.00,
  "currency": "ETB",
  "provider": "chapa",
  "description": "Top-up payment"
}
```

**Success Response (201):**
```json
{
  "message": "Payment created successfully",
  "payment": {
    "id": 123,
    "reference": "chapa_692957025da77_1764316930",
    "amount": 30.00,
    "currency": "ETB",
    "provider": "chapa",
    "status": "pending",
    "checkout_url": "https://checkout.chapa.co/checkout/payment/...",
    "created_at": "2025-11-28T08:02:10.000000Z"
  }
}
```

**Response Fields:**
- `id`: Internal payment ID
- `reference`: Unique payment reference (use this for status checks)
- `amount`: Payment amount
- `currency`: Payment currency
- `provider`: Payment provider used
- `status`: Payment status (`pending`, `success`, `failed`, `refunded`)
- `checkout_url`: URL to redirect user for payment (Stripe/Chapa checkout page)
- `created_at`: Payment creation timestamp

**Error Responses:**

**422 Validation Error:**
```json
{
  "message": "Validation failed",
  "errors": {
    "provider": ["The provider field is required."],
    "currency": ["The currency must be USD or ETB."]
  }
}
```

**400 Bad Request:**
```json
{
  "message": "Free plan does not require payment."
}
```

**500 Server Error:**
```json
{
  "message": "Failed to create payment: {error_message}"
}
```

**How It Works:**
1. Validates request data (plan_id or amount, currency, provider)
2. If `plan_id` provided, fetches plan and uses its price/currency
3. Creates payment record in database with status `pending`
4. Calls payment provider API (Stripe/Chapa) to create checkout session
5. Returns payment details including `checkout_url`
6. Frontend redirects user to `checkout_url` to complete payment
7. After payment, user is redirected back (webhook also processes payment)

---

### 2. Get Payment Status

Retrieve the current status of a payment by reference.

**Endpoint:** `GET /{region}/payments/status?reference={payment_reference}`

**Query Parameters:**
- `reference` (required) - Payment reference ID from create payment response

**Example Request:**
```
GET /api/v1/local/payments/status?reference=chapa_692957025da77_1764316930
```

**Success Response (200):**
```json
{
  "payment": {
    "id": 123,
    "reference": "chapa_692957025da77_1764316930",
    "amount": 30.00,
    "currency": "ETB",
    "provider": "chapa",
    "status": "success",
    "transaction_id": "APYDH1YwJOlKU",
    "created_at": "2025-11-28T08:02:10.000000Z",
    "updated_at": "2025-11-28T08:02:17.000000Z"
  }
}
```

**Payment Status Values:**
- `pending` - Payment is pending user action (user hasn't completed checkout)
- `success` - Payment completed successfully (subscription created automatically)
- `failed` - Payment failed or was cancelled
- `refunded` - Payment was refunded

**Error Responses:**

**422 Validation Error:**
```json
{
  "message": "Validation failed",
  "errors": {
    "reference": ["The reference field is required."]
  }
}
```

**404 Not Found:**
```json
{
  "message": "Payment not found"
}
```

**403 Forbidden:**
```json
{
  "message": "Unauthorized access to payment"
}
```

**500 Server Error:**
```json
{
  "message": "Failed to get payment status: {error_message}"
}
```

**How It Works:**
1. Validates that `reference` parameter is provided
2. Looks up payment by reference in database
3. Verifies user owns the payment (security check)
4. Returns current payment status from database
5. Note: Status is updated via webhooks automatically, but this endpoint shows current DB state

---

### 3. Verify Payment

Manually verify a payment with the payment gateway and update its status.

**Endpoint:** `POST /{region}/payments/verify`

**Request Body:**
```json
{
  "reference": "chapa_692957025da77_1764316930",  // Required: Payment reference
  "provider": "chapa"                              // Optional: Payment provider
}
```

**Example Request:**
```json
{
  "reference": "chapa_692957025da77_1764316930"
}
```

**Success Response (200):**
```json
{
  "verified": true,
  "payment": {
    "id": 123,
    "reference": "chapa_692957025da77_1764316930",
    "status": "success",
    "transaction_id": "APYDH1YwJOlKU"
  }
}
```

**If Payment Still Pending:**
```json
{
  "verified": false,
  "payment": {
    "id": 123,
    "reference": "chapa_692957025da77_1764316930",
    "status": "pending",
    "transaction_id": null
  }
}
```

**Error Responses:**

**422 Validation Error:**
```json
{
  "message": "Validation failed",
  "errors": {
    "reference": ["The reference field is required."]
  }
}
```

**404 Not Found:**
```json
{
  "message": "Payment not found"
}
```

**403 Forbidden:**
```json
{
  "message": "Unauthorized access to payment"
}
```

**500 Server Error:**
```json
{
  "message": "Verification failed: {error_message}"
}
```

**How It Works:**
1. Validates that `reference` is provided
2. Looks up payment in database
3. Verifies user owns the payment
4. Calls payment provider API (Stripe/Chapa) to check payment status
5. Updates payment status in database if changed
6. If status changes to `success`, fires `PaymentSucceeded` event (creates subscription automatically)
7. Returns verification result

**When to Use:**
- After user returns from payment gateway (redirect)
- If webhook hasn't processed yet (manual verification)
- To get real-time payment status from gateway

---

### 4. Get Payment History

Retrieve all payments for the authenticated user with optional filtering.

**Endpoint:** `GET /{region}/payments/history`

**Query Parameters (All Optional):**
- `status` - Filter by status (`pending`, `success`, `failed`, `refunded`)
- `provider` - Filter by provider (`stripe`, `chapa`)
- `date_from` - Filter from date (format: `YYYY-MM-DD`)
- `date_to` - Filter to date (format: `YYYY-MM-DD`)

**Example Requests:**
```
GET /api/v1/local/payments/history
GET /api/v1/local/payments/history?status=success
GET /api/v1/local/payments/history?provider=chapa&status=success
GET /api/v1/local/payments/history?date_from=2025-11-01&date_to=2025-11-30
GET /api/v1/local/payments/history?status=success&provider=stripe&date_from=2025-11-01
```

**Success Response (200):**
```json
{
  "data": [
    {
      "id": 123,
      "reference": "chapa_692957025da77_1764316930",
      "amount": 30.00,
      "currency": "ETB",
      "provider": "chapa",
      "status": "success",
      "transaction_id": "APYDH1YwJOlKU",
      "created_at": "2025-11-28T08:02:10.000000Z"
    },
    {
      "id": 122,
      "reference": "stripe_session_xyz123",
      "amount": 100.00,
      "currency": "USD",
      "provider": "stripe",
      "status": "pending",
      "transaction_id": null,
      "created_at": "2025-11-27T09:30:00.000000Z"
    }
  ]
}
```

**Response Fields:**
- `data`: Array of payment objects
- Each payment includes: id, reference, amount, currency, provider, status, transaction_id, created_at
- Results are ordered by most recent first

**Error Response (500):**
```json
{
  "message": "Failed to retrieve payment history: {error_message}"
}
```

**How It Works:**
1. Gets authenticated user from JWT token
2. Queries all payments for that user
3. Applies optional filters (status, provider, date range)
4. Returns paginated list of payments (most recent first)
5. Only returns payments owned by the authenticated user

---

## Complete Payment Flow

### Flow Diagram

```
1. User selects plan → Frontend calls POST /payments/create
2. Backend creates payment → Returns checkout_url
3. Frontend redirects user → User completes payment on Stripe/Chapa
4. Payment gateway redirects → User returns to frontend success page
5. Frontend verifies payment → Calls POST /payments/verify
6. Backend processes webhook → Updates payment status automatically
7. Subscription created → If payment successful, subscription activated
```

### Step-by-Step Flow

#### Step 1: Create Payment
```javascript
// Frontend: Create payment
const response = await fetch('/api/v1/local/payments/create', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    plan_id: 1,
    currency: 'ETB',
    provider: 'chapa'
  })
});

const { payment } = await response.json();
// payment.reference = "chapa_692957025da77_1764316930"
// payment.checkout_url = "https://checkout.chapa.co/..."
```

#### Step 2: Redirect to Checkout
```javascript
// Frontend: Redirect user to payment gateway
if (payment.checkout_url) {
  window.location.href = payment.checkout_url;
}
// User completes payment on Chapa/Stripe
```

#### Step 3: Handle Return Redirect
```javascript
// Frontend: On payment success page (/payment/success)
// Option 1: Check URL params
const urlParams = new URLSearchParams(window.location.search);
const txRef = urlParams.get('tx_ref');

// Option 2: If no params, get recent payment
if (!txRef) {
  const historyResponse = await fetch('/api/v1/local/payments/history?status=success&limit=1', {
    headers: { 'Authorization': `Bearer ${token}` }
  });
  const { data } = await historyResponse.json();
  txRef = data[0]?.reference;
}
```

#### Step 4: Verify Payment
```javascript
// Frontend: Verify payment status
const verifyResponse = await fetch('/api/v1/local/payments/verify', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    reference: txRef
  })
});

const { verified, payment: updatedPayment } = await verifyResponse.json();

if (verified && updatedPayment.status === 'success') {
  // Payment successful, subscription activated
  console.log('Subscription activated!');
  // Redirect to dashboard or show success message
} else if (updatedPayment.status === 'pending') {
  // Payment still processing, show loading state
  console.log('Payment is being processed...');
} else {
  // Payment failed
  console.log('Payment failed');
}
```

---

## Payment Flow Examples

### Example 1: Complete Subscription Payment Flow

```javascript
async function subscribeToPlan(planId, provider = 'chapa') {
  try {
    // Step 1: Create payment
    const createResponse = await fetch('/api/v1/local/payments/create', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        plan_id: planId,
        currency: 'ETB',
        provider: provider
      })
    });

    if (!createResponse.ok) {
      const error = await createResponse.json();
      throw new Error(error.message);
    }

    const { payment } = await createResponse.json();

    // Step 2: Redirect to checkout
    if (payment.checkout_url) {
      window.location.href = payment.checkout_url;
    } else {
      throw new Error('No checkout URL received');
    }
  } catch (error) {
    console.error('Payment creation failed:', error);
    alert('Failed to create payment: ' + error.message);
  }
}
```

### Example 2: Polling Payment Status

```javascript
async function checkPaymentStatus(reference, maxAttempts = 30) {
  const interval = 2000; // 2 seconds
  
  for (let i = 0; i < maxAttempts; i++) {
    try {
      const response = await fetch(
        `/api/v1/local/payments/status?reference=${reference}`,
        {
          headers: {
            'Authorization': `Bearer ${token}`
          }
        }
      );
      
      if (!response.ok) {
        throw new Error('Status check failed');
      }
      
      const { payment } = await response.json();
      
      if (payment.status === 'success') {
        return { success: true, payment };
      }
      
      if (payment.status === 'failed') {
        return { success: false, payment, message: 'Payment failed' };
      }
      
      // Wait before next check
      await new Promise(resolve => setTimeout(resolve, interval));
    } catch (error) {
      console.error('Status check error:', error);
      await new Promise(resolve => setTimeout(resolve, interval));
    }
  }
  
  return { success: false, message: 'Payment check timeout' };
}

// Usage
const result = await checkPaymentStatus('chapa_692957025da77_1764316930');
if (result.success) {
  console.log('Payment successful!');
}
```

### Example 3: Display Payment History

```javascript
async function getPaymentHistory(filters = {}) {
  try {
    const queryParams = new URLSearchParams(filters);
    const response = await fetch(
      `/api/v1/local/payments/history?${queryParams}`,
      {
        headers: {
          'Authorization': `Bearer ${token}`
        }
      }
    );
    
    if (!response.ok) {
      throw new Error('Failed to fetch payment history');
    }
    
    const { data } = await response.json();
    return data;
  } catch (error) {
    console.error('Payment history error:', error);
    return [];
  }
}

// Usage examples
const allPayments = await getPaymentHistory();
const successfulPayments = await getPaymentHistory({ status: 'success' });
const chapaPayments = await getPaymentHistory({ provider: 'chapa' });
const recentPayments = await getPaymentHistory({ 
  status: 'success',
  date_from: '2025-11-01',
  date_to: '2025-11-30'
});
```

### Example 4: Payment Success Page Handler

```javascript
// On /payment/success page
async function handlePaymentSuccess() {
  // Get tx_ref from URL or recent payment
  const urlParams = new URLSearchParams(window.location.search);
  let txRef = urlParams.get('tx_ref');
  
  // If no tx_ref in URL, get most recent successful payment
  if (!txRef) {
    const history = await getPaymentHistory({ status: 'success' });
    if (history.length > 0) {
      txRef = history[0].reference;
    } else {
      // No payment found, show error
      showError('Payment reference not found');
      return;
    }
  }
  
  // Verify payment
  try {
    const verifyResponse = await fetch('/api/v1/local/payments/verify', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ reference: txRef })
    });
    
    const { verified, payment } = await verifyResponse.json();
    
    if (verified && payment.status === 'success') {
      showSuccess('Payment successful! Your subscription is now active.');
      // Redirect to dashboard after 3 seconds
      setTimeout(() => {
        window.location.href = '/dashboard';
      }, 3000);
    } else if (payment.status === 'pending') {
      showInfo('Payment is being processed. Please wait...');
      // Retry verification after 5 seconds
      setTimeout(() => handlePaymentSuccess(), 5000);
    } else {
      showError('Payment verification failed');
    }
  } catch (error) {
    console.error('Verification error:', error);
    showError('Failed to verify payment');
  }
}

// Call on page load
handlePaymentSuccess();
```

---

## Payment Providers

### Stripe
- **Supported Currencies:** USD, EUR, and other Stripe-supported currencies
- **Payment Methods:** Credit cards, debit cards
- **Checkout:** Redirects to Stripe Checkout page
- **Webhook:** Automatically updates payment status via `payment_intent.succeeded` event
- **Return URL:** User redirected to `success_url` with `session_id` parameter
- **Best For:** International users, USD payments

### Chapa
- **Supported Currencies:** ETB (Ethiopian Birr)
- **Payment Methods:** Mobile money, bank transfer, etc.
- **Checkout:** Redirects to Chapa payment page
- **Webhook:** Automatically updates payment status via `charge.success` event
- **Return URL:** User redirected to backend endpoint, then to frontend with `tx_ref` parameter
- **Best For:** Ethiopian users, ETB payments

---

## Error Handling

All endpoints return standard HTTP status codes:

- **200** - Success
- **201** - Created (payment created)
- **400** - Bad Request
- **401** - Unauthorized (invalid/missing token)
- **403** - Forbidden (access denied - user doesn't own payment)
- **404** - Not Found (payment not found)
- **422** - Validation Error (invalid request data)
- **500** - Server Error

**Error Response Format:**
```json
{
  "message": "Error description",
  "errors": {
    "field_name": ["Error message"]
  }
}
```

**Common Errors:**

1. **401 Unauthorized:**
   - Token expired or invalid
   - Solution: Re-login to get new token

2. **403 Forbidden:**
   - User trying to access another user's payment
   - Solution: Use correct payment reference for authenticated user

3. **422 Validation Error:**
   - Missing required fields or invalid values
   - Solution: Check request body matches API requirements

4. **404 Not Found:**
   - Payment reference doesn't exist
   - Solution: Verify reference is correct

5. **500 Server Error:**
   - Backend error (payment gateway issue, database error, etc.)
   - Solution: Check logs, retry request, or contact support

---

## Important Notes

### 1. CORS Headers
All responses include CORS headers automatically, so frontend can make requests from any domain.

### 2. Payment References
- Each payment has a unique `reference` (e.g., `chapa_692957025da77_1764316930`)
- Use this reference for all status checks and verification
- References are unique across all payments

### 3. Automatic Subscription Creation
- When a payment with `plan_id` succeeds, a subscription is automatically created
- This happens via webhook processing (automatic) or manual verification
- No need to call subscription API separately

### 4. Webhook Processing
- Payment status is automatically updated via webhooks from Stripe/Chapa
- Webhooks are processed asynchronously (may take a few seconds)
- Manual verification is recommended for immediate user feedback

### 5. Payment Status Flow
```
pending → success (via webhook/verification)
pending → failed (if user cancels or payment fails)
success → refunded (if refunded by admin)
```

### 6. Idempotency
- Creating multiple payments with the same parameters creates separate payment records
- Each payment has a unique reference
- No duplicate prevention - each request creates a new payment

### 7. Currency and Provider Matching
- **USD** payments must use `provider: "stripe"`
- **ETB** payments must use `provider: "chapa"`
- Mismatched currency/provider will cause validation errors

### 8. Free Plans
- Plans with `monthly_price = 0` don't require payment
- API returns 400 error if trying to pay for free plan
- Free plans are assigned automatically on user registration

### 9. Payment Redirect URLs
- `success_url`: Where user goes after successful payment (Stripe)
- `cancel_url`: Where user goes if payment is cancelled (Stripe)
- `return_url`: Where user goes after payment (Chapa)
- If not provided, defaults to `https://akili.akmicroservice.com/payment/success`

### 10. Frontend Success Page Handling
- Check for `tx_ref` parameter in URL first
- If not found, fetch recent payment history
- Always verify payment status after redirect
- Show loading state while verifying
- Handle pending/failed states gracefully

---

## Support

For issues or questions:
- Check payment status via `/payments/status` endpoint
- Review payment history via `/payments/history` endpoint
- Contact backend team or refer to admin panel for payment logs
- Check webhook logs in admin panel for webhook processing issues

---

## Quick Reference

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/payments/create` | POST | Create new payment |
| `/payments/status` | GET | Get payment status |
| `/payments/verify` | POST | Verify payment with gateway |
| `/payments/history` | GET | Get user's payment history |

**Base URL:** `https://chat.akmicroservice.com/api/v1/{local|intl}`

**Authentication:** `Authorization: Bearer {jwt_token}`
