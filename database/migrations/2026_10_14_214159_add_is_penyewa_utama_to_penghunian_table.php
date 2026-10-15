<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('penghunian', 'is_penyewa_utama')) {
            Schema::table('penghunian', function (Blueprint $table) {
                $table->boolean('is_penyewa_utama')
                    ->default(false);
            });
        }

        // Tetapkan satu penyewa utama untuk kamar
        // yang belum memiliki penyewa utama.
        $kamarAktif = DB::table('penghunian')
            ->where('status', 'aktif')
            ->orderBy('id_penghunian')
            ->get()
            ->groupBy('id_kamar');

        foreach ($kamarAktif as $daftar) {
            $sudahAda = $daftar->contains(
                fn ($item) => (bool) $item->is_penyewa_utama
            );

            if (!$sudahAda) {
                $pertama = $daftar->first();

                DB::table('penghunian')
                    ->where('id_penghunian', $pertama->id_penghunian)
                    ->update(['is_penyewa_utama' => true]);
            }
        }
    }

    public function down(): void
    {
        // Kolom mungkin sudah ada sebelum migration ini.
        // Jangan hapus kolom yang mungkin digunakan fitur lain.
    }
};