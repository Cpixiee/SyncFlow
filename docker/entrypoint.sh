#!/bin/bash
set -e

echo "🚀 Starting SyncFlow API Container..."

# Simple wait for database
echo "⏳ Waiting for database..."
sleep 15

# Setup Laravel
echo "🔧 Setting up Laravel..."

# Generate keys if needed
php artisan key:generate --force || true
php artisan jwt:secret --force || true

# Clear caches
php artisan config:clear || true
php artisan cache:clear || true

# Try to run migrations (with timeout)
echo "🗃️ Running database setup..."
timeout 30 php artisan migrate --force || echo "⚠️ Migration skipped"
timeout 30 php artisan db:seed --class=LoginUserSeeder --force || echo "⚠️ Seeding skipped"

# Set permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

echo "🎉 SyncFlow API starting..."

# Start Apache
exec apache2-foreground
