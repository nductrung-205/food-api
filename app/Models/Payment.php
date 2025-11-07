<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'method',
        'amount',
        'status',
        'vnp_txn_ref',          // Mã giao dịch của hệ thống
        'vnp_transaction_no',   // Mã giao dịch tại VNPAY
        'vnp_response_code',    // Mã phản hồi từ VNPAY
        'vnp_bank_code',        // Mã ngân hàng thanh toán
        'vnp_card_type',        // Loại thẻ
        'vnp_pay_date',         // Thời gian thanh toán
        'vnp_order_info',       // Thông tin đơn hàng (mô tả)
        'vnp_secure_hash',      // Chữ ký bảo mật
        'notes',                // Ghi chú thêm
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'vnp_pay_date' => 'datetime', // Cast sang datetime để dễ xử lý
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
