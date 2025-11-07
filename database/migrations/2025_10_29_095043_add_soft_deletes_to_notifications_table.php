<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->softDeletes();
            // Thêm các cột mới: 'type' và 'data'
            $table->string('type')->nullable()->after('message'); // Loại thông báo (order, promotion, system)
            $table->json('data')->nullable()->after('type'); // Dữ liệu bổ sung (ví dụ: order_id, product_id)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['type', 'data']);
        });
    }
};
