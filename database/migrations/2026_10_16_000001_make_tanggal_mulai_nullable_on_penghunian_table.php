<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penghunian', function (Blueprint $table) {
            $table->date('tanggal_mulai')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Isi tanggal kosong sebelum mengembalikan kolom menjadi wajib.
        DB::table('penghunian')
            ->whereNull('tanggal_mulai')
            ->update(['tanggal_mulai' => now()->toDateString()]);

        Schema::table('penghunian', function (Blueprint $table) {
            $table->date('tanggal_mulai')->nullable(false)->change();
        });
    }
};
