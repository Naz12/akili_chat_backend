# Stripe Subscription Test Guide

## Test User Setup

**User:** Dagu Tester
- **Email:** dagu@example.com
- **User ID:** 1
- **Region:** intl
- **Status:** Ready for testing

**Plan to Test:** Next (ID: 6)
- **Price:** 300 USD/month
- **Billing Cycle:** monthly

---

## Step 1: Subscribe to Plan

### API Request

```bash
curl -X POST https://chat.akmicroservice.com/api/v1/intl/subscribe \
  -H "Authorization: Bearer {YOUR_JWT_TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "plan_id": 6
  }'
```

**Expected Response:**
```json
{
  "payment_method": "stripe",
  "payment_id": 123,
  "reference": "cs_test_...",
  "checkout_url": "https://checkout.stripe.com/c/pay/cs_test_...",
  "subscription_id": 456,
  "status": "pending"
}
```

**What Happens:**
1. Subscription record created (inactive, pending payment)
2. Stripe Customer created/retrieved
3. Stripe Subscription checkout session created
4. Checkout URL returned

---

## Step 2: Complete Payment with Stripe Test Card

### Open Checkout URL

Open the `checkout_url` from the response in your browser.

### Use Stripe Test Cards

#### Successful Payment:
- **Card Number:** `4242 4242 4242 4242`
- **Expiry:** Any future date (e.g., `12/25`)
- **CVC:** Any 3 digits (e.g., `123`)
- **ZIP:** Any 5 digits (e.g., `12345`)

#### Payment Failure (for testing failure scenarios):
- **Card Number:** `4000 0000 0000 0002` (Declined card)
- **Expiry:** Any future date
- **CVC:** Any 3 digits
- **ZIP:** Any 5 digits

### Complete Checkout

1. Enter test card details
2. Click "Pay" or "Subscribe"
3. Stripe will process the payment
4. You'll be redirected to success URL

---

## Step 3: Verify Webhook Processing

### Check Webhook Logs

```bash
# Check Laravel logs
tail -f storage/logs/laravel.log | grep -i stripe
```

**Expected Log Entries:**
```
✅ Stripe webhook received
✅ Stripe subscription ID extracted from checkout session
✅ Payment activated and subscription event fired
✅ Subscription created from payment
✅ Stripe subscription ID stored in subscription metadata
```

### Verify Database

```sql
-- Check payment record
SELECT id, reference, status, metadata 
FROM payments 
WHERE user_id = 1 
ORDER BY created_at DESC 
LIMIT 1;

-- Check subscription record
SELECT id, is_active, metadata 
FROM subscriptions 
WHERE user_id = 1 
ORDER BY created_at DESC 
LIMIT 1;
```

**Expected Results:**
- Payment status: `success`
- Payment metadata contains: `stripe_subscription_id: "sub_..."`
- Subscription is_active: `true`
- Subscription metadata contains: `stripe_subscription_id: "sub_..."`

---

## Step 4: Test Automatic Renewal (Simulate)

### Option A: Use Stripe Dashboard

1. Go to Stripe Dashboard → Subscriptions
2. Find the subscription (search by customer email: dagu@example.com)
3. Click on the subscription
4. Click "Actions" → "Create invoice"
5. This will trigger `invoice.payment_succeeded` webhook

### Option B: Simulate via Webhook

```bash
# Use Stripe CLI to trigger webhook
stripe trigger invoice.payment_succeeded \
  --override subscription=sub_XXXXX
```

### Verify Renewal

After webhook processes:
- Old subscription should be deactivated
- New subscription should be created
- New subscription should have same `stripe_subscription_id`
- New subscription should have `renewed_from_subscription_id` in metadata

---

## Step 5: Test Payment Failure

### Simulate Payment Failure

1. Go to Stripe Dashboard → Subscriptions
2. Find the subscription
3. Click "Actions" → "Create invoice"
4. Then manually fail the payment
5. Or use Stripe CLI:

```bash
stripe trigger invoice.payment_failed \
  --override subscription=sub_XXXXX
```

### Verify Failure Handling

**Expected Results:**
- Bill created with type: `renewal_failed`
- Bill status: `pending`
- Subscription grace_period_ends_at set (3 days from now)
- Payment failure count incremented

**Check Database:**
```sql
-- Check bills
SELECT id, type, status, amount, due_date 
FROM bills 
WHERE user_id = 1 
ORDER BY created_at DESC 
LIMIT 1;

-- Check subscription grace period
SELECT id, grace_period_ends_at, payment_failure_count 
FROM subscriptions 
WHERE user_id = 1 
AND is_active = true;
```

---

## Step 6: Test Manual Payment of Failed Bill

### Get Pending Bills

```bash
curl -X GET https://chat.akmicroservice.com/api/v1/intl/bills/pending \
  -H "Authorization: Bearer {YOUR_JWT_TOKEN}"
```

