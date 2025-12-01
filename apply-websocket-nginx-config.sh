#!/bin/bash

# Script to apply WebSocket nginx configuration and restart services
# Run with: sudo bash apply-websocket-nginx-config.sh

set -e

echo "🔧 Applying WebSocket nginx configuration..."
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "❌ This script must be run with sudo"
    echo "   Run: sudo bash apply-websocket-nginx-config.sh"
    exit 1
fi

NGINX_CONFIG="/etc/nginx/sites-available/chat.akmicroservice.com.conf"
BACKUP_CONFIG="/etc/nginx/sites-available/chat.akmicroservice.com.conf.backup.$(date +%Y%m%d_%H%M%S)"

# Backup existing config
echo "📦 Backing up existing nginx config..."
cp "$NGINX_CONFIG" "$BACKUP_CONFIG"
echo "   ✅ Backup saved to: $BACKUP_CONFIG"
echo ""

# Check if /app/ location block already exists
if grep -q "location /app/" "$NGINX_CONFIG"; then
    echo "✅ WebSocket /app/ location block already exists in nginx config"
else
    echo "⚠️  WebSocket /app/ location block not found - you may need to add it manually"
    echo "   The location block should be added before the main / location block"
fi
echo ""

# Test nginx configuration
echo "🧪 Testing nginx configuration..."
if nginx -t; then
    echo "   ✅ Nginx configuration is valid"
else
    echo "   ❌ Nginx configuration test failed!"
    echo "   Restoring backup..."
    cp "$BACKUP_CONFIG" "$NGINX_CONFIG"
    exit 1
fi
echo ""

# Reload nginx
echo "🔄 Reloading nginx..."
if systemctl reload nginx; then
    echo "   ✅ Nginx reloaded successfully"
else
    echo "   ❌ Failed to reload nginx"
    exit 1
fi
echo ""

# Restart WebSocket service
echo "🔄 Restarting WebSocket service (akili-websocket)..."
if systemctl restart akili-websocket; then
    echo "   ✅ WebSocket service restarted"
else
    echo "   ⚠️  Failed to restart WebSocket service (may not be critical)"
fi
echo ""

# Check service status
echo "📊 Checking WebSocket service status..."
systemctl status akili-websocket --no-pager | head -10
echo ""

# Check if Soketi is listening on port 6001
echo "🔍 Checking if Soketi is listening on port 6001..."
if netstat -tlnp 2>/dev/null | grep -q ":6001" || ss -tlnp 2>/dev/null | grep -q ":6001"; then
    echo "   ✅ Soketi is listening on port 6001"
else
    echo "   ⚠️  Soketi is not listening on port 6001"
    echo "   Check service logs: tail -f /var/log/akili-websocket.log"
fi
echo ""

echo "✅ Configuration applied successfully!"
echo ""
echo "Next steps:"
echo "1. Test WebSocket connection from frontend"
echo "2. Check nginx error logs if issues persist: tail -f /var/log/nginx/chat.akmicroservice.error.log"
echo "3. Check WebSocket service logs: tail -f /var/log/akili-websocket.log"

