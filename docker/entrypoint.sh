#!/bin/bash
set -e

echo "========================================"
echo " Tweakspot — Visual Merchandiser Pro"
echo "========================================"

# Wait for database
echo "[boot] Waiting for database..."
for i in $(seq 1 30); do
    if php -r "try { new PDO(getenv('DATABASE_URL') ?: 'mysql:host=localhost'); echo 'ok'; } catch(Exception \$e) { exit(1); }" 2>/dev/null; then
        echo "[boot] Database connected."
        break
    fi
    # Fallback: try mysqladmin
    DB_HOST=$(echo "$DATABASE_URL" | grep -oP '@\K[^:]+')
    if mysqladmin ping -h "$DB_HOST" -u shopware -pshopware --silent 2>/dev/null; then
        echo "[boot] Database connected (mysqladmin)."
        break
    fi
    [ "$i" -eq 30 ] && echo "[boot] WARNING: Database not ready after 60s"
    sleep 2
done

# First-time installation
if [ ! -f /app/install.lock ]; then
    echo "[boot] First run — installing Shopware..."
    php bin/console system:install --create-database --basic-setup --force 2>&1 || true
    touch /app/install.lock
    echo "[boot] Shopware installed."
else
    echo "[boot] Shopware already installed."
    # Run migrations on update
    php bin/console system:update:finish 2>/dev/null || true
fi

# Plugin activation
echo "[boot] Activating plugin..."
php bin/console plugin:refresh 2>/dev/null || true
php bin/console plugin:install --activate StrixVisualMerchandiser 2>/dev/null || true
php bin/console database:migrate StrixVisualMerchandiser --all 2>/dev/null || true
php bin/console cache:clear 2>/dev/null || true
echo "[boot] Plugin activated."

# Start FrankenPHP
echo "[boot] Starting FrankenPHP on :8000..."
exec frankenphp run --config /etc/caddy/Caddyfile
