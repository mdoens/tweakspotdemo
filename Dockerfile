FROM dunglas/frankenphp:1-php8.3

# 1. PHP extensions + system packages
RUN install-php-extensions gd intl pdo_mysql zip opcache redis amqp \
    && apt-get update \
    && apt-get install -y ca-certificates curl gnupg git unzip bash default-mysql-client \
    && mkdir -p /etc/apt/keyrings \
    && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_20.x nodistro main" | tee /etc/apt/sources.list.d/nodesource.list \
    && apt-get update && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 2. Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . /app

# 3. Install dependencies (plugin from Bitbucket via HTTPS — repo is public)
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# 4. Build admin + storefront assets
RUN chmod +x bin/build-administration.sh bin/build-storefront.sh 2>/dev/null || true
RUN CI=1 APP_URL=http://localhost bin/ci 2>/dev/null || \
    (CI=1 APP_URL=http://localhost bin/build-administration.sh && \
     CI=1 APP_URL=http://localhost SHOPWARE_SKIP_THEME_COMPILE=true bin/build-storefront.sh) || true

# 5. Copy entrypoint
COPY docker/entrypoint.sh /app/entrypoint.sh
RUN chmod +x /app/entrypoint.sh

# 6. Fix permissions
RUN chown -R www-data:www-data /app

# 7. FrankenPHP config
ENV SERVER_NAME=:80
EXPOSE 80

ENTRYPOINT ["/app/entrypoint.sh"]
