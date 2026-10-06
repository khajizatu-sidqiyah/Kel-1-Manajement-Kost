<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PemilikSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('pemilik')->insert([
            [
                'id_pemilik' => 1,
                'nama_pemilik' => 'Ibu Cahaya',
                'email' => 'pemilik@gmail.com',
                'no_telepon' => '081234567890',
                'alamat' => 'Cirebon',
            ],
        ]);
    }
}