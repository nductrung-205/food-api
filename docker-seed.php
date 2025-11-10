<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $count = \App\Models\Product::count();
} catch (Exception $e) {
    echo "⚠️ Skip seed check: {$e->getMessage()}\n";
    exit(0);
}

if ($count == 0) {
    echo "🌱 Database empty, seeding...\n";
    shell_exec('php artisan db:seed --force');
} else {
    echo "✅ Database has data ($count products), skipping seed.\n";
}
