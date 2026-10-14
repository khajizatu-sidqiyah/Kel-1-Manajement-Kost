<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('penghuni', 'is_aktif')) {
            Schema::table('penghuni', function (Blueprint $table) {
                $table->boolean('is_aktif')->default(true);
            });
        }

        if (!Schema::hasColumn('penghunian', 'is_penyewa_utama')) {
            Schema::table('penghunian', function (Blueprint $table) {
                $table->boolean('is_penyewa_utama')->default(false);
                $table->index(['id_kamar', 'status', 'is_penyewa_utama'], 'penghunian_primary_lookup');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('penghunian', 'is_penyewa_utama')) {
            Schema::table('penghunian', function (Blueprint $table) {
                $table->dropIndex('penghunian_primary_lookup');
                $table->dropColumn('is_penyewa_utama');
            });
        }
        if (Schema::hasColumn('penghuni', 'is_aktif')) {
            Schema::table('penghuni', function (Blueprint $table) {
                $table->dropColumn('is_aktif');
            });
        }
    }
};
