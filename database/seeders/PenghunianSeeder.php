<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PenghunianSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('penghunian')->insert([
            // Kamar 2 - Teh Erin
            [
                'id_kamar' => 2,
                'id_penghuni' => 2,
                'tanggal_mulai' => '2020-07-13',
                'tanggal_selesai' => null,
                'status' => 'aktif',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Kamar 4 - Teh Azizah
            [
                'id_kamar' => 2,
                'id_penghuni' => 3,
                'tanggal_mulai' => '2024-08-25',
                'tanggal_selesai' => null,
                'status' => 'aktif',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Kamar 5 - Aca
            [
                'id_kamar' => 5,
                'id_penghuni' => 4,
                'tanggal_mulai' => '2026-08-22',
                'tanggal_selesai' => null,
                'status' => 'aktif',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Kamar 5 - Risma
            [
                'id_kamar' => 5,
                'id_penghuni' => 5,
                'tanggal_mulai' => '2026-08-22',
                'tanggal_selesai' => null,
                'status' => 'aktif',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Kamar 6 - Syifa
            [
                'id_kamar' => 6,
                'id_penghuni' => 6,
                'tanggal_mulai' => '2026-08-17',
                'tanggal_selesai' => null,
                'status' => 'aktif',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Kamar 8 - Indah
            [
                'id_kamar' => 8,
                'id_penghuni' => 7,
                'tanggal_mulai' => '2026-08-15',
                'tanggal_selesai' => null,
                'status' => 'aktif',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Kamar 8 - Nunu
            [
                'id_kamar' => 8,
                'id_penghuni' => 8,
                'tanggal_mulai' => '2026-08-15',
                'tanggal_selesai' => null,
                'status' => 'aktif',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Kamar 12 - Syahra
            [
                'id_kamar' => 12,
                'id_penghuni' => 9,
                'tanggal_mulai' => '2025-08-20',
                'tanggal_selesai' => null,
                'status' => 'aktif',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Kamar 12 - Nunun
            [
                'id_kamar' => 12,
                'id_penghuni' => 10,
                'tanggal_mulai' => '2025-08-20',
                'tanggal_selesai' => null,
                'status' => 'aktif',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Kamar 13 - Jijah
            [
                'id_kamar' => 13,
                'id_penghuni' => 11,
                'tanggal_mulai' => '2024-08-25',
                'tanggal_selesai' => null,
                'status' => 'aktif',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Kamar 13 - Elza
            [
                'id_kamar' => 13,
                'id_penghuni' => 12,
                'tanggal_mulai' => '2024-08-25',
                'tanggal_selesai' => null,
                'status' => 'aktif',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Kamar 22 - Salsa
            [
                'id_kamar' => 22,
                'id_penghuni' => 13,
                'tanggal_mulai' => '2026-09-30',
                'tanggal_selesai' => null,
                'status' => 'aktif',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Kamar 23 - Dyas
            [
                'id_kamar' => 23,
                'id_penghuni' => 14,
                'tanggal_mulai' => '2025-08-20',
                'tanggal_selesai' => null,
                'status' => 'aktif',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
