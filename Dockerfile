# ========================================
# ✅ Laravel (PHP 8.2 FPM + Caddy) for Render
# ========================================
FROM php:8.2-fpm

# 1️⃣ Cài đặt gói & extension cần thiết
RUN apt-get update && apt-get install -y \
    git unzip curl libpng-dev libjpeg-dev libfreetype6-dev \
    libonig-dev libxml2-dev libzip-dev zip libpq-dev caddy \
    && docker-php-ext-install pdo pdo_pgsql pdo_mysql mbstring exif pcntl bcmath gd zip

# 2️⃣ Cài Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 3️⃣ Tạo thư mục làm việc
WORKDIR /var/www/html

# 4️⃣ Copy toàn bộ mã nguồn
COPY . .

# 5️⃣ Cài dependency
RUN composer install --no-dev --optimize-autoloader

# 6️⃣ Tạo APP_KEY nếu chưa có
RUN php artisan key:generate --force || true

# 7️⃣ Cache config & route
RUN php artisan config:clear && php artisan route:clear && php artisan view:clear
RUN php artisan config:cache && php artisan route:cache && php artisan view:cache

# 8️⃣ Thiết lập quyền
RUN chmod -R 775 storage bootstrap/cache

# 9️⃣ Copy cấu hình Caddy
COPY Caddyfile /etc/caddy/Caddyfile

# 🔟 Mở cổng Render
EXPOSE 8000

# --- Auto migrate & seed if database empty ---
RUN php artisan migrate --force || true && \
    php -r '$count = 0; try {
        require "vendor/autoload.php";
        $app = require_once "bootstrap/app.php";
        $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
        $kernel->bootstrap();
        $count = \App\Models\Product::count();
    } catch (Exception $e) {
        echo "⚠️  Skip seed check: {$e->getMessage()}\n";
    }
    if ($count == 0) {
        echo "🌱 Database empty, seeding...\n";
        shell_exec("php artisan db:seed --force");
    } else {
        echo "✅ Database has data ($count products), skipping seed.\n";
    }'



# 1️⃣1️⃣ Start cả PHP-FPM & Caddy trong 1 container (foreground)
CMD php-fpm -D && caddy run --config /etc/caddy/Caddyfile --adapter caddyfile
