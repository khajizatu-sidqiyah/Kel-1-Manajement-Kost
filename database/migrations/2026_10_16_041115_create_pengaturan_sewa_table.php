<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
{
    if (!Schema::hasTable('pengaturan_sewa')) {
        Schema::create('pengaturan_sewa', function (Blueprint $table) {
            $table->id('id_pengaturan');
            $table->decimal('dp_minimum_tahunan', 12, 2)
                ->default(300000);
            $table->timestamps();
        });
    }

    // Nilai awal konfigurasi; pemilik tetap dapat mengubahnya dari menu Pengaturan Sewa.
    if (DB::table('pengaturan_sewa')->count() === 0) {
        DB::table('pengaturan_sewa')->insert([
            'dp_minimum_tahunan' => 300000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

    public function down(): void
    {
        // Tabel dan data konfigurasi dipertahankan.
    }
};