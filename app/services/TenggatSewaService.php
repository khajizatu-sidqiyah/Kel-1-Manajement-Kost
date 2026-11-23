<?php

namespace App\Services;

use App\Models\Penghunian;
use App\Models\Tagihan;
use Carbon\Carbon;

class TenggatSewaService
{
    /**
     * Menghitung tenggat berikutnya dengan mempertahankan
     * tanggal patokan awal.
     *
     * Contoh:
     * 31 Januari -> 28 Februari -> 31 Maret.
     */
    public function hitungBerikutnya($tanggalTenggat): string
    {
        $tanggal = Carbon::parse($tanggalTenggat);
        $bulanBerikutnya = $tanggal->copy()
            ->startOfMonth()
            ->addMonthNoOverflow();

        $hari = min($tanggal->day, $bulanBerikutnya->daysInMonth);

        return $bulanBerikutnya
            ->day($hari)
            ->toDateString();
    }

    /**
     * Tenggat hanya maju jika tagihan bulanan pada periode
     * tersebut benar-benar sudah lunas.
     *
     * Tanggal patokan diambil dari tagihan bulanan pertama,
     * bukan dari tenggat yang sudah pernah disesuaikan.
     */
    public function majukanJikaLunas(
        $penghunian,
        string $periodeTagihan,
        float $totalTerverifikasi = 0
    ): ?string {
        if (
            $penghunian->skema_sewa !== 'bulanan' ||
            !$penghunian->tenggat_pelunasan
        ) {
            return null;
        }

        $tagihanDibayar = Tagihan::where(
            'id_penghunian',
            $penghunian->id_penghunian
        )
            ->whereDate('periode', Carbon::parse($periodeTagihan)
                ->startOfMonth()
                ->toDateString())
            ->where('jenis_tagihan', 'bulanan')
            ->first();

        // Jangan memajukan tenggat hanya berdasarkan jumlah transaksi.
        if (!$tagihanDibayar || $tagihanDibayar->status !== 'sudah_lunas') {
            return null;
        }

        $tagihanPertama = Tagihan::where(
            'id_penghunian',
            $penghunian->id_penghunian
        )
            ->where('jenis_tagihan', 'bulanan')
            ->orderBy('jatuh_tempo')
            ->orderBy('id_tagihan')
            ->first();

        if (!$tagihanPertama || !$tagihanPertama->jatuh_tempo) {
            return null;
        }

        $tanggalPatokan = Carbon::parse($tagihanPertama->jatuh_tempo);
        $periodeAwal = $tanggalPatokan->copy()->startOfMonth();
        $periodeDibayar = Carbon::parse($periodeTagihan)->startOfMonth();

        // Hitung selisih bulan berdasarkan tahun dan bulan.
        $indeksAwal = ($periodeAwal->year * 12) + $periodeAwal->month;
        $indeksDibayar = ($periodeDibayar->year * 12) + $periodeDibayar->month;
        $selisihBulan = $indeksDibayar - $indeksAwal;

        if ($selisihBulan < 0) {
            return null;
        }

        // Tenggat berikutnya dihitung dari tanggal patokan asli.
        $bulanBerikutnya = $periodeAwal
            ->copy()
            ->addMonthsNoOverflow($selisihBulan + 1);

        $hariPatokan = $tanggalPatokan->day;
        $hariBerikutnya = min(
            $hariPatokan,
            $bulanBerikutnya->daysInMonth
        );

        $tenggatBaru = $bulanBerikutnya
            ->day($hariBerikutnya)
            ->toDateString();

        $tenggatSekarang = Carbon::parse(
            $penghunian->tenggat_pelunasan
        )->toDateString();

        // Mencegah tenggat mundur atau diperbarui berulang kali.
        if ($tenggatBaru <= $tenggatSekarang) {
            return null;
        }

        $penghunian->update([
            'tenggat_pelunasan' => $tenggatBaru,
            'status_pembayaran' => 'belum_bayar',
        ]);

        return $tenggatBaru;
    }
}
