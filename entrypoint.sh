#!/bin/sh
set -e

echo "🚀 Starting Laravel container..."

# ==========================
# 1️⃣ Chờ PostgreSQL sẵn sàng
# ==========================
echo "⏳ Waiting for PostgreSQL..."

# Trích xuất thông tin kết nối từ .env
DB_HOST=${DB_HOST:-localhost}
DB_PORT=${DB_PORT:-5432}
DB_DATABASE=${DB_DATABASE:-laravel}
DB_USERNAME=${DB_USERNAME:-postgres}
DB_PASSWORD=${DB_PASSWORD:-password}

until PGPASSWORD=$DB_PASSWORD psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d "$DB_DATABASE" -c '\q' 2>/dev/null; do
  echo "🔁 PostgreSQL not ready - waiting..."
  sleep 3
done

echo "✅ PostgreSQL is up!"

# ==========================
# 2️⃣ Chạy migrate + seed
# ==========================
echo "🗃️  Running migrations..."
php artisan migrate --force || true

# Kiểm tra nếu bảng users trống thì seed (tránh trùng dữ liệu)
COUNT=$(php -r "require 'vendor/autoload.php'; \$c=new PDO('pgsql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_DATABASE', '$DB_USERNAME', '$DB_PASSWORD'); echo \$c->query('SELECT COUNT(*) FROM users')->fetchColumn();")

if [ "$COUNT" -eq 0 ]; then
  echo "🌱 Seeding database..."
  php artisan db:seed --force || true
else
  echo "✅ Database already seeded ($COUNT users found)."
fi

# ==========================
# 3️⃣ Cache & link storage
# ==========================
php artisan storage:link || true
php artisan config:cache && php artisan route:cache

# ==========================
# 4️⃣ Chạy server
# ==========================
echo "✅ Laravel is ready. Starting server..."
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
