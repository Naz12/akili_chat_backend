#!/bin/bash

# Test script to verify Soketi configuration
# Run with: sudo bash test-soketi-config.sh

set -e

echo "🧪 TESTING SOKETI CONFIGURATION"
echo "================================"
echo ""

echo "1️⃣ Checking if Soketi container is running..."
if docker ps | grep -q akili-websocket; then
    echo "   ✅ Container is running"
    CONTAINER_ID=$(docker ps | grep akili-websocket | awk '{print $1}')
else
    echo "   ❌ Container is not running"
    exit 1
fi
echo ""

echo "2️⃣ Checking config file in container..."
if docker exec akili-websocket test -f /app/config.json 2>/dev/null; then
    echo "   ✅ Config file exists at /app/config.json"
    echo "   Contents:"
    docker exec akili-websocket cat /app/config.json 2>/dev/null | sed 's/^/      /'
else
    echo "   ❌ Config file not found at /app/config.json"
    echo "   Checking for config file in other locations..."
    docker exec akili-websocket find /app -name "*.json" 2>/dev/null | sed 's/^/      /' || echo "      No JSON files found"
fi
echo ""

echo "3️⃣ Checking environment variables in container..."
echo "   Environment variables:"
docker exec akili-websocket env 2>/dev/null | grep SOKETI | sed 's/^/      /' || echo "      No SOKETI env vars found"
echo ""

echo "4️⃣ Checking Soketi process..."
echo "   Running processes:"
docker exec akili-websocket ps aux 2>/dev/null | grep soketi | sed 's/^/      /' || echo "      Soketi process not found"
echo ""

echo "5️⃣ Testing Soketi HTTP endpoint..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:6001 2>/dev/null || echo "000")
echo "   HTTP Status: $HTTP_CODE"
if [ "$HTTP_CODE" != "000" ]; then
    echo "   Response:"
    curl -s http://127.0.0.1:6001 2>/dev/null | head -5 | sed 's/^/      /' || echo "      (no response body)"
fi
echo ""

echo "6️⃣ Checking Soketi logs for app registration..."
echo "   Recent log entries:"
tail -30 /var/log/akili-websocket.log 2>/dev/null | grep -E "app|config|start|listening|key" | tail -10 | sed 's/^/      /' || echo "      (no matching entries)"
echo ""

echo "================================"
echo "📊 SUMMARY"
echo "================================"
echo ""
echo "If config file is not found, Soketi might be using:"
echo "  - Environment variables (if supported)"
echo "  - Default configuration"
echo "  - Database backend (Redis/MySQL/PostgreSQL)"
echo ""
echo "Next steps:"
echo "  1. Verify config file is mounted correctly"
echo "  2. Check if Soketi supports --config flag"
echo "  3. Try using Redis/database backend for apps"

