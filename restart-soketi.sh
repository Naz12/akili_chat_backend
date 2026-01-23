#!/bin/bash

# Script to restart Soketi with new configuration
# Run with: sudo bash restart-soketi.sh

set -e

echo "🔄 RESTARTING SOKETI WITH NEW CONFIGURATION"
echo "============================================"
echo ""

cd /home/deploy_user_dagi/services/akili/akili_chat_backend

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "❌ This script must be run with sudo"
    echo "   Run: sudo bash restart-soketi.sh"
    exit 1
fi

echo "1️⃣ Copying service file..."
cp akili-websocket.service /etc/systemd/system/akili-websocket.service
echo "   ✅ Service file copied"
echo ""

echo "2️⃣ Reloading systemd..."
systemctl daemon-reload
echo "   ✅ Systemd reloaded"
echo ""

echo "3️⃣ Stopping Soketi..."
systemctl stop akili-websocket 2>/dev/null || true
sleep 2
echo "   ✅ Service stopped"
echo ""

echo "4️⃣ Starting Soketi..."
if systemctl start akili-websocket; then
    echo "   ✅ Service started"
else
    echo "   ❌ Failed to start service"
    systemctl status akili-websocket
    exit 1
fi
echo ""

echo "5️⃣ Waiting for service to initialize..."
sleep 5
echo ""

echo "6️⃣ Checking service status..."
if systemctl is-active --quiet akili-websocket; then
    echo "   ✅ Service is running"
else
    echo "   ⚠️  Service may not be fully started"
fi
echo ""

echo "7️⃣ Checking Soketi logs..."
sleep 2
echo "   Recent log entries:"
tail -20 /var/log/akili-websocket.log 2>/dev/null | grep -E "app|key|start|listening|error|4001" | tail -10 | sed 's/^/      /' || echo "      (checking logs...)"
echo ""

echo "8️⃣ Testing Soketi connection..."
sleep 1
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:6001 2>/dev/null || echo "000")
if [ "$HTTP_CODE" != "000" ] && [ "$HTTP_CODE" != "502" ]; then
    echo "   ✅ Soketi is responding (HTTP $HTTP_CODE)"
else
    echo "   ⚠️  Soketi may not be fully ready (HTTP $HTTP_CODE)"
fi
echo ""

echo "============================================"
echo "✅ RESTART COMPLETE"
echo "============================================"
echo ""
echo "Check logs for app key recognition:"
echo "  tail -f /var/log/akili-websocket.log"
echo ""
echo "Look for:"
echo "  ✅ No 'App key does not exist' errors"
echo "  ✅ App initialization messages"
echo "  ✅ Server listening on port 6001"
echo ""
echo "Test the connection:"
echo "  php test/test_websocket_live_connection.php"

