<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Akun pemilik
        User::create([
            'name' => 'Pemilik Kost',
            'email' => 'pemilik@gmail.com',
            'password' => Hash::make('password123'),
            'role' => 'pemilik',
        ]);

        // Akun penghuni untuk testing
        User::create([
            'name' => 'Penghuni Uji',
            'email' => 'penghuni@gmail.com',
            'password' => Hash::make('password123'),
            'role' => 'penghuni',
        ]);
    }
}