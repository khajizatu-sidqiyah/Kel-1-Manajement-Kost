<?php

namespace App\Services;

use Carbon\Carbon;

class TenggatSewaService
{
    /**
     * Menghitung tenggat bulan berikutnya.
     * Tanggal 29, 30, dan 31 menjadi hari terakhir
     * pada bulan berikutnya.
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
     * Tenggat hanya maju jika tagihan periode tersebut sudah lunas.
     */
    public function majukanJikaLunas(
        $penghunian,
        string $periodeTagihan,
        float $totalTerverifikasi
    ): ?string {
        if (
            $penghunian->skema_sewa !== 'bulanan' ||
            !$penghunian->tenggat_pelunasan
        ) {
            return null;
        }

        $periode = Carbon::parse($periodeTagihan)->startOfMonth();
        $tenggat = Carbon::parse($penghunian->tenggat_pelunasan);

        if ($tenggat->format('Y-m') !== $periode->format('Y-m')) {
            return null;
        }

        $totalTagihan = (float) ($penghunian->total_sewa ?? 0);

        if (
            $totalTagihan <= 0 ||
            $totalTerverifikasi < $totalTagihan
        ) {
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