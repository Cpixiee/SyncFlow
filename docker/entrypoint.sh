#!/bin/bash
set -e

echo "🚀 Starting SyncFlow API Container..."

# Wait for database to be ready
echo "⏳ Waiting for database connection..."
while ! mysqladmin ping -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" --silent; do
    echo "💤 Database is unavailable - sleeping"
    sleep 2
done

echo "✅ Database is ready!"

# Setup Laravel environment
echo "🔧 Setting up Laravel environment..."

# Generate app key if not exists
if [ ! -f .env ]; then
    echo "📝 Creating .env file..."
    cp .env.example .env
fi

# Generate application key
php artisan key:generate --force

# Generate JWT secret
php artisan jwt:secret --force

# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Run database migrations
echo "🗃️ Running database migrations..."
php artisan migrate --force

# Seed database if needed
echo "🌱 Seeding database..."
php artisan db:seed --class=LoginUserSeeder --force

# Cache configurations for production
echo "⚡ Optimizing for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set final permissions
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache

echo "🎉 SyncFlow API is ready!"

# Start Apache
exec apache2-foreground
