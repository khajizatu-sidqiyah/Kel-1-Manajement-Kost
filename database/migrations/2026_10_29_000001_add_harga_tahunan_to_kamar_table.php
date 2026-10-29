<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('kamar', 'harga_tahunan')) {
            Schema::table('kamar', function (Blueprint $table) {
                $table->decimal('harga_tahunan', 12, 2)->nullable()->after('harga');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('kamar', 'harga_tahunan')) {
            Schema::table('kamar', function (Blueprint $table) {
                $table->dropColumn('harga_tahunan');
            });
        }
    }
};
