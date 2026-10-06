<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KostSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('kos')->insert([
            [
                'id_kost' => 1,
                'nama_kost' => 'Kost Putri Cirebon',
                'alamat' => 'Cirebon',
                'deskripsi' => 'Kost putri dengan fasilitas yang nyaman dan aman.',
                'fasilitas' => 'WiFi, Kamar Mandi Luar, Kamar Mandi Dalam, Listrik, Parkir Luas, Dapur Bersama, Halaman Jemur',
                'id_pemilik' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}