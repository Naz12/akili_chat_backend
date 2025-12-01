#!/bin/bash

# Stripe Subscription Test Script
# Usage: ./test_stripe_subscription.sh {JWT_TOKEN}

if [ -z "$1" ]; then
    echo "Usage: ./test_stripe_subscription.sh {JWT_TOKEN}"
    echo "Get JWT token by logging in as dagu@example.com"
    exit 1
fi

TOKEN=$1
BASE_URL="https://chat.akmicroservice.com/api/v1/intl"

echo "=== Testing Stripe Subscription ==="
echo ""

# Step 1: Subscribe to plan
echo "Step 1: Subscribing to Next plan (300 USD/month)..."
RESPONSE=$(curl -s -X POST "$BASE_URL/subscribe" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"plan_id": 6}')

echo "Response:"
echo "$RESPONSE" | jq '.'

# Extract checkout URL
CHECKOUT_URL=$(echo "$RESPONSE" | jq -r '.checkout_url // empty')
PAYMENT_ID=$(echo "$RESPONSE" | jq -r '.payment_id // empty')
SUBSCRIPTION_ID=$(echo "$RESPONSE" | jq -r '.subscription_id // empty')

if [ -z "$CHECKOUT_URL" ]; then
    echo "❌ Error: No checkout URL returned"
    exit 1
fi

echo ""
echo "✅ Subscription checkout created!"
echo "Payment ID: $PAYMENT_ID"
echo "Subscription ID: $SUBSCRIPTION_ID"
echo ""
echo "=== Next Steps ==="
echo "1. Open checkout URL in browser:"
echo "   $CHECKOUT_URL"
echo ""
echo "2. Use Stripe test card:"
echo "   Card: 4242 4242 4242 4242"
echo "   Expiry: 12/25"
echo "   CVC: 123"
echo "   ZIP: 12345"
echo ""
echo "3. Complete payment"
echo ""
echo "4. Monitor webhook processing:"
echo "   tail -f storage/logs/laravel.log | grep -i stripe"
echo ""
echo "5. Verify subscription:"
echo "   curl -X GET '$BASE_URL/subscription' -H 'Authorization: Bearer $TOKEN' | jq '.'"

