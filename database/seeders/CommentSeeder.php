<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Comment;

class CommentSeeder extends Seeder
{
    public function run(): void
    {
        Comment::create([
            'user_id' => 2,
            'product_id' => 2,
            'content' => 'Trà sữa ngon, trân châu mềm vừa phải!',
        ]);
    }
}
