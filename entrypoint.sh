#!/bin/sh
set -e

echo "🚀 Starting Laravel container..."

# ==========================
# 1️⃣ Khai báo biến môi trường
# ==========================
DB_HOST=${DB_HOST}
DB_PORT=${DB_PORT}
DB_DATABASE=${DB_DATABASE}
DB_USERNAME=${DB_USERNAME}
DB_PASSWORD=${DB_PASSWORD}
APP_KEY=${APP_KEY}
PORT_TO_USE=${PORT:-8000} # Đặt ở đây để dùng sớm hơn nếu cần

echo "💡 Environment Check: DB_HOST=$DB_HOST, DB_USER=$DB_USERNAME"

# ==========================
# 2️⃣ Chờ PostgreSQL sẵn sàng
# ==========================
echo "⏳ Waiting for PostgreSQL on $DB_HOST:$DB_PORT ..."
until PGPASSWORD=$DB_PASSWORD psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d "$DB_DATABASE" -c '\q' 2>&1; do
  echo "🔁 PostgreSQL not ready - waiting (sleeping 3s)..."
  sleep 3
done
echo "✅ PostgreSQL is up and reachable!"

# ==========================
# 3️⃣ Xóa và Tạo lại Cache (QUAN TRỌNG CHO LỖI 405)
# ==========================
echo "⚙️  Clearing and Caching configurations..."
php artisan optimize:clear # Thêm lệnh này để xóa tất cả các cache tối ưu hóa
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear # Xóa cache event nếu có sử dụng

php artisan storage:link # Giữ lại lệnh này
php artisan config:cache
php artisan route:cache
php artisan view:cache # Caching view cũng tốt cho production

# ==========================
# 4️⃣ Chạy migrate + seed
# ==========================
echo "🗃️  Running migrations..."
php artisan migrate --force

# Kiểm tra biến môi trường để quyết định có chạy Seeder hay không
if [ "$RUN_SEEDER_ON_DEPLOY" = "true" ]; then
  echo "🌱 Running database seeder (RUN_SEEDER_ON_DEPLOY is true)..."
  php artisan db:seed --force
  # Sau khi seed xong, bạn có thể cân nhắc gửi thông báo hoặc ghi log
  # để biết là nó đã chạy, và sau đó xóa biến môi trường trên Render.
else
  echo "✅ Skipping database seeder (RUN_SEEDER_ON_DEPLOY is not true)."
fi
# Hoặc đơn giản là chỉ chạy seed nếu cần, không kiểm tra phức tạp
# echo "🌱 Running db:seed (if needed)..."
# php artisan db:seed --force
# (LƯU Ý: Nếu seed của bạn không có điều kiện kiểm tra, nó sẽ chạy mỗi lần deploy, hãy cẩn thận)

# ==========================
# 5️⃣ Chạy server
# ==========================
echo "✅ Laravel is ready. Starting web server on 0.0.0.0:$PORT_TO_USE ..."
exec php artisan serve --host=0.0.0.0 --port=$PORT_TO_USE