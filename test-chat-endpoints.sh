#!/usr/bin/env bash
# Test all Akili backend chat endpoints: login, POST chat, GET sessions, GET messages.
# Uses snazrawi@gmail.com / test123$ to get JWT.
set -e
BASE="${CHAT_BACKEND_URL:-https://chat.akmicroservice.com}/api/v1"
REGION="local"
PREFIX="$BASE/$REGION/chat"

echo "=============================================="
echo "Akili Backend Chat Endpoints Test"
echo "Base: $BASE"
echo "=============================================="

# 1) Login
echo ""
echo "1) POST /api/v1/login"
LOGIN_RESP=$(curl -s -w "\n%{http_code}" -X POST "$BASE/login" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email":"snazrawi@gmail.com","password":"test123$"}' \
  --connect-timeout 10 --max-time 15)
LOGIN_BODY=$(echo "$LOGIN_RESP" | head -n -1)
LOGIN_CODE=$(echo "$LOGIN_RESP" | tail -n 1)
TOKEN=$(echo "$LOGIN_BODY" | sed -n 's/.*"access_token":"\([^"]*\)".*/\1/p')
if [ "$LOGIN_CODE" != "200" ] || [ -z "$TOKEN" ]; then
  echo "   FAIL HTTP $LOGIN_CODE. Body: $LOGIN_BODY"
  exit 1
fi
echo "   HTTP $LOGIN_CODE - token obtained"

# 2) POST /chat (send message)
echo ""
echo "2) POST /api/v1/$REGION/chat (send message)"
CHAT_RESP=$(curl -s -w "\n%{http_code}" -X POST "$PREFIX" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"message": "Test message for endpoint check."}' \
  --connect-timeout 10 --max-time 90)
CHAT_BODY=$(echo "$CHAT_RESP" | head -n -1)
CHAT_CODE=$(echo "$CHAT_RESP" | tail -n 1)
SESSION_ID=$(echo "$CHAT_BODY" | sed -n 's/.*"session_id":"\([^"]*\)".*/\1/p')
if [ "$CHAT_CODE" != "200" ]; then
  echo "   FAIL HTTP $CHAT_CODE. Body: $CHAT_BODY"
  exit 1
fi
echo "   HTTP $CHAT_CODE - session_id: $SESSION_ID"

# 3) GET /chat/sessions
echo ""
echo "3) GET /api/v1/$REGION/chat/sessions"
SESS_RESP=$(curl -s -w "\n%{http_code}" -X GET "$PREFIX/sessions" \
  -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" \
  --connect-timeout 10 --max-time 15)
SESS_BODY=$(echo "$SESS_RESP" | head -n -1)
SESS_CODE=$(echo "$SESS_RESP" | tail -n 1)
if [ "$SESS_CODE" != "200" ]; then
  echo "   FAIL HTTP $SESS_CODE. Body: $SESS_BODY"
  exit 1
fi
echo "   HTTP $SESS_CODE - sessions list OK"

# 4) GET /chat/messages/{sessionId}
echo ""
echo "4) GET /api/v1/$REGION/chat/messages/$SESSION_ID"
MSGS_RESP=$(curl -s -w "\n%{http_code}" -X GET "$PREFIX/messages/$SESSION_ID" \
  -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" \
  --connect-timeout 10 --max-time 15)
MSGS_BODY=$(echo "$MSGS_RESP" | head -n -1)
MSGS_CODE=$(echo "$MSGS_RESP" | tail -n 1)
if [ "$MSGS_CODE" != "200" ]; then
  echo "   FAIL HTTP $MSGS_CODE. Body: $MSGS_BODY"
  exit 1
fi
echo "   HTTP $MSGS_CODE - messages OK"

# 5) PUT /chat/sessions/{sessionId} (rename) - use first session from list (user-owned) so rename is allowed
echo ""
echo "5) PUT /api/v1/$REGION/chat/sessions (rename first session)"
RENAME_SID=$(echo "$SESS_BODY" | sed -n 's/.*"id":"\([^"]*\)".*/\1/p' | head -1)
if [ -n "$RENAME_SID" ]; then
  RENAME_RESP=$(curl -s -w "\n%{http_code}" -X PUT "$PREFIX/sessions/$RENAME_SID" \
    -H "Accept: application/json" -H "Content-Type: application/json" \
    -H "Authorization: Bearer $TOKEN" \
    -d '{"title": "Test Renamed"}' \
    --connect-timeout 10 --max-time 15)
  RENAME_CODE=$(echo "$RENAME_RESP" | tail -n 1)
  if [ "$RENAME_CODE" = "200" ]; then
    echo "   HTTP $RENAME_CODE - rename OK"
  else
    echo "   HTTP $RENAME_CODE (optional)"
  fi
else
  echo "   Skip (no session to rename)"
fi

echo ""
echo "=============================================="
echo "All required chat endpoints: OK"
echo "=============================================="
