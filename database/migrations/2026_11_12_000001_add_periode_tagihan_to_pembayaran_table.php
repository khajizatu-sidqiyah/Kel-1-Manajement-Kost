<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('pembayaran', 'periode_tagihan')) {
            Schema::table('pembayaran', function (Blueprint $table) {
                $table->date('periode_tagihan')->nullable()->after('tanggal_bayar');
            });
        }

        // Data lama untuk sewa bulanan diisi berdasarkan bulan tanggal bayar.
        // Data tahunan tetap NULL karena tidak memakai siklus tagihan bulanan.
        $pembayaranLama = DB::table('pembayaran')
            ->join('penghunian', 'pembayaran.id_penghunian', '=', 'penghunian.id_penghunian')
            ->where('penghunian.skema_sewa', 'bulanan')
            ->whereNull('pembayaran.periode_tagihan')
            ->select('pembayaran.id_pembayaran', 'pembayaran.tanggal_bayar')
            ->get();

        foreach ($pembayaranLama as $pembayaran) {
            if ($pembayaran->tanggal_bayar) {
                DB::table('pembayaran')
                    ->where('id_pembayaran', $pembayaran->id_pembayaran)
                    ->update([
                        'periode_tagihan' => Carbon::parse($pembayaran->tanggal_bayar)
                            ->startOfMonth()
                            ->toDateString(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pembayaran', 'periode_tagihan')) {
            Schema::table('pembayaran', function (Blueprint $table) {
                $table->dropColumn('periode_tagihan');
            });
        }
    }
};
