#!/bin/bash

# Find and verify all Chapa payments for a user
# Usage: ./find_chapa_payments.sh [JWT_TOKEN]

BASE_URL="https://chat.akmicroservice.com"
# BASE_URL="http://localhost:8000"  # Uncomment for local testing

EMAIL="snazrawi@gmail.com"
PASSWORD="snazrawi"

# Use provided token or login
if [ -n "$1" ]; then
    TOKEN="$1"
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
echo "Fetching payment history..."
echo ""

# Get payment history
HISTORY_RESPONSE=$(curl -s -X GET "${BASE_URL}/api/v1/local/payments/history?provider=chapa" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer ${TOKEN}")

echo "Payment History:"
echo "${HISTORY_RESPONSE}" | jq '.' 2>/dev/null || echo "${HISTORY_RESPONSE}"

echo ""
echo "========================================="
echo "To verify a specific payment, run:"
echo "./verify_payment.sh [REFERENCE] [TOKEN]"
echo "========================================="

