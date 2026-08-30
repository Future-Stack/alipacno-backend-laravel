#!/bin/bash

set -e

echo "Starting Laravel application..."

## Install/update composer dependencies
#echo "Installing composer dependencies..."
#composer install --optimize-autoloader --no-interaction --no-progress

# Install npm dependencies and build assets
# Checks if node_modules exists to avoid redundant installs
#if [ ! -d "node_modules" ]; then
#    echo "Installing npm dependencies..."
#    npm install --silent
#fi
#
#echo "Building assets..."
#npm run build
#
## Wait for MySQL to be ready
## (Note: In a production script, you might want to add a loop here to actually check MySQL availability)
#
#echo "MySQL is up - continuing..."
#
## Create .env file if it doesn't exist
## Copies the example configuration to key the environment variables
#if [ ! -f .env ]; then
#    echo "Creating .env file from .env.example..."
#    cp .env.example .env
#fi
#
## Generate application key if not set
#if ! grep -q "APP_KEY=base64:" .env; then
#    echo "Generating application key..."
#    php artisan key:generate --force
#fi

# Create storage directories if they don't exist
mkdir -p storage/framework/{sessions,views,cache}
mkdir -p storage/logs
mkdir -p bootstrap/cache

# Set proper permissions
echo "Setting permissions..."
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache

# Run database migrations
echo "Running database migrations..."
php artisan migrate:fresh --force

# Run database seeders
echo "Running database seeders..."
php artisan db:seed --force

# Clear and cache configuration
echo "Optimizing application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link

echo "Laravel application is ready!"

# Execute the main command
exec "$@"
