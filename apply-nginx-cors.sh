#!/bin/bash

# Script to apply CORS-enabled nginx configuration

echo "Applying CORS-enabled nginx configuration..."

# Backup current config
echo "1. Backing up current nginx config..."
sudo cp /etc/nginx/sites-enabled/chat.akmicroservice.com.conf /etc/nginx/sites-enabled/chat.akmicroservice.com.conf.backup.$(date +%Y%m%d_%H%M%S)

# Copy new config
echo "2. Copying new nginx config..."
sudo cp /home/deploy_user_dagi/services/akili_chat_backend/chat.akmicroservice.com.conf /etc/nginx/sites-enabled/chat.akmicroservice.com.conf

# Test nginx
echo "3. Testing nginx configuration..."
if sudo nginx -t; then
    echo "✓ Nginx configuration test passed"
    echo "4. Reloading nginx..."
    sudo systemctl reload nginx
    echo "✓ Nginx reloaded successfully"
    echo ""
    echo "CORS headers have been applied at the nginx level!"
    echo "The backend should now properly handle CORS requests."
else
    echo "✗ Nginx configuration test failed!"
    echo "Restoring backup..."
    sudo cp /etc/nginx/sites-enabled/chat.akmicroservice.com.conf.backup.* /etc/nginx/sites-enabled/chat.akmicroservice.com.conf
    exit 1
fi

