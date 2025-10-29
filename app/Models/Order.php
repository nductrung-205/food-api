<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_code',
        'subtotal_price',
        'delivery_fee',
        'discount_amount',
        'coupon_code',
        'total_price',
        'status',
        'payment_method',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_address',
        'customer_ward',
        'customer_district',
        'customer_city',
        'customer_type',
        'customer_note',
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
        'subtotal_price' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    // Thêm accessor
    public function getCouponCodeAttribute($value)
    {
        return $value ?: null;
    }

    public function getDiscountAmountAttribute($value)
    {
        return $value ? (float) $value : 0;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function delivery()
    {
        return $this->hasOne(Delivery::class);
    }
}
