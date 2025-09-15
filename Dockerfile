# Use PHP 8.3 with Apache
FROM php:8.3-apache

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy existing application directory contents
COPY . /var/www/html

# Set permissions
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html/storage
RUN chmod -R 755 /var/www/html/bootstrap/cache

# Install dependencies
RUN composer install --optimize-autoloader --no-dev

# Configure Apache to serve Laravel
COPY docker/vhost.conf /etc/apache2/sites-available/000-default.conf

# Generate application key if .env doesn't exist
RUN if [ ! -f .env ]; then \
        cp .env.example .env && \
        php artisan key:generate; \
    fi

# Create JWT secret if not exists
RUN php artisan jwt:secret --force

# Run migrations (optional, comment out if you want to run manually)
# RUN php artisan migrate --force

# Expose port 2020
EXPOSE 2020

# Create startup script
RUN echo '#!/bin/bash\n\
# Wait for database connection\n\
echo "Waiting for database..."\n\
sleep 10\n\
\n\
# Run migrations\n\
php artisan migrate --force\n\
\n\
# Start Apache on port 2020\n\
sed -i "s/Listen 80/Listen 2020/g" /etc/apache2/ports.conf\n\
sed -i "s/:80>/:2020>/g" /etc/apache2/sites-available/000-default.conf\n\
apache2-foreground' > /usr/local/bin/start.sh

RUN chmod +x /usr/local/bin/start.sh

CMD ["/usr/local/bin/start.sh"]
