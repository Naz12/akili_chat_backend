#!/bin/bash

# Test Payment Verification Endpoint
# This script verifies a Chapa payment and updates its status

# Configuration
BASE_URL="https://chat.akmicroservice.com"
# BASE_URL="http://localhost:8000"  # Uncomment for local testing

ENDPOINT="${BASE_URL}/api/v1/local/payments/verify"

# Payment details
REFERENCE="chapa_69294f0143d07_1764314881"
PROVIDER="chapa"

# User credentials (you'll need to login first to get a token)
# Replace with your actual JWT token or login credentials
EMAIL="snazrawi@gmail.com"
PASSWORD="snazrawi"

echo "========================================="
echo "Testing Payment Verification Endpoint"
echo "========================================="
echo ""
echo "Reference: ${REFERENCE}"
echo "Provider: ${PROVIDER}"
echo ""

# Step 1: Login to get JWT token
echo "Step 1: Logging in to get JWT token..."
LOGIN_RESPONSE=$(curl -s -X POST "${BASE_URL}/api/v1/local/auth/login" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{
    \"email\": \"${EMAIL}\",
    \"password\": \"${PASSWORD}\"
  }")

echo "Login Response:"
echo "${LOGIN_RESPONSE}" | jq '.' 2>/dev/null || echo "${LOGIN_RESPONSE}"
echo ""

# Extract token from response
TOKEN=$(echo "${LOGIN_RESPONSE}" | grep -o '"token":"[^"]*' | cut -d'"' -f4)

if [ -z "$TOKEN" ]; then
    echo "❌ Failed to get authentication token. Please check your credentials."
    echo ""
    echo "If you already have a token, you can set it manually:"
    echo "export TOKEN='your_jwt_token_here'"
    echo ""
    read -p "Do you have a token to use? (y/n): " has_token
    if [ "$has_token" = "y" ]; then
        read -p "Enter your JWT token: " TOKEN
    else
        exit 1
    fi
fi

echo "✅ Token obtained"
echo ""

# Step 2: Verify payment
echo "Step 2: Verifying payment..."
echo ""

VERIFY_RESPONSE=$(curl -s -X POST "${ENDPOINT}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer ${TOKEN}" \
  -d "{
    \"reference\": \"${REFERENCE}\",
    \"provider\": \"${PROVIDER}\"
  }")

echo "Verification Response:"
echo "${VERIFY_RESPONSE}" | jq '.' 2>/dev/null || echo "${VERIFY_RESPONSE}"
echo ""

# Check if verification was successful
if echo "${VERIFY_RESPONSE}" | grep -q '"status":"success"'; then
    echo "✅ Payment verification successful!"
    echo ""
    echo "The payment status should now be updated and subscription should be created."
else
    echo "⚠️  Payment verification may have failed or payment is still pending."
    echo "Check the response above for details."
fi

echo ""
echo "========================================="
echo "Test Complete"
echo "========================================="

