#!/bin/sh
set -e

# Ensure permissions for storage and bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Create storage link if not present
if [ ! -d "/var/www/public/storage" ]; then
    echo "Creating storage symlink..."
    php artisan storage:link || true
fi

# Clear stale cache and bootstrap files
rm -f /var/www/bootstrap/cache/packages.php /var/www/bootstrap/cache/services.php /var/www/bootstrap/cache/config.php
php artisan config:clear || true
php artisan package:discover || true

# Run migrations if database is reachable
echo "Running database migrations..."
php artisan migrate --force || echo "Migration failed or database not ready yet."

# Cache configurations in production/optimized environments if desired
# php artisan config:cache
# php artisan route:cache
# php artisan view:cache

echo "Container setup completed. Launching process..."
exec "$@"
