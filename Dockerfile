# Stage 1: Node.js build for frontend assets
FROM node:24-alpine@sha256:06d7adf3e0ef61a13f1d28682726366f97a15aa1888c934d3ecec86c33918b53 AS node-builder

WORKDIR /app

# Copy package files
COPY package*.json ./

# Install Node dependencies
RUN npm ci

# Copy source files needed for Vite build
COPY . .

# Build Vite assets (this compiles Tailwind CSS)
RUN npm run build

# Stage 2: Composer dependencies
# PHP 8.3.37 (19 Nov 2026 security release) also covers CVE-2026-109618/109619.
# Keep both PHP stages aligned.
FROM serversideup/php:8.3.37-cli@sha256:18c82d8812b92ca38d91e87d71655f296208b92f3085b5b6e1ddad4656859d4c AS composer-builder

USER root

WORKDIR /app

# Copy composer files
COPY composer.json composer.lock ./

# Install Composer dependencies
# Artisan is only available after the application source is copied below.
RUN composer install --no-interaction --optimize-autoloader --no-dev --prefer-dist --no-scripts

# Copy application files
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

# Set working directory
WORKDIR /var/www/html

# Load before request parsing; application-level ini_set() is too late.
COPY docker/php/security.ini /usr/local/etc/php/conf.d/zz-security.ini

# Copy application files from composer-builder stage
COPY --chown=www-data:www-data --from=composer-builder /app /var/www/html

# Copy built Vite assets from node-builder stage
COPY --chown=www-data:www-data --from=node-builder /app/public/build /var/www/html/public/build

# Check the actual production runtime, independently of the CLI build stage.
RUN composer check-platform-reqs --no-dev

# Set permissions for Laravel
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache \
    && ln -s ../storage/app/public /var/www/html/public/storage

# Switch to www-data user
USER www-data

# Expose port (serversideup/php fpm-nginx uses 8080 by default)
EXPOSE 8080
