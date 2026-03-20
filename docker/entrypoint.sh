#!/bin/bash
set -e

echo "========================================"
echo " Tweakspot — Visual Merchandiser Pro"
echo "========================================"

# Source .env file if DATABASE_URL not set as container env
if [ -z "$DATABASE_URL" ] && [ -f /app/.env ]; then
    echo "[boot] Loading .env file..."
    set -a
    . /app/.env
    set +a
fi

# Extract DB host from DATABASE_URL
DB_HOST=$(echo "$DATABASE_URL" | sed -n 's/.*@\([^:]*\):.*/\1/p')
DB_PORT=$(echo "$DATABASE_URL" | sed -n 's/.*:\([0-9]*\)\/.*/\1/p')
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-3306}"

echo "[boot] DB host: ${DB_HOST}:${DB_PORT}"
echo "[boot] Waiting for database..."

for i in $(seq 1 60); do
    if php -r "
        \$dsn = 'mysql:host=${DB_HOST};port=${DB_PORT}';
        try { new PDO(\$dsn, 'shopware', 'shopware'); echo 'connected'; exit(0); }
        catch (Exception \$e) { exit(1); }
    " 2>/dev/null; then
        echo "[boot] Database connected!"
        break
    fi
    [ "$i" -eq 60 ] && echo "[boot] ERROR: Database not available after 120s" && exit 1
    sleep 2
done

# First-time installation
if [ ! -f /app/install.lock ]; then
    echo "[boot] First run — installing Shopware..."
    php bin/console system:install --create-database --basic-setup --force 2>&1
    touch /app/install.lock
    echo "[boot] Shopware installed."
else
    echo "[boot] Already installed."
    php bin/console system:update:finish 2>/dev/null || true
fi

# Plugin
echo "[boot] Activating plugin..."
php bin/console plugin:refresh
php bin/console plugin:install --activate StrixVisualMerchandiser 2>/dev/null || true
php bin/console database:migrate StrixVisualMerchandiser --all 2>/dev/null || true
php bin/console cache:clear 2>/dev/null || true

echo "[boot] Starting FrankenPHP..."
exec frankenphp run --config /etc/caddy/Caddyfile
