<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'valhaimerd@gmail.com'],
            [
                'name' => 'Valhaimerd',
                'password' => Hash::make('12345678'),
                'role' => 'admin',
                'is_active' => true,
                'default_country' => 'Philippines',
            ]
        );

        User::updateOrCreate(
            ['email' => 'customer@pageturner.test'],
            [
                'name' => 'Customer User',
                'password' => Hash::make('password123'),
                'role' => 'customer',
                'is_active' => true,
                'default_country' => 'Philippines',
            ]
        );
    }
}
