<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
}

    public function down(): void
    {
        // Tabel dan data konfigurasi dipertahankan.
    }
};