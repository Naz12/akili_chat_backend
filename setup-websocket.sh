#!/bin/bash

# Setup script for WebSocket server (Soketi)
# Run this script with sudo to complete the WebSocket setup

echo "🚀 Setting up WebSocket Server (Soketi)"
echo "========================================"
echo ""

# Check if running as root or with sudo
if [ "$EUID" -ne 0 ]; then 
    echo "❌ Please run this script with sudo:"
    echo "   sudo bash setup-websocket.sh"
    exit 1
fi

# Step 1: Copy systemd service file
echo "1️⃣ Copying systemd service file..."
cp /home/deploy_user_dagi/services/akili/akili_chat_backend/akili-websocket.service /etc/systemd/system/akili-websocket.service
chmod 644 /etc/systemd/system/akili-websocket.service
echo "✅ Service file copied"

# Step 2: Reload systemd
echo ""
echo "2️⃣ Reloading systemd daemon..."
systemctl daemon-reload
echo "✅ Systemd reloaded"

# Step 3: Enable service
echo ""
echo "3️⃣ Enabling WebSocket service..."
systemctl enable akili-websocket.service
echo "✅ Service enabled"

# Step 4: Start service
echo ""
echo "4️⃣ Starting WebSocket service..."
systemctl start akili-websocket.service
echo "✅ Service started"

# Step 5: Check status
echo ""
echo "5️⃣ Checking service status..."
sleep 2
systemctl status akili-websocket.service --no-pager | head -15

# Step 6: Test nginx config
echo ""
echo "6️⃣ Testing nginx configuration..."
if nginx -t; then
    echo "✅ Nginx configuration is valid"
    echo ""
    echo "7️⃣ Reloading nginx..."
    systemctl reload nginx
    echo "✅ Nginx reloaded"
else
    echo "❌ Nginx configuration has errors!"
    echo "   Please check: /home/deploy_user_dagi/services/akili/akili_chat_backend/chat.akmicroservice.com.conf"
    exit 1
fi

# Step 7: Check Docker container
echo ""
echo "8️⃣ Checking Docker container..."
sleep 3
if docker ps | grep -q akili-websocket; then
    echo "✅ WebSocket container is running"
    docker ps | grep akili-websocket
else
    echo "⚠️  WebSocket container not found. Checking logs..."
    echo ""
    echo "Recent logs:"
    tail -20 /var/log/akili-websocket.log
fi

echo ""
echo "========================================"
echo "✅ WebSocket setup complete!"
echo ""
echo "📝 Next steps:"
echo "   1. Check service status: sudo systemctl status akili-websocket"
echo "   2. View logs: sudo tail -f /var/log/akili-websocket.log"
echo "   3. Test WebSocket: curl http://localhost:6001"
echo "   4. Clear Laravel config: cd /home/deploy_user_dagi/services/akili/akili_chat_backend && php artisan config:clear"
echo ""
echo "🔗 WebSocket endpoint: wss://chat.akmicroservice.com/app/"
echo ""

