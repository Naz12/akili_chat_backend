#!/bin/bash

# Simple Payment Verification Script
# Usage: ./verify_payment.sh [REFERENCE] [JWT_TOKEN]
#   - If REFERENCE is provided, it will verify that payment
#   - If REFERENCE is not provided, it uses the default test reference
#   - If JWT_TOKEN is provided as second arg, it uses that instead of logging in

BASE_URL="https://chat.akmicroservice.com"
# BASE_URL="http://localhost:8000"  # Uncomment for local testing

# Default reference (can be overridden)
REFERENCE="${1:-chapa_69294f0143d07_1764314881}"
PROVIDER="chapa"
EMAIL="snazrawi@gmail.com"
PASSWORD="snazrawi"

# Use provided token (second argument) or login
if [ -n "$2" ]; then
    TOKEN="$2"
    echo "Using provided token"
else
    echo "Logging in..."
    LOGIN_RESPONSE=$(curl -s -X POST "${BASE_URL}/api/v1/local/auth/login" \
      -H "Content-Type: application/json" \
      -H "Accept: application/json" \
      -d "{\"email\":\"${EMAIL}\",\"password\":\"${PASSWORD}\"}")
    
    TOKEN=$(echo "${LOGIN_RESPONSE}" | grep -o '"token":"[^"]*' | cut -d'"' -f4)
    
    if [ -z "$TOKEN" ]; then
        echo "❌ Login failed. Response:"
        echo "${LOGIN_RESPONSE}" | jq '.' 2>/dev/null || echo "${LOGIN_RESPONSE}"
        exit 1
    fi
    echo "✅ Login successful"
fi

echo ""
echo "Verifying payment: ${REFERENCE}"
echo ""

RESPONSE=$(curl -s -X POST "${BASE_URL}/api/v1/local/payments/verify" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer ${TOKEN}" \
  -d "{\"reference\":\"${REFERENCE}\",\"provider\":\"${PROVIDER}\"}")

echo "Response:"
echo "${RESPONSE}" | jq '.' 2>/dev/null || echo "${RESPONSE}"

# Check result
if echo "${RESPONSE}" | grep -q '"status":"success"'; then
    echo ""
    echo "✅ Payment verified successfully! Subscription should be created."
elif echo "${RESPONSE}" | grep -q '"verified":true'; then
    echo ""
    echo "✅ Payment verified successfully!"
else
    echo ""
    echo "⚠️  Payment verification completed. Check response above."
fi

