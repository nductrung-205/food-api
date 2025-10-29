<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Coupon;
use Carbon\Carbon;

class CouponSeeder extends Seeder
{
    public function run(): void
    {
        Coupon::create([
            'code' => 'FOOD30',
            'discount_percent' => 30,
            'valid_from' => Carbon::now(),
            'valid_to' => Carbon::now()->addDays(7),
            'usage_limit' => 100,
        ]);

        Coupon::create([
            'code' => 'FREESHIP',
            'discount_amount' => 15000,
            'valid_from' => Carbon::now(),
            'valid_to' => Carbon::now()->addMonth(),
            'usage_limit' => 200,
        ]);
    }
}
