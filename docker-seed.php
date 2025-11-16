<?php
require __DIR__.'/vendor/autoload.php';

// ✅ Tăng timeout và memory
set_time_limit(300); // 5 phút
ini_set('memory_limit', '512M');

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
    echo "🌱 Database empty, seeding 10,022 products...\n";
    $start = microtime(true);
    
    passthru('php artisan db:seed --force 2>&1', $exitCode);
    
    $time = round(microtime(true) - $start, 2);
    
    if ($exitCode === 0) {
        echo "✅ Seeding completed in {$time}s!\n";
    } else {
        echo "❌ Seeding failed with exit code: $exitCode\n";
    }
} else {
    echo "✅ Database has data ($count products), skipping seed.\n";
}