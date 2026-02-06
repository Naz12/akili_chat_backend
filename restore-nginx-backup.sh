#!/bin/bash

# Script to restore the original nginx configuration

echo "Restoring original nginx configuration..."

# Find the most recent backup
BACKUP_FILE=$(ls -t /etc/nginx/sites-enabled/chat.akmicroservice.com.conf.backup.* 2>/dev/null | head -1)

if [ -z "$BACKUP_FILE" ]; then
    echo "✗ No backup file found!"
    exit 1
fi

echo "Found backup: $BACKUP_FILE"
echo "Restoring..."

# Restore backup
sudo cp "$BACKUP_FILE" /etc/nginx/sites-enabled/chat.akmicroservice.com.conf

# Test nginx
echo "Testing nginx configuration..."
if sudo nginx -t; then
    echo "✓ Nginx configuration test passed"
    echo "Reloading nginx..."
    sudo systemctl reload nginx
    echo "✓ Nginx reloaded successfully"
    echo ""
    echo "Original configuration has been restored!"
else
    echo "✗ Nginx configuration test failed!"
    exit 1
fi

