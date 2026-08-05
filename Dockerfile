FROM php:8.3-cli

# Set working directory for the application
WORKDIR /var/www/html

# Install system dependencies required for Laravel and PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    nodejs \
    npm \
    default-mysql-client

# Clear cache to reduce image size
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions needed for Laravel
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Get latest Composer (PHP package manager)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy existing application directory contents
COPY . /var/www/html

# Copy existing application directory permissions
RUN chown -R www-data:www-data /var/www/html

# Install PHP dependencies
RUN git config --global --add safe.directory /var/www/html \
    && composer install --optimize-autoloader --no-dev



#RUN composer install --optimize-autoloader --no-dev

# Install Node.js dependencies and build assets (for frontend)

# Set proper permissions for Laravel storage and cache directories
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Copy entrypoint script to handle container startup
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Expose port 8000 for Laravel's built-in server
EXPOSE 8000

# Set the entrypoint script
ENTRYPOINT ["docker-entrypoint.sh"]

# Default command to run the application
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
