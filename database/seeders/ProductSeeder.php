<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'category_id' => 1,
                'name' => 'Cơm gà xối mỡ',
                'slug' => 'com-ga-xoi-mo',
                'description' => 'Cơm gà giòn rụm, xối mỡ thơm ngon, ăn kèm dưa leo và nước mắm chua ngọt.',
                'price' => 45000,
                'stock' => 100,
                'image' => 'images/products/com-ga-xoi-mo.jpg',
            ],
            [
                'category_id' => 2,
                'name' => 'Trà sữa trân châu đường đen',
                'slug' => 'tra-sua-tran-chau-duong-den',
                'description' => 'Trà sữa hảo hạng với trân châu mềm thơm, đường đen nhập khẩu.',
                'price' => 30000,
                'stock' => 150,
                'image' => 'images/products/tra-sua-tran-chau.jpg',
            ],
            [
                'category_id' => 3,
                'name' => 'Gà rán KFC combo 2 miếng',
                'slug' => 'ga-ran-kfc-combo',
                'description' => 'Gà rán giòn tan, kèm khoai tây chiên và nước ngọt.',
                'price' => 79000,
                'stock' => 50,
                'image' => 'images/products/ga-ran.jpg',
            ],
            [
                'category_id' => 4,
                'name' => 'Bánh flan caramel',
                'slug' => 'banh-flan-caramel',
                'description' => 'Món tráng miệng ngọt ngào với lớp caramel hấp dẫn.',
                'price' => 20000,
                'stock' => 70,
                'image' => 'images/products/banh-flan.jpg',
            ],
        ];

        foreach ($products as $p) {
            Product::create($p);
        }
    }
}
