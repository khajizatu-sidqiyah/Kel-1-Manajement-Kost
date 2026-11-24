<?php

namespace App\Services;

use App\Models\Tagihan;
use App\Models\Penghunian;
use App\Models\Pembayaran;
use App\Models\AlokasiPembayaran;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TagihanService
{
    /**
     * Membuat tagihan secara idempoten.
     * Tagihan yang sudah ada tidak dibuat ulang.
     */

public function terbitkanTagihan(Penghunian $penghunian): void
{
    if (
        $penghunian->status !== 'aktif' ||
        !$penghunian->skema_sewa ||
        (float) $penghunian->total_sewa <= 0
    ) {
        return;
    }

    // Tagihan tahunan tidak mewajibkan tenggat tersedia.
    if ($penghunian->skema_sewa === 'tahunan') {
        $this->terbitkanTagihanTahunan($penghunian);
        return;
    }

    // Tagihan bulanan wajib memiliki tenggat.
    if ($penghunian->skema_sewa !== 'bulanan') {
        return;
    }

    $rawTenggat = $penghunian->getRawOriginal('tenggat_pelunasan');

    if (
        empty($rawTenggat) ||
        str_starts_with((string) $rawTenggat, '0000-00-00')
    ) {
        return;
    }

    try {
        $tanggalValid = Carbon::parse($rawTenggat);

        if ($tanggalValid->year < 1000) {
            return;
        }
    } catch (\Throwable $e) {
        return;
    }

    $this->terbitkanTagihanBulanan($penghunian);
}

    /**
     * Menerbitkan satu tagihan untuk skema sewa tahunan.
     */
    private function terbitkanTagihanTahunan(
        Penghunian $penghunian
    ): void {
        Tagihan::firstOrCreate(
            [
                'id_penghunian' => $penghunian->id_penghunian,
                'kode_periode' => 'tahunan',
            ],
            [
                'jenis_tagihan' => 'tahunan',
                'periode' => null,
                'jatuh_tempo' => $penghunian->tenggat_pelunasan,
                'jumlah_tagihan' => $penghunian->total_sewa,
                'jumlah_terbayar' => 0,
                'sisa_tagihan' => $penghunian->total_sewa,
                'status' => 'belum_bayar',
            ]
        );
    }

    /**
     * Menerbitkan tagihan bulanan.
     *
     * Tagihan berikutnya diterbitkan apabila:
     * 1. Tanggal jatuh tempo tagihan berikutnya sudah tercapai; atau
     * 2. Tagihan sebelumnya sudah lunas.
     *
     * Dengan demikian, tagihan bulan berikutnya dapat tersedia
     * segera setelah tagihan bulan berjalan dilunasi.
     */
    private function terbitkanTagihanBulanan(
        Penghunian $penghunian
    ): void {
        // Cari tagihan pertama sebagai patokan tanggal asli.
        $tagihanPertama = Tagihan::where(
            'id_penghunian',
            $penghunian->id_penghunian
        )
            ->orderBy('jatuh_tempo')
            ->orderBy('id_tagihan')
            ->first();

        // Jika belum ada tagihan, gunakan tenggat penghunian.
        $tanggalPatokan = $tagihanPertama
            ? Carbon::parse($tagihanPertama->jatuh_tempo)
            : Carbon::parse($penghunian->tenggat_pelunasan);

        // Simpan hari asli sebagai patokan.
        $hariPatokan = $tanggalPatokan->day;

        // Buat tagihan pertama jika belum tersedia.
        if (!$tagihanPertama) {
            $this->buatTagihanBulanan(
                $penghunian,
                $tanggalPatokan
            );
        }

        // Ambil tagihan terakhir yang sudah diterbitkan.
        $tagihanTerakhir = Tagihan::where(
            'id_penghunian',
            $penghunian->id_penghunian
        )
            ->orderByDesc('jatuh_tempo')
            ->orderByDesc('id_tagihan')
            ->first();

        // Pengaman jika tagihan tidak berhasil ditemukan.
        if (!$tagihanTerakhir) {
            return;
        }

        $tanggalHariIni = Carbon::today();

        while ($tagihanTerakhir) {
            // Hitung tanggal jatuh tempo bulan berikutnya.
            $bulanBerikutnya = Carbon::parse(
                $tagihanTerakhir->jatuh_tempo
            )
                ->startOfMonth()
                ->addMonthNoOverflow();

            // Pertahankan tanggal patokan awal.
            // Contoh: 31 Januari -> 28 Februari -> 31 Maret.
            $hariBerikutnya = min(
                $hariPatokan,
                $bulanBerikutnya->daysInMonth
            );

            $tanggalBerikutnya = $bulanBerikutnya
                ->copy()
                ->day($hariBerikutnya);

            // Periksa apakah tenggat bulan berikutnya sudah tercapai.
            $jatuhTempoSudahTercapai = $tanggalBerikutnya->lte(
                $tanggalHariIni
            );

            // Periksa apakah tagihan sebelumnya sudah lunas.

            $jatuhTempoSudahTercapai = $tanggalBerikutnya->lte($tanggalHariIni);

            // Tagihan berikutnya hanya boleh diterbitkan ketika tanggal jatuh
            // temponya sudah tercapai, bukan langsung setelah tagihan sebelumnya lunas.
            if (!$jatuhTempoSudahTercapai) {
                break;
            }

            // Terbitkan tagihan bulan berikutnya.
            $this->buatTagihanBulanan(
                $penghunian,
                $tanggalBerikutnya
            );

            // Ambil kembali tagihan terakhir.
            $tagihanTerakhir = Tagihan::where(
                'id_penghunian',
                $penghunian->id_penghunian
            )
                ->orderByDesc('jatuh_tempo')
                ->orderByDesc('id_tagihan')
                ->first();
        }
    }

    /**
     * Membuat tagihan bulanan berdasarkan tanggal jatuh tempo.
     */
    private function buatTagihanBulanan(
        Penghunian $penghunian,
        Carbon $tanggalTenggat
    ): void {
        $periode = $tanggalTenggat->copy()
            ->startOfMonth()
            ->toDateString();

        $kodePeriode = $tanggalTenggat->format('Y-m');

        Tagihan::firstOrCreate(
            [
                'id_penghunian' => $penghunian->id_penghunian,
                'kode_periode' => $kodePeriode,
            ],
            [
                'jenis_tagihan' => 'bulanan',
                'periode' => $periode,
                'jatuh_tempo' => $tanggalTenggat->toDateString(),
                'jumlah_tagihan' => $penghunian->total_sewa,
                'jumlah_terbayar' => 0,
                'sisa_tagihan' => $penghunian->total_sewa,
                'status' => 'belum_bayar',
            ]
        );
    }

    /**
     * Menghitung tenggat bulan berikutnya.
     */
    private function hitungTenggatBerikutnya(
        Carbon $tanggal
    ): Carbon {
        $tanggal = $tanggal->copy();

        if ($tanggal->day >= 29) {
            return $tanggal
                ->startOfMonth()
                ->addMonthNoOverflow()
                ->endOfMonth();
        }

        return $tanggal->addMonthNoOverflow();
    }

    /**
     * Mengalokasikan pembayaran ke tagihan tertua
     * yang masih memiliki sisa tagihan.
     *
     * Satu pembayaran hanya dialokasikan ke satu tagihan.
     */
    public function alokasikanPembayaran(
        Pembayaran $pembayaran
    ): void {
        DB::transaction(function () use ($pembayaran) {

            $pembayaran = Pembayaran::where(
                'id_pembayaran',
                $pembayaran->id_pembayaran
            )
                ->lockForUpdate()
                ->firstOrFail();

            // Pembayaran yang belum terverifikasi tidak dialokasikan.
            if ($pembayaran->status !== 'terverifikasi') {
                return;
            }

            // Hindari alokasi pembayaran ganda.
            $sudahDialokasikan = AlokasiPembayaran::where(
                'id_pembayaran',
                $pembayaran->id_pembayaran
            )->exists();

            if ($sudahDialokasikan) {
                return;
            }

            // Kunci data penghunian selama proses alokasi.
            $penghunian = Penghunian::where(
                'id_penghunian',
                $pembayaran->id_penghunian
            )
                ->lockForUpdate()
                ->first();

            if (!$penghunian) {
                throw ValidationException::withMessages([
                    'id_penghunian' => 'Data penghunian tidak ditemukan.',
                ]);
            }

            // Pastikan tagihan sudah tersedia.
            $this->terbitkanTagihan($penghunian);

            // Ambil tagihan tertua yang belum lunas.
            $tagihan = Tagihan::where(
                'id_penghunian',
                $penghunian->id_penghunian
            )
                ->where('sisa_tagihan', '>', 0)
                ->orderBy('jatuh_tempo')
                ->orderBy('id_tagihan')
                ->lockForUpdate()
                ->first();

            if (!$tagihan) {
                throw ValidationException::withMessages([
                    'jumlah' => 'Tidak ada tagihan yang masih harus dibayar.',
                ]);
            }

            $jumlahPembayaran = (float) $pembayaran->jumlah;
            $sisaTagihan = (float) $tagihan->sisa_tagihan;

            if ($jumlahPembayaran <= 0) {
                throw ValidationException::withMessages([
                    'jumlah' => 'Jumlah pembayaran harus lebih dari nol.',
                ]);
            }

            // Pembayaran tidak boleh melebihi sisa satu tagihan.
            if ($jumlahPembayaran > $sisaTagihan) {
                throw ValidationException::withMessages([
                    'jumlah' => 'Jumlah pembayaran melebihi sisa tagihan periode ini.',
                ]);
            }

            // Simpan alokasi pembayaran.
            AlokasiPembayaran::create([
                'id_tagihan' => $tagihan->id_tagihan,
                'id_pembayaran' => $pembayaran->id_pembayaran,
                'jumlah_dialokasikan' => $jumlahPembayaran,
            ]);

            // Hitung ulang total pembayaran dari seluruh alokasi.
            $jumlahTerbayar = (float) AlokasiPembayaran::where(
                'id_tagihan',
                $tagihan->id_tagihan
            )->sum('jumlah_dialokasikan');

            $sisaBaru = max(
                0,
                (float) $tagihan->jumlah_tagihan - $jumlahTerbayar
            );

            // Perbarui kondisi tagihan.
            $tagihan->update([
                'jumlah_terbayar' => $jumlahTerbayar,
                'sisa_tagihan' => $sisaBaru,
                'status' => $sisaBaru <= 0
                    ? 'sudah_lunas'
                    : (
                        $jumlahTerbayar > 0
                            ? 'belum_lunas'
                            : 'belum_bayar'
                    ),
            ]);
        });
    }

    /**
     * Membatalkan seluruh alokasi pembayaran.
     *
     * Digunakan ketika pembayaran yang sebelumnya
     * terverifikasi berubah menjadi ditolak.
     */
    public function batalkanAlokasiPembayaran(
        Pembayaran $pembayaran
    ): void {
        DB::transaction(function () use ($pembayaran) {

            $alokasiList = AlokasiPembayaran::where(
                'id_pembayaran',
                $pembayaran->id_pembayaran
            )
                ->lockForUpdate()
                ->get();

            foreach ($alokasiList as $alokasi) {

                $tagihan = Tagihan::where(
                    'id_tagihan',
                    $alokasi->id_tagihan
                )
                    ->lockForUpdate()
                    ->first();

                if (!$tagihan) {
                    continue;
                }

                // Hapus alokasi pembayaran.
                $alokasi->delete();

                // Hitung ulang pembayaran yang masih berlaku.
                $jumlahTerbayar = (float) AlokasiPembayaran::where(
                    'id_tagihan',
                    $tagihan->id_tagihan
                )->sum('jumlah_dialokasikan');

                $sisaBaru = max(
                    0,
                    (float) $tagihan->jumlah_tagihan - $jumlahTerbayar
                );

                // Perbarui kembali status tagihan.
                $tagihan->update([
                    'jumlah_terbayar' => $jumlahTerbayar,
                    'sisa_tagihan' => $sisaBaru,
                    'status' => $sisaBaru <= 0
                        ? 'sudah_lunas'
                        : (
                            $jumlahTerbayar > 0
                                ? 'belum_lunas'
                                : 'belum_bayar'
                        ),
                ]);
            }
        });
    }
}
