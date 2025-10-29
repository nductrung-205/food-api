# Sử dụng PHP 8.2 với Composer
FROM php:8.2-cli

# Cài extension cần thiết
RUN apt-get update && apt-get install -y unzip libpng-dev libonig-dev libxml2-dev && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Cài Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

RUN composer install --no-dev --optimize-autoloader
RUN php artisan key:generate --force
RUN php artisan migrate --force

EXPOSE 10000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=10000"]
