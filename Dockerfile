FROM php:8.2-cli

# Cài extension cần thiết
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev zip unzip git curl && \
    docker-php-ext-install pdo pdo_mysql

WORKDIR /var/www

# Copy toàn bộ code
COPY . .

# Cài composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install --no-dev --optimize-autoloader

# ✅ Tạo file .env tạm để artisan hoạt động
RUN cp .env.example .env

# ✅ Tạo key và cache config
RUN php artisan key:generate --force
RUN php artisan config:cache && php artisan route:cache

# Mở port Laravel
EXPOSE 8000

# ✅ Chạy server
CMD php artisan serve --host=0.0.0.0 --port=$PORT
