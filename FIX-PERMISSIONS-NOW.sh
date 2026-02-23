#!/bin/bash
# Server: run from backend dir to fix storage permissions (uploads, logs, chat-attachments).

set -e
cd "$(dirname "$0")"

echo "=========================================="
echo "FIXING LARAVEL STORAGE PERMISSIONS"
echo "=========================================="
echo ""

# Ensure directory structure exists (chat uploads, diagrams, presentations)
echo "1. Ensuring storage directories exist..."
mkdir -p storage/app/public/chat-attachments
mkdir -p storage/app/private/diagrams
mkdir -p storage/app/private/presentations
mkdir -p storage/app/private/doc-converter
mkdir -p storage/framework/{sessions,views,cache}
mkdir -p storage/logs
mkdir -p bootstrap/cache

echo "2. Fixing storage directory ownership (www-data)..."
sudo chown -R www-data:www-data storage bootstrap/cache

echo "3. Setting proper permissions..."
sudo chmod -R 775 storage bootstrap/cache

echo "4. Fixing log file specifically..."
touch storage/logs/laravel.log 2>/dev/null || true
sudo chown www-data:www-data storage/logs/laravel.log
sudo chmod 664 storage/logs/laravel.log

echo "5. Ensuring public storage link exists..."
php artisan storage:link 2>/dev/null || true

echo "6. Verifying permissions..."
echo ""
echo "Storage directory:"
ls -ld storage
echo "Chat attachments dir:"
ls -ld storage/app/public/chat-attachments 2>/dev/null || true
echo "Log file:"
ls -l storage/logs/laravel.log

echo ""
echo "7. Restarting PHP-FPM..."
sudo systemctl restart php8.3-fpm 2>/dev/null || true

echo ""
echo "=========================================="
echo "✓ PERMISSIONS FIXED!"
echo "=========================================="
echo ""
echo "Chat uploads and logs are writable. Run as deploy user if you need CLI/tests to write too: ./fix-permissions.sh"

