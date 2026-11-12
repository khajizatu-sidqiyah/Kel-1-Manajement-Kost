<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kamar', function (Blueprint $table) {
            if (!Schema::hasColumn('kamar', 'harga_tahunan_1_orang')) {
                $table->decimal('harga_tahunan_1_orang', 12, 2)->nullable()->after('harga_tahunan');
            }

            if (!Schema::hasColumn('kamar', 'harga_tahunan_2_orang')) {
                $table->decimal('harga_tahunan_2_orang', 12, 2)->nullable()->after('harga_tahunan_1_orang');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kamar', function (Blueprint $table) {
            if (Schema::hasColumn('kamar', 'harga_tahunan_2_orang')) {
                $table->dropColumn('harga_tahunan_2_orang');
            }

            if (Schema::hasColumn('kamar', 'harga_tahunan_1_orang')) {
                $table->dropColumn('harga_tahunan_1_orang');
            }
        });
    }
};
