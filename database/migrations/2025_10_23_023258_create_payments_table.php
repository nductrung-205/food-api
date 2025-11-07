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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->string('method');
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('pending');

            $table->string('vnp_txn_ref')->nullable()->unique(); // Mã giao dịch của hệ thống (chính là payment->id hoặc order->id)
            $table->string('vnp_transaction_no')->nullable(); // Mã giao dịch tại VNPAY
            $table->string('vnp_response_code')->nullable(); // Mã phản hồi từ VNPAY (00 là thành công)
            $table->string('vnp_bank_code')->nullable(); // Mã ngân hàng thanh toán
            $table->string('vnp_card_type')->nullable(); // Loại thẻ
            $table->dateTime('vnp_pay_date')->nullable(); // Thời gian thanh toán
            $table->string('vnp_order_info')->nullable(); // Thông tin đơn hàng (mô tả)
            $table->string('vnp_secure_hash')->nullable(); // Chữ ký bảo mật nhận được từ VNPAY

            $table->text('notes')->nullable(); // Ghi chú thêm về giao dịch

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
