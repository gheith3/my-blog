# ==========================================
# STAGE 1: Base PHP Environment
# ==========================================
FROM php:8.4-fpm-alpine AS base

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    zip \
    unzip \
    git \
    postgresql-client \
    postgresql-dev \
    sqlite \
    sqlite-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    autoconf \
    g++ \
    make \
    netcat-openbsd

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    pdo_pgsql \
    pdo_sqlite \
    gd \
    zip \
    intl \
    mbstring \
    opcache \
    bcmath

# Install Redis extension via PECL
RUN pecl install redis \
    && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html


# ==========================================
# STAGE 2: Composer Dependencies
# ==========================================
FROM base AS vendor

COPY composer.json composer.lock ./
# Install dependencies (no-scripts prevents errors before code is copied)
RUN composer install --no-scripts --no-autoloader

# Copy the rest of the application
COPY . .

# ✨ THE FIX: Create Laravel's required cache directories before Artisan runs ✨
RUN mkdir -p /var/www/html/bootstrap/cache \
    && mkdir -p /var/www/html/storage/logs \
    && mkdir -p /var/www/html/storage/framework/cache \
    && mkdir -p /var/www/html/storage/framework/sessions \
    && mkdir -p /var/www/html/storage/framework/views

# Generate optimized autoloader and run scripts
# faker is nedded for seeders so we need to install it without --no-dev
# RUN composer dump-autoload --optimize \
#     && composer install --optimize-autoloader


RUN composer dump-autoload --optimize \
    && composer install --no-dev --optimize-autoloader



# ==========================================
# STAGE 3: Frontend Builder (Node.js)
# ==========================================
FROM node:22-alpine AS frontend

WORKDIR /app

# Copy package files and install NPM dependencies
COPY package*.json ./
RUN npm install

# Copy application files (contains tailwind config, resources, etc.)
COPY . .

# ✨ THE FIX: Copy the vendor directory from Stage 2 so Tailwind can see Filament files ✨
COPY --from=vendor /var/www/html/vendor ./vendor

# Build the frontend assets
RUN npm run build


# ==========================================
# STAGE 4: Final Production Image
# ==========================================
FROM base AS final

# Copy all PHP files and the completed vendor directory from Stage 2
COPY --from=vendor /var/www/html /var/www/html

# Copy ONLY the compiled frontend assets from Stage 3
COPY --from=frontend /app/public/build /var/www/html/public/build

# Set correct permissions
RUN mkdir -p /var/www/html/storage/logs \
    && mkdir -p /var/www/html/storage/framework/cache \
    && mkdir -p /var/www/html/storage/framework/sessions \
    && mkdir -p /var/www/html/storage/framework/views \
    && mkdir -p /var/www/html/storage/app/public \
    && mkdir -p /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Copy configuration files
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh

# Create log directories and set final permissions
RUN mkdir -p /var/log/nginx \
    && mkdir -p /var/log/supervisor \
    && mkdir -p /var/log/php-fpm \
    && mkdir -p /run/nginx \
    && touch /var/log/nginx/access.log \
    && touch /var/log/nginx/error.log \
    && chown -R www-data:www-data /var/log/nginx \
    && chown -R www-data:www-data /run/nginx \
    && chmod +x /entrypoint.sh

EXPOSE 80

# Health check (Updated with 127.0.0.1 and longer grace period!)
HEALTHCHECK --interval=30s --timeout=10s --start-period=90s --retries=3 \
    CMD curl -f http://127.0.0.1/ || exit 1

ENTRYPOINT ["/entrypoint.sh"]