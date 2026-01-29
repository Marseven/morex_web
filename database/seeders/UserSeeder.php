<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'mebodoaristide@gmail.com'],
            [
                'name' => 'Mebodo Richard Aristide',
                'phone' => '074228306',
                'password' => '274784336277', // Auto-hashed by User model cast
                'email_verified_at' => now(),
                'theme' => 'dark',
            ]
        );
    }
}
