<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Pemilik Kost',
            'email' => 'pemilik@gmail.com',
            'password' => 'pemilik123',
            'role' => 'pemilik',
        ]);
    }
}