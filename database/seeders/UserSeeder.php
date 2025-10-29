<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'fullname' => 'Admin',
            'email' => 'admin@foodhub.com',
            'password' => Hash::make('123456'),
            'role' => 0,
            'phone' => '0123456789',
            'address' => 'Hà Nội, Việt Nam',
        ]);

        User::create([
            'fullname' => 'Nguyễn Văn An',
            'email' => 'an@example.com',
            'password' => Hash::make('123456'),
            'phone' => '0987654321',
            'address' => 'Quận 1, TP.HCM',
        ]);
    }
}
