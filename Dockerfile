# ================================
# ✅ Laravel + PHP-FPM + Caddy (Render)
# ================================
FROM php:8.2-fpm

# Cài đặt các gói cần thiết
RUN apt-get update && apt-get install -y \
    git unzip curl libpng-dev libjpeg-dev libfreetype6-dev \
    libonig-dev libxml2-dev libzip-dev zip libpq-dev \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql mbstring exif pcntl bcmath gd zip

# Cài composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Tạo thư mục làm việc
WORKDIR /var/www/html

# Copy source code
COPY . .

# Cài dependency
RUN composer install --no-dev --optimize-autoloader

# Tạo APP_KEY nếu chưa có
RUN php artisan key:generate --force || true

# Dọn cache
RUN php artisan config:clear && php artisan route:clear && php artisan view:clear

# Cache config & route để tăng tốc
RUN php artisan config:cache && php artisan route:cache && php artisan view:cache

# Cài Caddy web server
RUN apt-get install -y caddy

# Thiết lập quyền cho storage
RUN chmod -R 775 storage bootstrap/cache

# Copy cấu hình Caddy
COPY Caddyfile /etc/caddy/Caddyfile

# Expose cổng Render
EXPOSE 8000

# Start PHP-FPM và Caddy
CMD service php8.2-fpm start && caddy run --config /etc/caddy/Caddyfile --adapter caddyfile
