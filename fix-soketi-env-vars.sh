#!/bin/bash

# Script to fix Soketi app key using environment variables
# Run with: sudo bash fix-soketi-env-vars.sh

set -e

echo "🔧 FIXING SOKETI APP KEY (Environment Variables)"
echo "=================================================="
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "❌ This script must be run with sudo"
    echo "   Run: sudo bash fix-soketi-env-vars.sh"
    exit 1
fi

SERVICE_FILE="/etc/systemd/system/akili-websocket.service"
BACKEND_DIR="/home/deploy_user_dagi/services/akili_chat_backend"

echo "1️⃣ Checking service file..."
if [ -f "$BACKEND_DIR/akili-websocket.service" ]; then
    echo "   ✅ Service file found"
    echo "   Environment variables configured:"
    grep "SOKETI_APP" "$BACKEND_DIR/akili-websocket.service" | sed 's/^/      /'
else
    echo "   ❌ Service file not found: $BACKEND_DIR/akili-websocket.service"
    exit 1
fi
echo ""

echo "2️⃣ Copying service file..."
cp "$BACKEND_DIR/akili-websocket.service" "$SERVICE_FILE"
echo "   ✅ Service file copied"
echo ""

echo "3️⃣ Reloading systemd..."
systemctl daemon-reload
echo "   ✅ Systemd reloaded"
echo ""

echo "4️⃣ Stopping Soketi service..."
systemctl stop akili-websocket 2>/dev/null || true
sleep 2
echo "   ✅ Service stopped"
echo ""

echo "5️⃣ Starting Soketi service..."
if systemctl start akili-websocket; then
    echo "   ✅ Service started"
else
    echo "   ❌ Failed to start service"
    systemctl status akili-websocket
    exit 1
fi
echo ""

echo "6️⃣ Waiting for service to initialize..."
sleep 5
echo ""

echo "7️⃣ Checking service status..."
if systemctl is-active --quiet akili-websocket; then
    echo "   ✅ Service is running"
else
    echo "   ⚠️  Service may not be fully started"
    systemctl status akili-websocket --no-pager -l
fi
echo ""

echo "8️⃣ Checking Soketi logs for app key..."
sleep 2
if tail -50 /var/log/akili-websocket.log 2>/dev/null | grep -q "akili-chat-key"; then
    echo "   ✅ App key found in logs"
    echo "   Recent log entries:"
    tail -10 /var/log/akili-websocket.log 2>/dev/null | grep -E "app|key|start|listening" | tail -5 | sed 's/^/      /' || echo "      (no matching entries)"
else
    echo "   ⚠️  App key not found in logs (checking full logs...)"
    tail -20 /var/log/akili-websocket.log 2>/dev/null | tail -10 | sed 's/^/      /'
fi
echo ""

echo "9️⃣ Testing Soketi connection..."
sleep 1
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:6001 2>/dev/null || echo "000")
if [ "$HTTP_CODE" != "000" ] && [ "$HTTP_CODE" != "502" ]; then
    echo "   ✅ Soketi is responding (HTTP $HTTP_CODE)"
else
    echo "   ⚠️  Soketi may not be fully ready (HTTP $HTTP_CODE)"
fi
echo ""

echo "=================================================="
echo "✅ CONFIGURATION COMPLETE"
echo "=================================================="
echo ""
echo "Soketi should now recognize the app key: akili-chat-key"
echo ""
echo "Environment variables set:"
echo "  - SOKETI_APP_ID=akili-chat"
echo "  - SOKETI_APP_KEY=akili-chat-key"
echo "  - SOKETI_APP_SECRET=akili-chat-secret"
echo ""
echo "Test the connection:"
echo "  php test/test_websocket_live_connection.php"
echo ""
echo "Check service status:"
echo "  sudo systemctl status akili-websocket"
echo ""
echo "View logs:"
echo "  tail -f /var/log/akili-websocket.log"

