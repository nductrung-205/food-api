<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    public function definition()
    {
        $images = config('product_images');

        return [
            'category_id' => fake()->numberBetween(1, 4), // Đảm bảo có 4 categories với id 1-4
            'name' => '',
            'slug' => '',
            'description' => fake()->sentence(12),
            'price' => fake()->numberBetween(10000, 200000),
            'stock' => fake()->numberBetween(10, 300),
            'image' => fake()->randomElement($images),
        ];
    }
}