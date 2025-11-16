#!/bin/sh
set -e

echo "🚀 Starting Laravel container..."

# ==========================
# 1️⃣ Khai báo biến môi trường
# ==========================
# Sử dụng biến môi trường do Render cung cấp.
# Nếu các biến này không có, script sẽ báo lỗi ở bước psql/migrate/seed
# và dừng lại, điều này tốt hơn là dùng các giá trị mặc định sai.
DB_HOST=${DB_HOST}
DB_PORT=${DB_PORT}
DB_DATABASE=${DB_DATABASE}
DB_USERNAME=${DB_USERNAME}
DB_PASSWORD=${DB_PASSWORD}
APP_KEY=${APP_KEY}

echo "💡 Environment Check: DB_HOST=$DB_HOST, DB_USER=$DB_USERNAME"

echo "🔑 GEMINI_API_KEY is set: ${GEMINI_API_KEY:+YES}"
# ==========================
# 2️⃣ Chờ PostgreSQL sẵn sàng
# ==========================
echo "⏳ Waiting for PostgreSQL on $DB_HOST:$DB_PORT ..."

# Vòng lặp chờ PostgreSQL sẵn sàng bằng lệnh psql
# Sử dụng 2>&1 để ẩn thông báo lỗi psql và chỉ in ra thông báo chờ của chúng ta
until PGPASSWORD=$DB_PASSWORD psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d "$DB_DATABASE" -c '\q' 2>&1; do
  echo "🔁 PostgreSQL not ready - waiting (sleeping 3s)..."
  sleep 3
done

echo "✅ PostgreSQL is up and reachable!"

# ==========================
# 3️⃣ Cache & link storage
# ==========================
echo "⚙️  Clearing and Caching configurations..."
php artisan optimize:clear # Thêm lệnh này để xóa tất cả các cache tối ưu hóa
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear

php artisan storage:link 
php artisan config:clear  # Xóa cache cũ
php artisan config:cache 
php artisan route:cache
php artisan view:cache

# ==========================
# 4️⃣ Chạy migrate + seed
# ==========================
echo "🗃️  Running migrations..."
# Chạy migrate. Nếu fail, deploy sẽ dừng lại. (Tốt hơn là `|| true`)
php artisan migrate --force

# Kiểm tra nếu bảng users trống thì seed (giả sử bạn có bảng 'users')
# Sử dụng các biến đã khai báo để tạo kết nối PDO
# LƯU Ý: Cách này yêu cầu PDO được cài đặt và PHP có thể tạo PDO instance.
# Nếu bạn dùng Laravel Eloquent, có thể dùng DB::table('users')->count()
USER_COUNT=$(php artisan tinker --execute="echo App\Models\User::count();" 2>/dev/null)

if [ "$USER_COUNT" -eq 0 ] || [ "$USER_COUNT" = "" ]; then
  echo "🌱 Seeding database (No users found)..."
  php artisan db:seed --force
else
  echo "✅ Database already seeded ($USER_COUNT users found). Skipping seed."
fi

# ==========================
# 5️⃣ Chạy server (Quan trọng để fix Port scan timeout)
# ==========================
# Lệnh 'exec' sẽ thay thế tiến trình shell hiện tại bằng tiến trình Laravel server.
# Đảm bảo sử dụng biến $PORT do Render cung cấp.
PORT_TO_USE=${PORT:-8000}
echo "✅ Laravel is ready. Starting web server on 0.0.0.0:$PORT_TO_USE ..."
exec php artisan serve --host=0.0.0.0 --port=$PORT_TO_USE