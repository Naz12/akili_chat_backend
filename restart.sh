#!/bin/bash

# Restart PHP-FPM and reload Nginx
# This script restarts the PHP-FPM service and reloads Nginx configuration

echo "🔄 Restarting PHP-FPM..."
sudo systemctl restart php8.3-fpm

if [ $? -eq 0 ]; then
    echo "✅ PHP-FPM restarted successfully"
else
    echo "❌ Failed to restart PHP-FPM"
    exit 1
fi

echo "🔄 Reloading Nginx..."
sudo systemctl reload nginx

if [ $? -eq 0 ]; then
    echo "✅ Nginx reloaded successfully"
else
    echo "❌ Failed to reload Nginx"
    exit 1
fi

echo "✨ All services restarted successfully!"

