<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Món chính', 'slug' => 'mon-chinh'],
            ['name' => 'Đồ uống', 'slug' => 'do-uong'],
            ['name' => 'Đồ ăn nhanh', 'slug' => 'do-an-nhanh'],
            ['name' => 'Tráng miệng', 'slug' => 'trang-mieng'],
        ];

        foreach ($categories as $item) {
            Category::create($item);
        }
    }
}
