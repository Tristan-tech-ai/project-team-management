# Production image for plain Docker and the Vercel Container Runtime.
# The application code, schema, and stack are unchanged: this file only wraps them.
# Local build: docker build -t ptm-team .   Run: see the README Online section.

# 1. Vite assets (Bootstrap, DataTables, FullCalendar, icons).
FROM node:24-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# 2. PHP 8.4 + FrankenPHP (the server Vercel recommends for PHP in a container).
FROM dunglas/frankenphp:1-php8.4-bookworm AS base
RUN install-php-extensions pdo_mysql gd intl zip bcmath opcache \
 && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
WORKDIR /app

# 3. Composer dependencies without dev packages (Debugbar is not installed).
FROM base AS vendor
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --prefer-dist --optimize-autoloader

# 4. Final image.
FROM base
ENV APP_ENV=production \
    APP_DEBUG=false \
    DEBUGBAR_ENABLED=false \
    LOG_CHANNEL=stderr \
    SESSION_DRIVER=database \
    CACHE_STORE=database \
    QUEUE_CONNECTION=sync \
    MYSQL_ATTR_SSL_CA=/etc/ssl/certs/ca-certificates.crt \
    XDG_CONFIG_HOME=/tmp/caddy-config \
    XDG_DATA_HOME=/tmp/caddy-data \
    PTM_STORAGE_ROOT=/tmp/storage/app/private \
    PORT=80
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
# Container file storage is not permanent and can disappear when the instance is replaced,
# so storage points to /tmp, which is always writable; it is recreated on every start.
RUN php artisan package:discover --ansi \
 && rm -rf storage && ln -s /tmp/storage storage \
 && ln -sfn ../storage/app/public public/storage \
 && chmod +x docker/start.sh
COPY docker/Caddyfile /etc/caddy/Caddyfile
CMD ["/app/docker/start.sh"]
