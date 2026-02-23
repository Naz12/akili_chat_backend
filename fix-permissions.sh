#!/bin/bash
# Run this on the server when you see: "Permission denied" on storage (logs, uploads, etc.)
# or "Storage directory is not writable" for diagrams/presentations/doc-converter.
#
# Storage must be writable by both:
#   - The deploy user (you), so you can run artisan and deploy.
#   - The web server user (e.g. www-data), so PHP-FPM/nginx can write uploads and generated files.
#
# Usage: from the backend directory run:  ./fix-permissions.sh
# (You may be prompted for sudo password.)

set -e
cd "$(dirname "$0")"

echo "=== Fixing Laravel Storage Permissions ==="
echo ""

# Prefer the *worker* process user (www-data), not the master (root). Otherwise we set group=root and www-data still can't write.
WEB_USER=$(ps aux | grep -E 'nginx: worker|php-fpm: pool|apache2.*worker' | grep -v grep | head -1 | awk '{print $1}')
if [ -z "$WEB_USER" ] || [ "$WEB_USER" = "root" ]; then
    WEB_USER=$(ps aux | grep -E 'php-fpm|nginx|apache' | grep -v grep | grep -v ' root ' | head -1 | awk '{print $1}')
fi
if [ -z "$WEB_USER" ] || [ "$WEB_USER" = "root" ]; then
    WEB_USER="www-data"
fi

echo "Owner (deploy user): $USER"
echo "Group (web server user): $WEB_USER"
echo ""

# Ensure directory structure exists (chat uploads, diagrams, presentations, logs)
echo "1. Ensuring storage directories exist..."
mkdir -p storage/app/public/chat-attachments
mkdir -p storage/app/private/diagrams
mkdir -p storage/app/private/presentations
mkdir -p storage/app/private/doc-converter
mkdir -p storage/framework/{sessions,views,cache}
mkdir -p storage/logs
mkdir -p bootstrap/cache

# Ownership: deploy user + web group so both can write
echo "2. Setting ownership (${USER}:${WEB_USER})..."
sudo chown -R "$USER:$WEB_USER" storage bootstrap/cache

echo "3. Setting permissions (775 so owner and group can write)..."
sudo chmod -R 775 storage bootstrap/cache

echo "4. Ensuring log file exists and is writable..."
touch storage/logs/laravel.log 2>/dev/null || true
sudo chown "$USER:$WEB_USER" storage/logs/laravel.log 2>/dev/null || true
sudo chmod 664 storage/logs/laravel.log 2>/dev/null || true

echo "5. Ensuring public storage link exists..."
php artisan storage:link 2>/dev/null || true
# "The [public/storage] link already exists" is normal; ignore it.

echo ""
echo "6. Verifying..."
ls -la storage/app/public/ 2>/dev/null | head -5
ls -la storage/app/private/ 2>/dev/null | head -5
ls -la storage/logs/ | head -3

echo ""
echo "✓ Permissions fixed! Chat attachments, diagrams, presentations, doc-converter, and logs are writable."
echo "  Manual fallback from this directory: sudo chown -R $USER:$WEB_USER storage bootstrap/cache && sudo chmod -R 775 storage bootstrap/cache"

