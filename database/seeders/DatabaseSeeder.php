<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Penghuni;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
     // Akun pemilik
        $pemilik = User::create([
            'name' => 'Pemilik Kost',
            'email' => 'pemilik@gmail.com',
            'password' => Hash::make('password123'),
            'role' => 'pemilik',
        ]);

        // Akun penghuni
        $penghuni = User::create([
            'name' => 'Penghuni Uji',
            'email' => 'penghuni@gmail.com',
            'password' => Hash::make('password123'),
            'role' => 'penghuni',
        ]);

        // Data penghuni yang terhubung dengan akun
        Penghuni::create([
            'user_id' => $penghuni->id,
            'nama_penghuni' => 'Penghuni Uji',
            'no_telepon' => '081234567890',
            'email' => 'penghuni@gmail.com',
            'alamat' => 'Cirebon',
        ]);
        // 4. Data kos
        $this->call([
            KostSeeder::class,
            KamarSeeder::class,
            PenghuniSeeder::class,
            PenghunianSeeder::class,
        ]);
    }
}