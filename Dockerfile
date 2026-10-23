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
# Patched for CVE-2026-109618 and CVE-2026-109619. Keep both PHP stages aligned.
FROM serversideup/php:8.3.36-cli@sha256:27522e2f5f4da8ba3f800d23df6032bf4487ce58ee347b49dd829a0565a7fa26 AS composer-builder

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
FROM serversideup/php:8.3.36-fpm-nginx@sha256:e6e9d1a027d603dd6399adc66cb85f83e474ca11fb521e1c2eb1411a82dd5ff0 AS production

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
