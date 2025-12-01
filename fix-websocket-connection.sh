#!/bin/bash

# Script to fix WebSocket connection issues
# Run with: sudo bash fix-websocket-connection.sh

set -e

echo "🔧 FIXING WEBSOCKET CONNECTION ISSUES"
echo "======================================"
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "❌ This script must be run with sudo"
    echo "   Run: sudo bash fix-websocket-connection.sh"
    exit 1
fi

echo "1️⃣ Checking Soketi service status..."
if systemctl is-active --quiet akili-websocket; then
    echo "   ✅ Soketi service is running"
else
    echo "   ❌ Soketi service is NOT running"
    echo "   🔄 Starting Soketi service..."
    systemctl start akili-websocket
    sleep 2
    if systemctl is-active --quiet akili-websocket; then
        echo "   ✅ Soketi service started successfully"
    else
        echo "   ❌ Failed to start Soketi service"
        echo "   📋 Service status:"
        systemctl status akili-websocket --no-pager | head -15
        exit 1
    fi
fi
echo ""

echo "2️⃣ Checking if Soketi is listening on port 6001..."
if netstat -tlnp 2>/dev/null | grep -q ":6001" || ss -tlnp 2>/dev/null | grep -q ":6001"; then
    echo "   ✅ Soketi is listening on port 6001"
else
    echo "   ❌ Soketi is NOT listening on port 6001"
    echo "   📋 Checking service logs..."
    tail -20 /var/log/akili-websocket.log
    echo ""
    echo "   ⚠️  Service may need to be restarted"
    systemctl restart akili-websocket
    sleep 3
    if netstat -tlnp 2>/dev/null | grep -q ":6001" || ss -tlnp 2>/dev/null | grep -q ":6001"; then
        echo "   ✅ Soketi is now listening on port 6001"
    else
        echo "   ❌ Still not listening. Check logs: tail -f /var/log/akili-websocket.log"
    fi
fi
echo ""

echo "3️⃣ Checking nginx configuration..."
if nginx -t 2>&1 | grep -q "syntax is ok"; then
    echo "   ✅ Nginx configuration is valid"
else
    echo "   ❌ Nginx configuration has errors:"
    nginx -t
    exit 1
fi
echo ""

echo "4️⃣ Checking nginx /app/ location block..."
if grep -q "location /app/" /etc/nginx/sites-available/chat.akmicroservice.com.conf; then
    echo "   ✅ Nginx has /app/ location block"
else
    echo "   ❌ Nginx is missing /app/ location block"
    echo "   ⚠️  You need to add the WebSocket proxy configuration"
    exit 1
fi
echo ""

echo "5️⃣ Reloading nginx..."
if systemctl reload nginx; then
    echo "   ✅ Nginx reloaded successfully"
else
    echo "   ❌ Failed to reload nginx"
    exit 1
fi
echo ""

echo "6️⃣ Testing WebSocket endpoint..."
sleep 1
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:6001 || echo "000")
if [ "$HTTP_CODE" != "000" ] && [ "$HTTP_CODE" != "502" ]; then
    echo "   ✅ Soketi is responding (HTTP $HTTP_CODE)"
else
    echo "   ⚠️  Soketi may not be fully ready yet (HTTP $HTTP_CODE)"
    echo "   Wait a few seconds and try again"
fi
echo ""

echo "7️⃣ Final service status check..."
systemctl status akili-websocket --no-pager | head -10
echo ""

echo "✅ WebSocket connection fix complete!"
echo ""
echo "📋 Summary:"
echo "  - Soketi service: $(systemctl is-active akili-websocket || echo 'inactive')"
echo "  - Nginx: $(systemctl is-active nginx || echo 'inactive')"
echo "  - Port 6001: $(netstat -tlnp 2>/dev/null | grep -q ':6001' && echo 'listening' || echo 'not listening')"
echo ""
echo "🔍 If issues persist:"
echo "  1. Check Soketi logs: tail -f /var/log/akili-websocket.log"
echo "  2. Check nginx error logs: tail -f /var/log/nginx/chat.akmicroservice.error.log"
echo "  3. Test WebSocket: wscat -c wss://chat.akmicroservice.com/app/"

