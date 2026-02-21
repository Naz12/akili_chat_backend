#!/usr/bin/env bash
# Test 1) AI manager directly (same style as zooys: /api/custom-prompt + X-API-KEY).
# Test 2) Akili backend POST /api/v1/local/chat (requires valid JWT for auth user, or no token for guest).
# Load env from .env in same directory (backend).
set -e
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Load AI Manager and backend URL from .env (no spaces in values)
if [ -f .env ]; then
  export $(grep -E '^AI_MANAGER_URL=|^AI_MANAGER_API_KEY=|^AI_MANAGER_MODEL=' .env | xargs)
fi
AI_MANAGER_URL="${AI_MANAGER_URL:-https://aimanager.akmicroservice.com}"
AI_MANAGER_API_KEY="${AI_MANAGER_API_KEY:-}"
AI_MANAGER_MODEL="${AI_MANAGER_MODEL:-deepseek-chat}"
CHAT_BACKEND_URL="${CHAT_BACKEND_URL:-https://chat.akmicroservice.com}"

echo "=============================================="
echo "1) AI Manager direct (zooys style: /api/custom-prompt + X-API-KEY)"
echo "=============================================="
if [ -z "$AI_MANAGER_API_KEY" ]; then
  echo "   Skip: AI_MANAGER_API_KEY not set (add from .env)"
else
  echo "   URL: $AI_MANAGER_URL/api/custom-prompt"
  echo "   Model: $AI_MANAGER_MODEL"
  HTTP=$(curl -s -w "\n%{http_code}" -X POST "$AI_MANAGER_URL/api/custom-prompt" \
    -H "Accept: application/json" \
    -H "Content-Type: application/json" \
    -H "X-API-KEY: $AI_MANAGER_API_KEY" \
    -d "{\"prompt\": \"Say hello in one short sentence.\", \"model\": \"$AI_MANAGER_MODEL\", \"response_format\": \"text\"}" \
    --connect-timeout 15 --max-time 60)
  BODY=$(echo "$HTTP" | head -n -1)
  CODE=$(echo "$HTTP" | tail -n 1)
  echo "   HTTP: $CODE"
  echo "   Body (first 500 chars):"
  echo "$BODY" | head -c 500
  echo ""
  if [ "$CODE" = "200" ]; then
    echo "   -> AI Manager direct: OK"
  else
    echo "   -> AI Manager direct: FAIL (check URL, key, and service)"
  fi
fi

echo ""
echo "=============================================="
echo "2) Akili backend POST /api/v1/local/chat"
echo "=============================================="
# Optional: JWT for authenticated user (set AUTH_TOKEN env to test as user)
if [ -n "$AUTH_TOKEN" ]; then
  echo "   Using AUTH_TOKEN (authenticated user)"
  CHAT_RESP=$(curl -s -w "\n%{http_code}" -X POST "$CHAT_BACKEND_URL/api/v1/local/chat" \
    -H "Accept: application/json" \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer $AUTH_TOKEN" \
    -d '{"message": "Say hi in one word."}' \
    --connect-timeout 10 --max-time 90)
else
  echo "   No AUTH_TOKEN; trying as guest (no Authorization header)"
  CHAT_RESP=$(curl -s -w "\n%{http_code}" -X POST "$CHAT_BACKEND_URL/api/v1/local/chat" \
    -H "Accept: application/json" \
    -H "Content-Type: application/json" \
    -d '{"message": "Say hi in one word."}' \
    --connect-timeout 10 --max-time 90)
fi
CHAT_BODY=$(echo "$CHAT_RESP" | head -n -1)
CHAT_CODE=$(echo "$CHAT_RESP" | tail -n 1)
echo "   HTTP: $CHAT_CODE"
echo "   Body (first 600 chars):"
echo "$CHAT_BODY" | head -c 600
echo ""
if [ "$CHAT_CODE" = "200" ]; then
  echo "   -> Backend chat: OK (check reply in body)"
else
  echo "   -> Backend chat: FAIL or auth required (set AUTH_TOKEN for logged-in user)"
fi

echo ""
echo "=============================================="
echo "To test as logged-in user: get token then re-run with it:"
echo "  TOKEN=\$(curl -s -X POST $CHAT_BACKEND_URL/api/v1/login -H 'Content-Type: application/json' -d '{\"email\":\"snazrawi@gmail.com\",\"password\":\"test123\$\"}' | sed -n 's/.*\"access_token\":\"\\([^\"]*\\)\".*/\\1/p')"
echo "  AUTH_TOKEN=\$TOKEN ./test-ai-manager-and-chat.sh"
echo "If backend returns 500 (guest_sessions duplicate), restart PHP-FPM so JWT auth is used: sudo systemctl restart php8.3-fpm"
echo "=============================================="
