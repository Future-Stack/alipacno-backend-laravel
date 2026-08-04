# ==========================================
# Stage 1: Build Frontend Assets (Vite)
# ==========================================
FROM node:20-alpine AS node_builder
WORKDIR /app
COPY package*.json vite.config.js ./
RUN npm install
COPY resources ./resources
COPY public ./public
RUN npm run build

# ==========================================
# Stage 2: PHP Application Runtime
# ==========================================
FROM php:8.2-fpm-alpine

# Set working directory
WORKDIR /var/www

# Install system dependencies & PHP extensions
RUN apk add --no-cache \
    bash \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    zip \
    unzip \
    icu-dev \
    oniguruma-dev \
    mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache

# Get Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application source code
COPY . .

# Copy compiled frontend assets from node_builder stage
COPY --from=node_builder /app/public/build ./public/build

# Install PHP dependencies
RUN composer install --optimize-autoloader --no-interaction

# Configure storage and cache permissions
RUN chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Make entrypoint script executable
RUN chmod +x /var/www/docker/entrypoint.sh

# Expose PHP-FPM port
EXPOSE 9000

ENTRYPOINT ["/var/www/docker/entrypoint.sh"]
CMD ["php-fpm"]
