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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('order_code')->unique();

            $table->decimal('subtotal_price', 10, 2)->default(0);      
            $table->decimal('delivery_fee', 10, 2)->default(0);       
            $table->decimal('discount_amount', 10, 2)->default(0); 
            $table->string('coupon_code')->nullable()->default('');
            $table->decimal('total_price', 10, 2);

            $table->string('status')->default('pending');            
            $table->string('payment_method')->default('COD');        
            $table->timestamp('paid_at')->nullable();
           
            $table->string('customer_name');    
            $table->string('customer_email');
            $table->string('customer_phone');
            $table->string('customer_address'); 
            $table->string('customer_ward');
            $table->string('customer_district');
            $table->string('customer_city');
            $table->string('customer_type')->default('Nhà Riêng');
            $table->text('customer_note')->nullable();  

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
