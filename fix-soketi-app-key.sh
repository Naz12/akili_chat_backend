#!/bin/bash

# Script to fix Soketi app key configuration
# Run with: sudo bash fix-soketi-app-key.sh

set -e

echo "🔧 FIXING SOKETI APP KEY CONFIGURATION"
echo "======================================="
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "❌ This script must be run with sudo"
    echo "   Run: sudo bash fix-soketi-app-key.sh"
    exit 1
fi

SERVICE_FILE="/etc/systemd/system/akili-websocket.service"
BACKEND_DIR="/home/deploy_user_dagi/services/akili_chat_backend"
CONFIG_FILE="$BACKEND_DIR/soketi.config.json"

echo "1️⃣ Checking Soketi config file..."
if [ -f "$CONFIG_FILE" ]; then
    echo "   ✅ Config file exists: $CONFIG_FILE"
    cat "$CONFIG_FILE" | jq . 2>/dev/null || cat "$CONFIG_FILE"
else
    echo "   ❌ Config file not found: $CONFIG_FILE"
    exit 1
fi
echo ""

echo "2️⃣ Copying service file..."
if [ -f "$BACKEND_DIR/akili-websocket.service" ]; then
    cp "$BACKEND_DIR/akili-websocket.service" "$SERVICE_FILE"
    echo "   ✅ Service file copied"
else
    echo "   ❌ Service file not found: $BACKEND_DIR/akili-websocket.service"
    exit 1
fi
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

echo "6️⃣ Checking service status..."
sleep 3
if systemctl is-active --quiet akili-websocket; then
    echo "   ✅ Service is running"
else
    echo "   ⚠️  Service may not be fully started"
fi
echo ""

echo "7️⃣ Checking Soketi logs..."
sleep 2
if tail -20 /var/log/akili-websocket.log 2>/dev/null | grep -q "akili-chat-key"; then
    echo "   ✅ App key found in logs"
else
    echo "   ⚠️  App key not found in logs (may need more time)"
fi
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

echo "======================================="
echo "✅ CONFIGURATION COMPLETE"
echo "======================================="
echo ""
echo "Soketi should now recognize the app key: akili-chat-key"
echo ""
echo "Test the connection:"
echo "  php test/test_websocket_live_connection.php"
echo ""
echo "Check service status:"
echo "  sudo systemctl status akili-websocket"
echo ""
echo "View logs:"
echo "  tail -f /var/log/akili-websocket.log"

