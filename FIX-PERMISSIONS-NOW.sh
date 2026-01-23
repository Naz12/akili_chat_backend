#!/bin/bash

echo "=========================================="
echo "FIXING LARAVEL STORAGE PERMISSIONS"
echo "=========================================="
echo ""

cd /home/deploy_user_dagi/services/akili/akili_chat_backend

echo "1. Fixing storage directory ownership..."
sudo chown -R www-data:www-data storage bootstrap/cache

echo "2. Setting proper permissions..."
sudo chmod -R 775 storage bootstrap/cache

echo "3. Fixing log file specifically..."
sudo chown www-data:www-data storage/logs/laravel.log
sudo chmod 664 storage/logs/laravel.log

echo "4. Verifying permissions..."
echo ""
echo "Storage directory:"
ls -ld storage
echo ""
echo "Log file:"
ls -l storage/logs/laravel.log

echo ""
echo "5. Restarting PHP-FPM..."
sudo systemctl restart php8.3-fpm

echo ""
echo "=========================================="
echo "✓ PERMISSIONS FIXED!"
echo "=========================================="
echo ""
echo "The login endpoint should now work."
echo "Test it by trying to login again."

