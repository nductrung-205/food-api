<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Notification;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        Notification::create([
            'user_id' => 2,
            'title' => 'Đơn hàng đã giao thành công',
            'message' => 'Cảm ơn bạn đã đặt hàng tại FoodHub. Chúc bạn ngon miệng!',
            'is_read' => false,
        ]);
    }
}
