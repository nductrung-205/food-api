<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // if (!User::exists()) {
        //     $this->call(UserSeeder::class);
        //     $this->call(CategorySeeder::class);
        //     $this->call(ProductSeeder::class);
        //     $this->call(BannerSeeder::class);
        //     $this->call(CouponSeeder::class);
        // }

        $this->call([
            UserSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            BannerSeeder::class,
            CouponSeeder::class,
            OrderSeeder::class,
            OrderItemSeeder::class,
            PaymentSeeder::class,
            ReviewSeeder::class,
            CommentSeeder::class,
            DeliverySeeder::class,
            NotificationSeeder::class,
        ]);
    }
}
