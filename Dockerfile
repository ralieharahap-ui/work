# syntax=docker/dockerfile:1

# ============================================================
#  Aplikasi Sumber Biomassa PT GEP — image produksi (Docker)
#  Laravel 11 + Inertia + React + Nginx + PHP-FPM
# ============================================================

# ---------- Stage 1: build aset frontend (Vite) ----------
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js tailwind.config.js postcss.config.js ./
RUN npm run build

# ---------- Stage 2: runtime PHP + Nginx ----------
FROM php:8.3-fpm-bookworm AS app

# Ekstensi PHP (via installer resmi mlocati)
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql pdo_sqlite mbstring bcmath intl zip gd opcache \
 && apt-get update \
 && apt-get install -y --no-install-recommends nginx supervisor git unzip \
 && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Sumber aplikasi + aset hasil build
COPY . .
COPY --from=frontend /app/public/build ./public/build

# Dependency PHP produksi + folder writable
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist \
 && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

# Konfigurasi Nginx / PHP / Supervisor / entrypoint
COPY docker/nginx.conf        /etc/nginx/sites-available/default
COPY docker/php.ini           /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/supervisord.conf  /etc/supervisor/conf.d/app.conf
COPY docker/entrypoint.sh     /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/app.conf"]
