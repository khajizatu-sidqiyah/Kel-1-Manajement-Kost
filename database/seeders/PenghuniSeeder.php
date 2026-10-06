<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PenghuniSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('penghuni')->insert([
            [
                'id_penghuni' => 2,
                'nama_penghuni' => 'Teh Erin',
                'no_telepon' => '+62 853-1852-8347',
                'email' => 'erin@gmail.com',
                'alamat' => 'Cirebon',
            ],
            [
                'id_penghuni' => 3,
                'nama_penghuni' => 'Teh Azizah',
                'no_telepon' => '+62 896-7788-3738',
                'email' => 'azizah@gmail.com',
                'alamat' => 'Cirebon',
            ],
            [
                'id_penghuni' => 4,
                'nama_penghuni' => 'Aca',
                'no_telepon' => '+62 838-3285-0093',
                'email' => 'aca@gmail.com',
                'alamat' => 'Cirebon',
            ],
            [
                'id_penghuni' => 5,
                'nama_penghuni' => 'Risma',
                'no_telepon' => '+62 822-4260-0038',
                'email' => 'risma@gmail.com',
                'alamat' => 'Cirebon',
            ],
            [
                'id_penghuni' => 6,
                'nama_penghuni' => 'Syifa',
                'no_telepon' => '+62 895-6350-08600',
                'email' => 'syifa@gmail.com',
                'alamat' => 'Cirebon',
            ],
            [
                'id_penghuni' => 7,
                'nama_penghuni' => 'Indah',
                'no_telepon' => '+62 831-2016-4396',
                'email' => 'indah@gmail.com',
                'alamat' => 'Cirebon',
            ],
            [
                'id_penghuni' => 8,
                'nama_penghuni' => 'Nunu',
                'no_telepon' => '+62 838-7955-5420',
                'email' => 'nunu@gmail.com',
                'alamat' => 'Kalibuntu',
            ],
            [
                'id_penghuni' => 9,
                'nama_penghuni' => 'Syahra',
                'no_telepon' => '+62 823-1656-0422',
                'email' => 'syahra@gmail.com',
                'alamat' => 'Cirebon',
            ],
            [
                'id_penghuni' => 10,
                'nama_penghuni' => 'Nunun',
                'no_telepon' => '+62 812-1414-5457',
                'email' => 'nunun@gmail.com',
                'alamat' => 'Cirebon',
            ],
            [
                'id_penghuni' => 11,
                'nama_penghuni' => 'Jijah',
                'no_telepon' => '+62 838-2524-8419',
                'email' => 'jijah@gmail.com',
                'alamat' => 'Kalibuntu',
            ],
            [
                'id_penghuni' => 12,
                'nama_penghuni' => 'Elza',
                'no_telepon' => '+62 838-4146-0957',
                'email' => 'elza@gmail.com',
                'alamat' => 'Losari',
            ],
            [
                'id_penghuni' => 13,
                'nama_penghuni' => 'Salsa',
                'no_telepon' => '+62 831-2919-9063',
                'email' => 'salsa@gmail.com',
                'alamat' => 'Cirebon',
            ],
            [
                'id_penghuni' => 14,
                'nama_penghuni' => 'Dyas',
                'no_telepon' => '+62 822-8678-2116',
                'email' => 'dyas@gmail.com',
                'alamat' => 'Cirebon',
            ],
        ]);
    }
}