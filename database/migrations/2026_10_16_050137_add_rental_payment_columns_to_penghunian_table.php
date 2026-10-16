<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */

public function up(): void
{
    Schema::table('penghunian', function (Blueprint $table) {
        if (!Schema::hasColumn('penghunian', 'skema_sewa')) {
            $table->string('skema_sewa')->nullable();
        }

        if (!Schema::hasColumn('penghunian', 'total_sewa')) {
            $table->decimal('total_sewa', 12, 2)->nullable();
        }

        if (!Schema::hasColumn('penghunian', 'dp_minimum')) {
            $table->decimal('dp_minimum', 12, 2)->nullable();
        }

        if (!Schema::hasColumn('penghunian', 'tenggat_pelunasan')) {
            $table->date('tenggat_pelunasan')->nullable();
        }
    });
}

public function down(): void
{
    // Sengaja tidak menghapus kolom untuk melindungi data proyek.
}
};