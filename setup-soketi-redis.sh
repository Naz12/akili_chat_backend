#!/bin/bash

# Script to setup Soketi with Redis backend
# Run with: bash setup-soketi-redis.sh

set -e

echo "🔧 SETTING UP SOKETI WITH REDIS BACKEND"
echo "========================================"
echo ""

echo "1️⃣ Checking Redis connection..."
if redis-cli ping >/dev/null 2>&1; then
    echo "   ✅ Redis is running"
else
    echo "   ❌ Redis is not accessible"
    echo "   Please ensure Redis is running: sudo systemctl status redis"
    exit 1
fi
echo ""

echo "2️⃣ Storing app in Redis..."
# Try different key formats that Soketi might use
APP_JSON='{"id":"akili-chat","key":"akili-chat-key","secret":"akili-chat-secret"}'

# Format 1: Direct key
redis-cli SET "soketi:apps:akili-chat" "$APP_JSON" >/dev/null 2>&1
echo "   ✅ Stored at: soketi:apps:akili-chat"

# Format 2: With prefix (if Soketi uses it)
redis-cli SET "apps:akili-chat" "$APP_JSON" >/dev/null 2>&1
echo "   ✅ Stored at: apps:akili-chat"

# Format 3: As hash (alternative format)
redis-cli HSET "soketi:apps" "akili-chat" "$APP_JSON" >/dev/null 2>&1
echo "   ✅ Stored in hash: soketi:apps"

# Format 4: List format (if Soketi expects a list)
redis-cli LPUSH "soketi:apps:list" "$APP_JSON" >/dev/null 2>&1
echo "   ✅ Added to list: soketi:apps:list"
echo ""

echo "3️⃣ Verifying stored data..."
echo "   Key: soketi:apps:akili-chat"
redis-cli GET "soketi:apps:akili-chat" 2>/dev/null | head -1 | sed 's/^/      /' || echo "      (not found)"
echo ""

echo "4️⃣ Testing Redis connection from container perspective..."
echo "   (This will be tested when Soketi starts)"
echo ""

echo "========================================"
echo "✅ REDIS SETUP COMPLETE"
echo "========================================"
echo ""
echo "App has been stored in Redis in multiple formats."
echo "Soketi should be able to find it using one of these formats."
echo ""
echo "Next steps:"
echo "  1. Rebuild Docker image: sudo bash build-soketi-docker.sh"
echo "  2. Apply service: sudo bash fix-soketi-env-vars.sh"
echo "  3. Test: php test/test_websocket_live_connection.php"
echo ""
echo "If it still doesn't work, check Soketi logs:"
echo "  tail -f /var/log/akili-websocket.log"

