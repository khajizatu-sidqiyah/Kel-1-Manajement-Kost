
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
{
    if (!Schema::hasColumn('penghunian', 'penyewa_utama')) {
        Schema::table('penghunian', function (Blueprint $table) {
            $table->boolean('penyewa_utama')->default(false);
        });
    }

    $kamarAktif = DB::table('penghunian')
        ->whereRaw('LOWER(status) = ?', ['aktif'])
        ->orderBy('id_penghunian')
        ->get()
        ->groupBy('id_kamar');

    foreach ($kamarAktif as $daftar) {
        $sudahAda = $daftar->contains(
            fn ($item) => (bool) $item->penyewa_utama
        );

        if (!$sudahAda) {
            $pertama = $daftar->first();

            DB::table('penghunian')
                ->where('id_penghunian', $pertama->id_penghunian)
                ->update(['penyewa_utama' => true]);
        }
    }
}
    public function down(): void
    {
        // Tidak menghapus kolom agar aman bagi data dan fitur lain.
    }
};