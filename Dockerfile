# Stage 1: Node.js build for frontend assets
FROM node:24-alpine@sha256:06d7adf3e0ef61a13f1d28682726366f97a15aa1888c934d3ecec86c33918b53 AS node-builder

WORKDIR /app

COPY package*.json ./
RUN npm install

COPY . .
RUN npm run build

# Stage 2: Composer dependencies
# PHP 8.3.37 (19 Nov 2026 security release) also covers CVE-2026-109618/109619.
# Keep both PHP stages aligned.
FROM serversideup/php:8.3.37-cli@sha256:18c82d8812b92ca38d91e87d71655f296208b92f3085b5b6e1ddad4656859d4c AS composer-builder

USER root
WORKDIR /app

COPY composer.json composer.lock ./

# Install Composer dependencies
# Artisan is only available after the application source is copied below.
RUN composer install --no-interaction --optimize-autoloader --no-dev --prefer-dist --no-scripts

COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize --no-dev \
    && composer check-platform-reqs --no-dev

# Test image uses the same PHP runtime and complete Composer lockfile.
FROM composer-builder AS testing

RUN install-php-extensions pdo_sqlite
RUN composer install --no-interaction --prefer-dist --optimize-autoloader \
    && composer check-platform-reqs

COPY --from=node-builder /app/public/build /app/public/build

CMD ["php", "vendor/bin/phpunit", "--colors=never", "--fail-on-warning"]

# Stage 3: Production image
FROM serversideup/php:8.3.37-fpm-nginx@sha256:8fdc286697c1279f793d40ce4a1ed15107052f8278f23b9395fbfa639d0111d6 AS production

ENV PHP_OPCACHE_ENABLE=1

USER root
WORKDIR /var/www/html

# Load before request parsing; application-level ini_set() is too late.
COPY docker/php/security.ini /usr/local/etc/php/conf.d/zz-security.ini

# Copy application files from composer-builder stage
COPY --chown=www-data:www-data --from=composer-builder /app /var/www/html

# Copy built Vite assets from node-builder
COPY --chown=www-data:www-data --from=node-builder /app/public/build /var/www/html/public/build

# Create ALL necessary storage directories
RUN mkdir -p storage/app/public/logos \
    storage/app/livewire-tmp \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

# Remove existing symlink if present and create new one
RUN cd public \
    rm -rf storage && \
    ln -s ../storage/app/public storage && \
    cd ..

# Set proper permissions (CRITICAL!)
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && find /var/www/html/storage -type d -exec chmod 775 {} \; \
    && find /var/www/html/storage -type f -exec chmod 664 {} \; \
    && chmod -R 775 /var/www/html/bootstrap/cache

USER www-data

# Expose port (serversideup/php fpm-nginx uses 8080 by default)
EXPOSE 8080
