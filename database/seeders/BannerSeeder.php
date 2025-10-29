<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Banner;

class BannerSeeder extends Seeder
{
    public function run(): void
    {
        $banners = [
            ['title' => 'Giảm giá 30% món chính hôm nay!', 'image' => 'images/banners/banner1.jpg', 'link' => '/menu'],
            ['title' => 'Combo gà rán chỉ 79K!', 'image' => 'images/banners/banner2.jpg', 'link' => '/combo-ga'],
        ];

        foreach ($banners as $b) {
            Banner::create($b);
        }
    }
}
