FROM php:8.2-apache

# Cài các extension cần thiết
RUN apt-get update && apt-get install -y \
    git unzip libpng-dev libjpeg-dev libonig-dev libxml2-dev zip curl libzip-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Enable Apache rewrite
RUN a2enmod rewrite

# Copy composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy source
COPY . .

# Install Laravel dependencies
RUN composer install --no-dev --optimize-autoloader

# Storage link & cache
RUN php artisan storage:link
RUN php artisan config:cache && php artisan route:cache

# Set permissions
RUN chmod -R 777 storage bootstrap/cache

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
