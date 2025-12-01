# Quick Stripe Test Reference

## Test User
- **Email:** dagu@example.com
- **User ID:** 1
- **Region:** intl
- **Plan:** Next (ID: 6, 300 USD/month)

---

## Step 1: Get JWT Token

```bash
# Login to get token
curl -X POST https://chat.akmicroservice.com/api/v1/intl/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "dagu@example.com",
    "password": "your_password"
  }'
```

Copy the `token` from response.

---

## Step 2: Subscribe to Plan

```bash
# Replace {TOKEN} with your JWT token
curl -X POST https://chat.akmicroservice.com/api/v1/intl/subscribe \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{"plan_id": 6}'
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

---

## Step 3: Complete Payment

1. **Open checkout URL** from response in browser
2. **Use Stripe test card:**
   - Card: `4242 4242 4242 4242`
   - Expiry: `12/25`
   - CVC: `123`
   - ZIP: `12345`
3. **Click "Pay" or "Subscribe"**

---

## Step 4: Monitor Webhook Processing

```bash
# Watch logs in real-time
tail -f storage/logs/laravel.log | grep -i stripe
```

**Look for:**
- ✅ `Stripe webhook received`
- ✅ `Stripe subscription ID extracted`
- ✅ `Payment activated`
- ✅ `Subscription created from payment`
- ✅ `Stripe subscription ID stored`

---

## Step 5: Verify Results

### Check Payment
```bash
php artisan tinker --execute="
\$payment = App\Models\Payment::where('user_id', 1)
    ->orderBy('created_at', 'desc')
    ->first();
echo 'Payment ID: ' . \$payment->id . PHP_EOL;
echo 'Status: ' . \$payment->status . PHP_EOL;
echo 'Metadata: ' . json_encode(\$payment->metadata, JSON_PRETTY_PRINT) . PHP_EOL;
"
```

**Should show:**
- Status: `success`
- Metadata contains: `"stripe_subscription_id": "sub_..."`

### Check Subscription
```bash
php artisan tinker --execute="
\$sub = App\Models\Subscription::where('user_id', 1)
    ->orderBy('created_at', 'desc')
    ->first();
echo 'Subscription ID: ' . \$sub->id . PHP_EOL;
echo 'Is Active: ' . (\$sub->is_active ? 'Yes' : 'No') . PHP_EOL;
echo 'Stripe Subscription ID: ' . (\$sub->metadata['stripe_subscription_id'] ?? 'NONE') . PHP_EOL;
"
```

**Should show:**
- Is Active: `Yes`
- Stripe Subscription ID: `sub_...` (not NONE)

---

## Test Automatic Renewal

### Option 1: Stripe Dashboard
1. Go to Stripe Dashboard → Subscriptions
2. Find subscription for `dagu@example.com`
3. Click "Actions" → "Create invoice"
4. This triggers `invoice.payment_succeeded` webhook

### Option 2: Stripe CLI
```bash
# Get subscription ID from database first
stripe trigger invoice.payment_succeeded \
  --override subscription=sub_XXXXX
```

**Verify:**
- Old subscription deactivated
- New subscription created
- New subscription has same `stripe_subscription_id`

---

## Test Payment Failure

### Stripe Dashboard
1. Go to subscription
2. Click "Actions" → "Create invoice"
3. Manually fail the payment

### Stripe CLI
```bash
stripe trigger invoice.payment_failed \
  --override subscription=sub_XXXXX
```

**Verify:**
- Bill created (type: `renewal_failed`)
- Grace period set (3 days)
- Bill appears in pending bills API

---

## Quick Verification Commands

```bash
# Check pending bills
curl -X GET https://chat.akmicroservice.com/api/v1/intl/bills/pending \
  -H "Authorization: Bearer {TOKEN}"

# Check current subscription
curl -X GET https://chat.akmicroservice.com/api/v1/intl/subscription \
  -H "Authorization: Bearer {TOKEN}"

# Check all subscriptions
curl -X GET https://chat.akmicroservice.com/api/v1/intl/subscriptions \
  -H "Authorization: Bearer {TOKEN}"
```

---

## Troubleshooting

### Webhook Not Received
1. Check Stripe Dashboard → Webhooks → Endpoint status
2. Verify webhook secret in `.env`: `STRIPE_WEBHOOK_SECRET`
3. Check webhook logs in Stripe Dashboard

### Subscription ID Not Stored
1. Check payment metadata contains `stripe_subscription_id`
2. Check subscription metadata contains `stripe_subscription_id`
3. Review logs for errors

### Payment Not Processing
1. Check payment status in database
2. Manually trigger event if needed:
   ```php
   $payment = Payment::find({id});
   event(new \App\Events\PaymentSucceeded($payment));
   ```

---

## Stripe Test Cards

**Success:**
- `4242 4242 4242 4242` - Visa

**Decline:**
- `4000 0000 0000 0002` - Card declined

**3D Secure:**
- `4000 0027 6000 3184` - Requires authentication

---

Ready to test! 🚀

