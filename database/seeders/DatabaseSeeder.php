<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // System categories
        $this->call([
            CategorySeeder::class,
        ]);

        // Admin user
        User::create([
            'name' => 'Mebodo Richard Aristide',
            'email' => 'mebodoaristide@gmail.com',
            'phone' => '074228306',
            'password' => '274784336277', // Auto-hashed by User model cast
            'email_verified_at' => now(),
            'theme' => 'dark',
        ]);
    }
}

