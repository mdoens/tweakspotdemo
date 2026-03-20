#!/bin/bash

echo "========================================"
echo " Tweakspot — Visual Merchandiser Pro"
echo "========================================"

# Load .env if no container env
if [ -z "$DATABASE_URL" ] && [ -f /app/.env ]; then
    echo "[boot] Loading /app/.env..."
    set -a; . /app/.env; set +a
fi
echo "[boot] DATABASE_URL=${DATABASE_URL}"

# Wait for DB
DB_HOST=$(echo "$DATABASE_URL" | sed -n 's/.*@\([^:]*\):.*/\1/p')
DB_HOST="${DB_HOST:-db}"
echo "[boot] DB host: ${DB_HOST}"
echo "[boot] Waiting for database..."
for i in $(seq 1 60); do
    if php -r "try{new PDO('mysql:host=${DB_HOST}','shopware','shopware');echo 'ok';}catch(Exception \$e){exit(1);}" 2>/dev/null; then
        echo "[boot] Database connected!"
        break
    fi
    [ "$i" -eq 60 ] && echo "[boot] WARNING: DB timeout"
    sleep 2
done

cd /app

# Check if Shopware is already installed (by checking if user table exists)
TABLE_COUNT=$(php -r "
try {
    \$pdo = new PDO('mysql:host=${DB_HOST};dbname=shopware','shopware','shopware');
    \$r = \$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=\"shopware\"');
    echo \$r->fetchColumn();
} catch(Exception \$e) { echo '0'; }
" 2>/dev/null)

echo "[boot] Tables in DB: ${TABLE_COUNT}"

if [ "${TABLE_COUNT:-0}" -lt 10 ]; then
    echo "[boot] Installing Shopware..."
    php bin/console system:install --create-database --basic-setup --force 2>&1 || echo "[boot] Install had warnings (OK)"
    echo "[boot] Installed."
else
    echo "[boot] Already installed (${TABLE_COUNT} tables)."
fi

# Plugin activation
echo "[boot] Activating plugin..."
php bin/console plugin:refresh 2>/dev/null || true
php bin/console plugin:install --activate StrixVisualMerchandiser 2>/dev/null || true
php bin/console database:migrate StrixVisualMerchandiser --all 2>/dev/null || true
php bin/console cache:clear 2>/dev/null || true
echo "[boot] Ready!"

# Start FrankenPHP
echo "[boot] Starting FrankenPHP..."
exec frankenphp run --config /etc/caddy/Caddyfile
