# Sử dụng PHP 8.2 CLI
FROM php:8.2-cli

# Cài các extension và gói cần thiết
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

# Copy toàn bộ mã nguồn
COPY . .

# Cài các gói Laravel
RUN composer install --no-dev --optimize-autoloader

# Copy .env.example thành .env nếu chưa có
RUN cp .env.example .env || true

# Sinh APP_KEY
RUN php artisan key:generate --force

# Migrate + Seed (nếu DB sẵn sàng, nếu chưa thì bỏ qua lỗi)
RUN php artisan migrate --force || true
RUN php artisan db:seed --force || true

# Tạo symbolic link & cache
RUN php artisan storage:link || true
RUN php artisan config:cache && php artisan route:cache

# Mở port Render
EXPOSE 8000

# Chạy server Laravel
CMD php artisan serve --host=0.0.0.0 --port=$PORT
