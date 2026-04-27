#!/bin/sh
set -e

echo "🚀 Starting Laravel application..."

# Ensure we're in the correct directory
cd /var/www

# Fix permissions (safe no-op if already correct)
echo "🔧 Setting permissions..."
chown -R www-data:www-data storage bootstrap/cache || true
chmod -R 775 storage bootstrap/cache || true

# Link storage (ignore if already linked)
echo "🔧 Linking storage..."
php artisan storage:link || true

# Clear stale caches (important when using Docker layers)
echo "🧹 Clearing caches..."
php artisan config:clear || true
php artisan route:clear || true
php artisan cache:clear || true
php artisan view:clear || true

# Run migrations (non-blocking fallback)
echo "🗄️ Running migrations..."
php artisan migrate:fresh --force --seed || echo "⚠️ Migration failed, continuing..."

# echo "Seeding Database..."
# php artisan db:seed || "⚠️ Database seeding failed, continuing..."

# Rebuild optimized caches
echo "⚡ Caching config and routes..."
php artisan config:cache || true
php artisan route:cache || true

echo "🎉 Laravel application is ready!"

# Start the main container process
exec "$@"
