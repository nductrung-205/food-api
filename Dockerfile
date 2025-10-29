FROM php:8.2-cli

# Cài các extension cần thiết
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
@@ -10,10 +11,21 @@ RUN apt-get update && apt-get install -y \

WORKDIR /var/www

# Copy toàn bộ mã nguồn vào container
COPY . .

# Cài composer và dependency
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install --no-dev --optimize-autoloader

# ✅ Thêm dòng này để tạo symbolic link storage/public
RUN php artisan storage:link

# ✅ Tạo cache config và route để build nhanh hơn
RUN php artisan config:cache && php artisan route:cache

# Mở port cho Laravel
EXPOSE 8000

# ✅ Chạy server
CMD php artisan serve --host=0.0.0.0 --port=$PORT