#!/bin/sh

echo "🚀 Running migrations..."
php artisan migrate --force

echo "🌱 Running seed check..."
php docker-seed.php

echo "🔥 Starting PHP-FPM & Caddy..."
php-fpm -D
caddy run --config /etc/caddy/Caddyfile --adapter caddyfile
