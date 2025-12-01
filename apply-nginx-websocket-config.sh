#!/bin/bash

# Script to apply nginx WebSocket configuration
# Run with: sudo bash apply-nginx-websocket-config.sh

set -e

echo "🔧 APPLYING NGINX WEBSOCKET CONFIGURATION"
echo "=========================================="
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "❌ This script must be run with sudo"
    echo "   Run: sudo bash apply-nginx-websocket-config.sh"
    exit 1
fi

NGINX_CONFIG="/etc/nginx/sites-available/chat.akmicroservice.com.conf"
BACKUP_CONFIG="/etc/nginx/sites-available/chat.akmicroservice.com.conf.backup.$(date +%Y%m%d_%H%M%S)"

# Backup existing config
echo "📦 Backing up existing nginx config..."
cp "$NGINX_CONFIG" "$BACKUP_CONFIG"
echo "   ✅ Backup saved to: $BACKUP_CONFIG"
echo ""

# Check if /app/ location block exists and has proxy_buffering off
if grep -q "location /app/" "$NGINX_CONFIG" && grep -A 15 "location /app/" "$NGINX_CONFIG" | grep -q "proxy_buffering off"; then
    echo "✅ WebSocket /app/ location block already has proxy_buffering off"
else
    echo "⚠️  Updating /app/ location block..."
    
    # Create temp file with updated config
    TEMP_CONFIG=$(mktemp)
    
    # Read the config and update it
    awk '
    /location \/app\/ \{/ {
        print
        in_app_block = 1
        next
    }
    in_app_block && /proxy_pass/ {
        print "        proxy_pass http://127.0.0.1:6001;"
        next
    }
    in_app_block && /proxy_send_timeout/ {
        print
        if (!proxy_buffering_added) {
            print "        proxy_buffering off;"
            proxy_buffering_added = 1
        }
        in_app_block = 0
        next
    }
    in_app_block && /^[[:space:]]*\}/ {
        if (!proxy_buffering_added) {
            print "        proxy_buffering off;"
            proxy_buffering_added = 1
        }
        in_app_block = 0
    }
    { print }
    ' "$NGINX_CONFIG" > "$TEMP_CONFIG"
    
    # Replace original with updated config
    mv "$TEMP_CONFIG" "$NGINX_CONFIG"
    echo "   ✅ Configuration updated"
fi
echo ""

# Test nginx configuration
echo "🧪 Testing nginx configuration..."
if nginx -t 2>&1 | grep -q "syntax is ok"; then
    echo "   ✅ Nginx configuration is valid"
else
    echo "   ❌ Nginx configuration test failed!"
    echo "   Restoring backup..."
    cp "$BACKUP_CONFIG" "$NGINX_CONFIG"
    nginx -t
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

# Check if Soketi is running
echo "🔍 Checking Soketi service..."
if systemctl is-active --quiet akili-websocket; then
    echo "   ✅ Soketi service is running"
else
    echo "   ⚠️  Soketi service is not running"
    echo "   🔄 Starting Soketi service..."
    systemctl start akili-websocket
    sleep 2
    if systemctl is-active --quiet akili-websocket; then
        echo "   ✅ Soketi service started"
    else
        echo "   ❌ Failed to start Soketi service"
    fi
fi
echo ""

# Test WebSocket endpoint
echo "🧪 Testing WebSocket endpoint..."
sleep 1
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:6001 2>/dev/null || echo "000")
if [ "$HTTP_CODE" != "000" ] && [ "$HTTP_CODE" != "502" ]; then
    echo "   ✅ Soketi is responding (HTTP $HTTP_CODE)"
else
    echo "   ⚠️  Soketi may not be fully ready (HTTP $HTTP_CODE)"
fi
echo ""

echo "✅ Configuration applied successfully!"
echo ""
echo "📋 Summary:"
echo "  - Nginx config: Updated and reloaded"
echo "  - Soketi service: $(systemctl is-active akili-websocket || echo 'inactive')"
echo "  - WebSocket endpoint: wss://chat.akmicroservice.com/app/"
echo ""
echo "🔍 Test the connection:"
echo "  php test/test_end_to_end_notifications.php"

