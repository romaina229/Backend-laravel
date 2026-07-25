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
            ['email' => 'admin@aquagestion.com'],
            [
                'name' => 'Administrateur',
                'password' => Hash::make('Admin@2026'),
                'role' => 'admin',
                'active' => true,
            ]
        );
    }
}
