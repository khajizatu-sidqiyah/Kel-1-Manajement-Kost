<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PembayaranTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Membuat data kamar, penghuni, dan penghunian untuk pengujian.
     */
    private function buatPenghunianPembayaran(
        float $totalSewa = 5000000,
        float $dpMinimum = 300000,
        bool $penyewaUtama = true,
        string $status = 'aktif',
        string $skema = 'tahunan'
    ): int {
        $idPemilik = DB::table('pemilik')->insertGetId([
            'nama_pemilik' => 'Pemilik Test',
            'no_telepon' => '08123456789',
            'email' => 'pemilik-pembayaran@test.com',
            'alamat' => 'Alamat Test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $idKost = DB::table('kos')->insertGetId([
            'nama_kost' => 'Kost Pembayaran Test',
            'alamat' => 'Alamat Kost Test',
            'deskripsi' => 'Testing pembayaran',
            'fasilitas' => 'WiFi',
            'id_pemilik' => $idPemilik,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $idKamar = DB::table('kamar')->insertGetId([
            'no_kamar' => 'PAY-01',
            'tipe_kamar' => 'Standard',
            'harga' => $totalSewa,
            'harga_tahunan' => $totalSewa,
            'status' => 'terisi',
            'id_kost' => $idKost,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $idPenghuni = DB::table('penghuni')->insertGetId([
            'nama_penghuni' => 'Rina Test',
            'no_telepon' => '081234567890',
            'email' => null,
            'alamat' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('penghunian')->insertGetId([
            'id_kamar' => $idKamar,
            'id_penghuni' => $idPenghuni,
            'tanggal_mulai' => '2026-11-05',
            'tanggal_selesai' => null,
            'status' => $status,
            'penyewa_utama' => $penyewaUtama,
            'skema_sewa' => $skema,
            'total_sewa' => $totalSewa,
            'dp_minimum' => $dpMinimum,
            'tenggat_pelunasan' => '2027-02-05',
            'status_pembayaran' => 'belum_lunas',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Helper agar seluruh test menggunakan format request yang sama.
     */
    private function bayar(
        int $id,
        string $jenis,
        float $jumlah,
        string $metode = 'transfer',
        array $tambahan = []
    ) {
        return $this->withoutMiddleware()->postJson(
            '/api/pembayaran',
            array_merge([
                'id_penghunian' => $id,
                'jenis_pembayaran' => $jenis,
                'jumlah' => $jumlah,
                'tanggal_bayar' => '2026-11-05',
                'metode_pembayaran' => $metode,
            ], $tambahan)
        );
    }

    /**
     * 1. DP berhasil dicatat atas nama penyewa utama.
     */
    public function test_dp_berhasil_dicatat_atas_nama_penyewa_utama(): void
    {
        $id = $this->buatPenghunianPembayaran();

        $this->bayar($id, 'dp', 300000)
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'DP berhasil dicatat.'
            )
            ->assertJsonPath(
                'data.pembayaran.jenis_pembayaran',
                'dp'
            )
            ->assertJsonPath(
                'data.pembayaran.status',
                'terverifikasi'
            )
            ->assertJsonPath(
                'data.status_pembayaran',
                'belum_lunas'
            );

        $this->assertDatabaseHas('pembayaran', [
            'id_penghunian' => $id,
            'jenis_pembayaran' => 'dp',
            'jumlah' => 300000,
            'status' => 'terverifikasi',
        ]);
    }

    /**
     * 2. DP kedua untuk kamar yang sama harus ditolak.
     */
    public function test_dp_kedua_untuk_kamar_yang_sama_ditolak(): void
    {
        $id = $this->buatPenghunianPembayaran();

        $this->bayar($id, 'dp', 300000)
            ->assertCreated();

        $this->bayar($id, 'dp', 300000)
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'DP_ALREADY_EXISTS'
            );

        $this->assertDatabaseCount('pembayaran', 1);
    }

    /**
     * 3. DP di bawah minimum memberikan peringatan
     *    dan belum disimpan.
     */
    public function test_dp_di_bawah_minimum_langsung_ditolak_tanpa_menyimpan(): void
    {
        $id = $this->buatPenghunianPembayaran(
            totalSewa: 5000000,
            dpMinimum: 500000
        );

        $this->bayar($id, 'dp', 200000)
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'DP_BELOW_MINIMUM'
            )
            ->assertJsonPath(
                'error.dp_minimum',
                500000
            );

        $this->assertDatabaseCount('pembayaran', 0);
    }

    /**
     * 4. DP di bawah minimum dapat disimpan
     *    setelah pemilik memberikan konfirmasi.
     */
    public function test_konfirmasi_dp_tidak_dapat_melewati_batas_minimum(): void
    {
        $id = $this->buatPenghunianPembayaran(
            totalSewa: 5000000,
            dpMinimum: 500000
        );

        $this->bayar(
            $id,
            'dp',
            200000,
            tambahan: [
                'konfirmasi_dp' => true,
            ]
        )->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'DP_BELOW_MINIMUM'
            );

        $this->assertDatabaseCount('pembayaran', 0);
    }

    /**
     * 5. Nominal DP nol dan negatif harus ditolak.
     */
    public function test_dp_nol_dan_negatif_ditolak(): void
    {
        $id = $this->buatPenghunianPembayaran();

        $this->bayar($id, 'dp', 0)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'jumlah',
            ]);

        $this->bayar($id, 'dp', -100000)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'jumlah',
            ]);

        $this->assertDatabaseCount('pembayaran', 0);
    }

    /**
     * 6. Pembayaran yang melebihi sisa tagihan ditolak.
     */
    public function test_pembayaran_melebihi_sisa_tagihan_ditolak(): void
    {
        $id = $this->buatPenghunianPembayaran();

        $this->bayar($id, 'dp', 300000)
            ->assertCreated();

        $this->bayar($id, 'cicilan', 4700001)
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'PAYMENT_EXCEEDS_REMAINING'
            );

        $this->assertDatabaseCount('pembayaran', 1);
    }

    /**
     * 7. Cicilan hanya tersedia untuk skema tahunan.
     */
    public function test_cicilan_hanya_tersedia_untuk_skema_tahunan(): void
    {
        $id = $this->buatPenghunianPembayaran(
            totalSewa: 600000,
            dpMinimum: 300000,
            skema: 'bulanan'
        );

        $this->bayar($id, 'cicilan', 200000)
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'INSTALLMENT_NOT_ALLOWED'
            );

        $this->assertDatabaseCount('pembayaran', 0);
    }

    /**
     * 8. Cicilan tahunan mengurangi sisa tagihan.
     *
     * Total sewa  : Rp5.000.000
     * DP          : Rp300.000
     * Cicilan     : Rp2.000.000
     * Total bayar : Rp2.300.000
     * Sisa        : Rp2.700.000
     */
    public function test_cicilan_tahunan_mengurangi_sisa_tagihan(): void
    {
        $id = $this->buatPenghunianPembayaran();

        $this->bayar($id, 'dp', 300000)
            ->assertCreated();

        $this->bayar($id, 'cicilan', 2000000)
            ->assertCreated()
            ->assertJsonPath(
                'data.total_terbayar',
                2300000
            )
            ->assertJsonPath(
                'data.sisa_tagihan',
                2700000
            )
            ->assertJsonPath(
                'data.status_pembayaran',
                'belum_lunas'
            );

        $this->assertDatabaseHas('penghunian', [
            'id_penghunian' => $id,
            'status_pembayaran' => 'belum_lunas',
        ]);
    }

    /**
     * 9. Pembayaran terakhir mengubah status menjadi lunas.
     */
    public function test_pembayaran_terakhir_mengubah_status_menjadi_lunas(): void
    {
        $id = $this->buatPenghunianPembayaran();

        $this->bayar($id, 'dp', 4900000)
            ->assertCreated();

        $this->bayar($id, 'pelunasan', 100000)
            ->assertCreated()
            ->assertJsonPath(
                'data.sisa_tagihan',
                0
            )
            ->assertJsonPath(
                'data.status_pembayaran',
                'lunas'
            );

        $this->assertDatabaseHas('penghunian', [
            'id_penghunian' => $id,
            'status_pembayaran' => 'lunas',
        ]);
    }

    /**
     * 10. Pembayaran pada penghunian yang tidak aktif ditolak.
     */
    public function test_pembayaran_penghunian_tidak_aktif_ditolak(): void
    {
        $id = $this->buatPenghunianPembayaran(
            status: 'selesai'
        );

        $this->bayar($id, 'dp', 300000)
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'OCCUPANCY_NOT_ACTIVE'
            );

        $this->assertDatabaseCount('pembayaran', 0);
    }

    /**
     * 11. DP ditolak jika skema sewa belum dipilih.
     */
    public function test_dp_ditolak_jika_skema_belum_dipilih(): void
    {
        $id = $this->buatPenghunianPembayaran();

        DB::table('penghunian')
            ->where('id_penghunian', $id)
            ->update([
                'skema_sewa' => null,
                'total_sewa' => null,
                'dp_minimum' => null,
            ]);

        $this->bayar($id, 'dp', 300000)
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.code',
                'RENT_SCHEME_NOT_SET'
            );

        $this->assertDatabaseCount('pembayaran', 0);
    }

    /**
     * 12. Jika kamar dihuni Rina dan Sari,
     *     pembayaran dari penghunian Sari tetap dicatat
     *     atas nama penyewa utama Rina.
     */
    public function test_penghunian_penghuni_kedua_dicatat_ke_penyewa_utama(): void
    {
        $idUtama = $this->buatPenghunianPembayaran();

        $dataUtama = DB::table('penghunian')
            ->where('id_penghunian', $idUtama)
            ->first();

        $idPenghuniKedua = DB::table('penghuni')
            ->insertGetId([
                'nama_penghuni' => 'Sari Test',
                'no_telepon' => '081234567891',
                'email' => null,
                'alamat' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $idKedua = DB::table('penghunian')
            ->insertGetId([
                'id_kamar' => $dataUtama->id_kamar,
                'id_penghuni' => $idPenghuniKedua,
                'tanggal_mulai' => '2026-11-05',
                'status' => 'aktif',
                'penyewa_utama' => false,
                'skema_sewa' => null,
                'total_sewa' => null,
                'dp_minimum' => null,
                'status_pembayaran' => 'belum_lunas',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $this->bayar($idKedua, 'dp', 300000)
            ->assertCreated();

        $this->assertDatabaseHas('pembayaran', [
            'id_penghunian' => $idUtama,
            'jenis_pembayaran' => 'dp',
            'jumlah' => 300000,
        ]);

        $this->assertDatabaseMissing('pembayaran', [
            'id_penghunian' => $idKedua,
        ]);
    }

    /**
     * 13. Jika kamar hanya memiliki satu penghuni,
     *     penghuni otomatis menjadi penyewa utama.
     */
    public function test_satu_penghuni_otomatis_dijadikan_penyewa_utama(): void
    {
        $id = $this->buatPenghunianPembayaran(
            penyewaUtama: false
        );

        $this->bayar($id, 'dp', 300000)
            ->assertCreated();

        $this->assertDatabaseHas('penghunian', [
            'id_penghunian' => $id,
            'penyewa_utama' => true,
        ]);

        $this->assertDatabaseHas('pembayaran', [
            'id_penghunian' => $id,
            'jenis_pembayaran' => 'dp',
        ]);
    }

    /**
     * 14. Tenggat yang diedit pemilik saat mencatat DP tersimpan.
     */
    public function test_tenggat_yang_diedit_saat_dp_disimpan(): void
    {
        $id = $this->buatPenghunianPembayaran();

        $this->bayar(
            $id,
            'dp',
            300000,
            tambahan: [
                'tenggat_pelunasan' => '2027-01-20',
            ]
        )->assertCreated();

        $this->assertDatabaseHas('penghunian', [
            'id_penghunian' => $id,
            'tenggat_pelunasan' => '2027-01-20',
        ]);
    }

    /**
     * 15. Skema tahunan menghasilkan tenggat tiga bulan
     *     setelah tanggal masuk sesuai keputusan terbaru pemilik.
     *
     * Tanggal masuk : 5 November 2026
     * Tenggat       : 5 Februari 2027
     */
    public function test_hitung_skema_tahunan_menghasilkan_tenggat_tiga_bulan(): void
    {
        $id = $this->buatPenghunianPembayaran();

        DB::table('pengaturan_sewa')->insert([
            'dp_minimum_tahunan' => 300000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withoutMiddleware()
            ->postJson(
                "/api/penghunian/{$id}/hitung-dp",
                [
                    'skema_sewa' => 'tahunan',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.skema_sewa',
                'tahunan'
            )
            ->assertJsonPath(
                'data.dp_minimum',
                '300000.00'
            )
            ->assertJsonPath(
                'data.tenggat_pelunasan',
                '2027-02-05'
            );

        $this->assertDatabaseHas('penghunian', [
            'id_penghunian' => $id,
            'skema_sewa' => 'tahunan',
            'tenggat_pelunasan' => '2027-02-05',
        ]);
    }

    /**
     * 16. Jenis pembayaran yang tidak dikenal ditolak.
     */
    public function test_jenis_pembayaran_tidak_valid_ditolak(): void
    {
        $id = $this->buatPenghunianPembayaran();

        $this->bayar($id, 'angsuran-aneh', 100000)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'jenis_pembayaran',
            ]);

        $this->assertDatabaseCount('pembayaran', 0);
    }
}