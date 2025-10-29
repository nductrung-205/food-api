# ==========================
# Laravel Dockerfile (Render)
# ==========================

# Sử dụng PHP 8.2 CLI
FROM php:8.2-cli

# Cài đặt các gói và extension cần thiết
RUN apt-get update && apt-get install -y \
    unzip \
    git \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    curl \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Cài Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Đặt thư mục làm việc
WORKDIR /var/www

# Copy toàn bộ project vào container
COPY . .

# Cài các gói Laravel (production)
RUN composer install --no-dev --optimize-autoloader

# Copy .env.example thành .env (nếu chưa có)
RUN cp .env.example .env || true

# Tạo APP_KEY
RUN php artisan key:generate --force

# Copy entrypoint script
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Mở port Render
EXPOSE 8000

# Lệnh khởi chạy container
CMD ["/entrypoint.sh"]