**Expected Response:**
```json
{
  "pending_bills": [
    {
      "id": 10,
      "type": "renewal_failed",
      "status": "pending",
      "amount": 300,
      "currency": "USD",
      "due_date": "2025-12-03T...",
      "description": "Payment failed for Next. Please pay manually to continue.",
      "plan_name": "Next",
      "is_overdue": false,
      "can_pay": true,
      "days_until_due": 2
    }
  ]
}
```

### Pay the Bill

```bash
curl -X POST https://chat.akmicroservice.com/api/v1/intl/bills/10/pay \
  -H "Authorization: Bearer {YOUR_JWT_TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "payment_method": "stripe"
  }'
```

**Expected Response:**
```json
{
  "message": "Payment initiated successfully",
  "payment_id": 125,
  "payment_reference": "cs_test_...",
  "checkout_url": "https://checkout.stripe.com/c/pay/cs_test_...",
  "bill_id": 10
}
```

### Complete Payment

1. Open checkout URL
2. Use test card: `4242 4242 4242 4242`
3. Complete payment
4. Verify bill is marked as paid
5. Verify subscription is renewed

---

## Verification Checklist

### ✅ Initial Subscription
- [ ] Stripe Customer created
- [ ] Subscription checkout session created
- [ ] Checkout URL returned
- [ ] Payment completed successfully
- [ ] `checkout.session.completed` webhook received
- [ ] `stripe_subscription_id` extracted from webhook
- [ ] Payment metadata contains `stripe_subscription_id`
- [ ] Subscription created and activated
- [ ] Subscription metadata contains `stripe_subscription_id`

### ✅ Automatic Renewal
- [ ] `invoice.payment_succeeded` webhook received
- [ ] Old subscription deactivated
- [ ] New subscription created
- [ ] New subscription has same `stripe_subscription_id`
- [ ] New subscription has `renewed_from_subscription_id` in metadata

### ✅ Payment Failure
- [ ] `invoice.payment_failed` webhook received
- [ ] Bill created (type: `renewal_failed`)
- [ ] Grace period set (3 days)
- [ ] Payment failure count incremented
- [ ] Bill appears in pending bills API

### ✅ Manual Payment of Failed Bill
- [ ] Bill payment initiated
- [ ] Checkout URL returned
- [ ] Payment completed
- [ ] Bill marked as paid
- [ ] Subscription renewed

---

## Stripe Test Cards Reference

### Successful Payments
- `4242 4242 4242 4242` - Visa (success)
- `5555 5555 5555 4444` - Mastercard (success)
- `3782 822463 10005` - American Express (success)

### Declined Cards
- `4000 0000 0000 0002` - Card declined
- `4000 0000 0000 9995` - Insufficient funds
- `4000 0000 0000 0069` - Expired card

### 3D Secure
- `4000 0027 6000 3184` - Requires authentication
- `4000 0025 0000 3155` - Authentication failed

---

## Troubleshooting

### Webhook Not Received

1. **Check Stripe Dashboard:**
   - Go to Developers → Webhooks
   - Check webhook endpoint is active
   - Check events are subscribed:
     - `checkout.session.completed`
     - `invoice.payment_succeeded`
     - `invoice.payment_failed`

2. **Check Webhook Logs:**
   ```bash
   tail -f storage/logs/laravel.log | grep -i webhook
   ```

3. **Verify Webhook Secret:**
   - Check `.env` file: `STRIPE_WEBHOOK_SECRET=whsec_...`
   - Must match Stripe Dashboard webhook secret

### Subscription ID Not Stored

1. **Check Payment Metadata:**
   ```sql
   SELECT metadata FROM payments WHERE id = {payment_id};
   ```
   Should contain: `"stripe_subscription_id": "sub_..."`

2. **Check Subscription Metadata:**
   ```sql
   SELECT metadata FROM subscriptions WHERE id = {subscription_id};
   ```
   Should contain: `"stripe_subscription_id": "sub_..."`

3. **Check Logs:**
   ```bash
   grep -i "stripe_subscription_id" storage/logs/laravel.log
   ```

### Payment Not Processing

1. **Check Payment Status:**
   ```sql
   SELECT status, gateway_response FROM payments WHERE id = {payment_id};
   ```

2. **Check Webhook Processing:**
   ```sql
   SELECT processed, processed_at FROM webhooks WHERE webhook_id = '{webhook_id}';
   ```

3. **Manually Trigger Event:**
   ```php
   // In tinker
   $payment = Payment::find({payment_id});
   event(new \App\Events\PaymentSucceeded($payment));
   ```

---

## Next Steps After Testing

1. ✅ Verify all webhook events are working
2. ✅ Test with real Stripe test cards
3. ✅ Verify subscription renewals
4. ✅ Test payment failure scenarios
5. ✅ Verify bill creation and payment
6. ✅ Monitor logs for any errors
7. ✅ Test in production with Stripe live mode (when ready)

---

## Support

If you encounter issues:
1. Check Laravel logs: `storage/logs/laravel.log`
2. Check Stripe Dashboard webhook logs
3. Verify database records
4. Check webhook endpoint is accessible from Stripe

Good luck with testing! 🚀

