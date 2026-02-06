#!/bin/bash

echo "=== Fixing Laravel Storage Permissions ==="
echo ""

# Get the web server user (usually www-data)
WEB_USER=$(ps aux | grep -E 'nginx|apache' | grep -v grep | head -1 | awk '{print $1}')
if [ -z "$WEB_USER" ]; then
    WEB_USER="www-data"
fi

echo "Detected web server user: $WEB_USER"
echo ""

# Fix storage and cache permissions
echo "1. Setting ownership..."
sudo chown -R $USER:$WEB_USER storage bootstrap/cache

echo "2. Setting permissions..."
sudo chmod -R 775 storage bootstrap/cache

echo "3. Ensuring log file exists and is writable..."
touch storage/logs/laravel.log 2>/dev/null || true
sudo chown $USER:$WEB_USER storage/logs/laravel.log 2>/dev/null || true
sudo chmod 664 storage/logs/laravel.log 2>/dev/null || true

echo ""
echo "4. Verifying permissions..."
ls -la storage/logs/ | head -5

echo ""
echo "✓ Permissions fixed!"

