#!/usr/bin/env bash
# Test POST /api/v1/local/chat with: general, PPT, diagram, doc-convert style.
set -e
BASE="${CHAT_BACKEND_URL:-https://chat.akmicroservice.com}/api/v1"
PREFIX="$BASE/local/chat"

echo "=============================================="
echo "Akili Chat Endpoint – Variant Tests"
echo "=============================================="

LOGIN_RESP=$(curl -s -X POST "$BASE/login" -H "Content-Type: application/json" \
  -d '{"email":"snazrawi@gmail.com","password":"test123$"}' --connect-timeout 10 --max-time 15)
TOKEN=$(echo "$LOGIN_RESP" | sed -n 's/.*"access_token":"\([^"]*\)".*/\1/p')
[ -z "$TOKEN" ] && { echo "Login failed."; exit 1; }
echo "Logged in."
echo ""

# Args: name, message
run_test() {
  local name="$1"
  local msg="$2"
  echo "--- $name ---"
  local resp
  resp=$(curl -s -w "\n%{http_code}" -X POST "$PREFIX" \
    -H "Accept: application/json" -H "Content-Type: application/json" \
    -H "Authorization: Bearer $TOKEN" \
    -d "{\"message\": \"$msg\"}" \
    --connect-timeout 10 --max-time 120)
  local code=$(echo "$resp" | tail -n 1)
  local json=$(echo "$resp" | head -n -1)
  echo "HTTP: $code"
  echo "$json" | head -c 600
  echo ""
  echo ""
}

run_test "1. General chat" "Hello, say hi in one sentence."
run_test "2. Generate PPT" "Generate a presentation about renewable energy with 5 slides."
run_test "3. Diagram" "Draw a flowchart for user login process."
run_test "4. Convert doc (no attachment)" "Convert this document to text."
run_test "5. Make a PPT" "Make a PPT about project management with 10 slides."

echo "=============================================="
echo "Done."
echo "=============================================="
