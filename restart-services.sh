#!/bin/bash

echo "=== Restarting Chat Backend Services ==="
echo ""

echo "1. Clearing Laravel cache..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear

echo ""
echo "2. Restarting PHP-FPM..."
sudo systemctl restart php8.3-fpm

echo ""
echo "3. Reloading Nginx..."
sudo systemctl reload nginx

echo ""
echo "4. Checking service status..."
echo ""
echo "PHP-FPM Status:"
sudo systemctl status php8.3-fpm --no-pager | head -5

echo ""
echo "Nginx Status:"
sudo systemctl status nginx --no-pager | head -5

echo ""
echo "✓ Services restarted!"

