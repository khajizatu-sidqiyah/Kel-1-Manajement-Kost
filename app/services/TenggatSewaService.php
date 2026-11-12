<?php

namespace App\Services;

use Carbon\Carbon;

class TenggatSewaService
{
    /**
     * Menghitung tenggat bulan berikutnya.
     * Tanggal 29, 30, dan 31 selalu menjadi hari terakhir
     * pada bulan berikutnya sesuai keputusan pemilik kos.
     */
    public function hitungBerikutnya($tanggalTenggat): string
    {
        $tanggal = Carbon::parse($tanggalTenggat);

        if ($tanggal->day >= 29) {
            return $tanggal
                ->copy()
                ->startOfMonth()
                ->addMonthNoOverflow()
                ->endOfMonth()
                ->toDateString();
        }

        return $tanggal
            ->copy()
            ->addMonthNoOverflow()
            ->toDateString();
    }

    /**
     * Memajukan tenggat hanya jika periode tersebut sudah lunas
     * dan tenggat saat ini masih berada pada bulan periode itu.
     * Pengecekan bulan mencegah pemrosesan pembayaran yang sama
     * memajukan tenggat lebih dari satu kali.
     */
    public function majukanJikaLunas($penghunian, string $periodeTagihan, float $totalTerverifikasi): ?string
    {
        if ($penghunian->skema_sewa !== 'bulanan' || !$penghunian->tenggat_pelunasan) {
            return null;
        }

        $periode = Carbon::parse($periodeTagihan)->startOfMonth();
        $tenggat = Carbon::parse($penghunian->tenggat_pelunasan);

        if ($tenggat->format('Y-m') !== $periode->format('Y-m')) {
            return null;
        }

        $totalTagihan = (float) ($penghunian->total_sewa ?? 0);

        if ($totalTagihan <= 0 || $totalTerverifikasi < $totalTagihan) {
            return null;
        }

        $tenggatBaru = $this->hitungBerikutnya($tenggat);

        $penghunian->update([
            'tenggat_pelunasan' => $tenggatBaru,
            'status_pembayaran' => 'belum_lunas',
        ]);

        return $tenggatBaru;
    }
}
