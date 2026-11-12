# Image produksi untuk Docker biasa dan Vercel Container Runtime.
# Kode, skema, dan stack aplikasi tidak diubah: berkas ini hanya membungkusnya.
# Build lokal: docker build -t ptm-admin .   Jalankan: lihat README bagian Online.

# 1. Aset Vite (Bootstrap, DataTables, FullCalendar, ikon).
FROM node:24-bookworm-slim AS aset
WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# 2. PHP 8.4 + FrankenPHP (server yang disarankan Vercel untuk PHP di container).
FROM dunglas/frankenphp:1-php8.4-bookworm AS dasar
RUN install-php-extensions pdo_mysql intl zip bcmath opcache \
 && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
WORKDIR /app

# 3. Dependensi Composer tanpa paket dev (Debugbar tidak ikut terpasang).
FROM dasar AS vendor
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --prefer-dist --optimize-autoloader

# 4. Image akhir.
FROM dasar
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
    PORT=80
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=aset /app/public/build ./public/build
# Penyimpanan berkas container tidak permanen dan bisa hilang saat instance diganti,
# jadi storage diarahkan ke /tmp yang selalu bisa ditulis; dibuat ulang tiap start.
RUN php artisan package:discover --ansi \
 && rm -rf storage && ln -s /tmp/storage storage \
 && ln -sfn ../storage/app/public public/storage \
 && chmod +x docker/mulai.sh
COPY docker/Caddyfile /etc/caddy/Caddyfile
CMD ["/app/docker/mulai.sh"]
