<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
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
            'tenggat_pelunasan' => '2027-01-05',
            'status_pembayaran' => 'belum_lunas',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Helper request pembayaran.
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
                'error.status',
                'DP_ALREADY_EXISTS'
            );

        $this->assertDatabaseCount('pembayaran', 1);
    }

    /**
     * 3. DP di bawah minimum langsung ditolak.
     */
    public function test_dp_di_bawah_minimum_langsung_ditolak(): void
    {
        $id = $this->buatPenghunianPembayaran(
            totalSewa: 5000000,
            dpMinimum: 500000
        );

        $this->bayar($id, 'dp', 200000)
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.status',
                'DP_BELOW_MINIMUM'
            );

        $this->assertDatabaseCount('pembayaran', 0);
    }

    /**
     * 4. Nominal DP nol dan negatif harus ditolak.
     */
    public function test_dp_nol_dan_negatif_ditolak(): void
    {
        $id = $this->buatPenghunianPembayaran();

        $this->bayar($id, 'dp', 0)
            ->assertUnprocessable();

        $this->bayar($id, 'dp', -100000)
            ->assertUnprocessable();

        $this->assertDatabaseCount('pembayaran', 0);
    }

    /**
     * 5. Pembayaran yang melebihi sisa tagihan ditolak.
     */
    public function test_pembayaran_melebihi_sisa_tagihan_ditolak(): void
    {
        $id = $this->buatPenghunianPembayaran();

        $this->bayar($id, 'dp', 300000)
            ->assertCreated();

        $this->bayar($id, 'cicilan', 4700001)
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.status',
                'PAYMENT_EXCEEDS_REMAINING'
            );

        $this->assertDatabaseCount('pembayaran', 1);
    }

    /**
     * 6. Cicilan hanya tersedia untuk skema tahunan.
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
                'error.status',
                'INSTALLMENT_NOT_ALLOWED'
            );

        $this->assertDatabaseCount('pembayaran', 0);
    }

    /**
     * 7. Cicilan tahunan mengurangi sisa tagihan.
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
                'data.status_pembayaran',
                'belum_lunas'
            );

        $totalTerbayar = DB::table('pembayaran')
            ->where('id_penghunian', $id)
            ->where('status', 'terverifikasi')
            ->sum('jumlah');

        $sisaTagihan = 5000000 - $totalTerbayar;

        $this->assertEquals(2300000, $totalTerbayar);
        $this->assertEquals(2700000, $sisaTagihan);

        $this->assertDatabaseHas('penghunian', [
            'id_penghunian' => $id,
            'status_pembayaran' => 'belum_lunas',
        ]);
    }

    /**
     * 8. Pembayaran terakhir mengubah status menjadi lunas.
     */
    public function test_pembayaran_terakhir_mengubah_status_menjadi_lunas(): void
    {
        $id = $this->buatPenghunianPembayaran();

        $this->bayar($id, 'dp', 4900000)
            ->assertCreated();

        $this->bayar($id, 'pelunasan', 100000)
            ->assertCreated()
            ->assertJsonPath(
                'data.status_pembayaran',
                'lunas'
            );

        $totalTerbayar = DB::table('pembayaran')
            ->where('id_penghunian', $id)
            ->where('status', 'terverifikasi')
            ->sum('jumlah');

        $this->assertEquals(5000000, $totalTerbayar);

        $this->assertDatabaseHas('penghunian', [
            'id_penghunian' => $id,
            'status_pembayaran' => 'lunas',
        ]);
    }

    /**
     * 9. Pembayaran pada penghunian yang tidak aktif ditolak.
     */
    public function test_pembayaran_penghunian_tidak_aktif_ditolak(): void
    {
        $id = $this->buatPenghunianPembayaran(
            status: 'selesai'
        );

        $this->bayar($id, 'dp', 300000)
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.status',
                'OCCUPANCY_NOT_ACTIVE'
            );

        $this->assertDatabaseCount('pembayaran', 0);
    }

    /**
     * 10. DP ditolak jika skema sewa belum dipilih.
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
                'error.status',
                'RENT_SCHEME_NOT_SET'
            );

        $this->assertDatabaseCount('pembayaran', 0);
    }

    /**
     * 11. Pembayaran dari penghuni kedua tetap dicatat
     *     atas nama penyewa utama.
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
     * 12. Satu penghuni otomatis menjadi penyewa utama.
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
     * 13. Skema tahunan menghasilkan tenggat dua bulan
     *     setelah tanggal masuk.
     */
    public function test_hitung_skema_tahunan_menghasilkan_tenggat_dua_bulan(): void
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
                300000
            );

        $penghunian = DB::table('penghunian')
            ->where('id_penghunian', $id)
            ->first();

        $this->assertEquals(
            'tahunan',
            $penghunian->skema_sewa
        );

        $this->assertEquals(
            '2027-01-05',
            Carbon::parse(
                $penghunian->tenggat_pelunasan
            )->toDateString()
        );
    }

    /**
     * 14. Jenis pembayaran yang tidak dikenal ditolak.
     */
    public function test_jenis_pembayaran_tidak_valid_ditolak(): void
    {
        $id = $this->buatPenghunianPembayaran();

        $this->bayar($id, 'angsuran-aneh', 100000)
            ->assertUnprocessable();

        $this->assertDatabaseCount('pembayaran', 0);
    }

    /**
     * 15. DP tidak boleh melebihi total sewa.
     */
    public function test_dp_melebihi_total_sewa_ditolak(): void
    {
        $id = $this->buatPenghunianPembayaran(
            totalSewa: 5000000,
            dpMinimum: 300000
        );

        $this->bayar($id, 'dp', 5000001)
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.status',
                'DP_EXCEEDS_RENT'
            );

        $this->assertDatabaseCount('pembayaran', 0);
    }

    /**
     * 16. DP bulanan minimal 50% harga kamar.
     */
    public function test_dp_bulanan_di_bawah_50_persen_ditolak(): void
    {
        $id = $this->buatPenghunianPembayaran(
            totalSewa: 1000000,
            dpMinimum: 500000,
            skema: 'bulanan'
        );

        $this->bayar($id, 'dp', 400000)
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.status',
                'DP_BELOW_MINIMUM'
            );

        $this->assertDatabaseCount('pembayaran', 0);
    }

    /**
     * 17. Cicilan tidak boleh menghabiskan seluruh sisa tagihan.
     */
    public function test_cicilan_yang_menghabiskan_sisa_tagihan_ditolak(): void
    {
        $id = $this->buatPenghunianPembayaran();

        $this->bayar($id, 'dp', 300000)
            ->assertCreated();

        $this->bayar($id, 'cicilan', 4700000)
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.status',
                'INVALID_INSTALLMENT_AMOUNT'
            );

        $this->assertDatabaseCount('pembayaran', 1);
    }

    /**
     * 18. Pelunasan harus sama dengan sisa tagihan.
     */
    public function test_pelunasan_harus_sesuai_sisa_tagihan(): void
    {
        $id = $this->buatPenghunianPembayaran();

        $this->bayar($id, 'dp', 300000)
            ->assertCreated();

        // Kurang dari sisa tagihan.
        $this->bayar($id, 'pelunasan', 4699999)
            ->assertUnprocessable();

        // Lebih dari sisa tagihan.
        $this->bayar($id, 'pelunasan', 4700001)
            ->assertUnprocessable();

        $this->assertDatabaseCount('pembayaran', 1);

        $this->assertDatabaseHas('penghunian', [
            'id_penghunian' => $id,
            'status_pembayaran' => 'belum_lunas',
        ]);
    }

    /**
     * 19. Tanggal bayar boleh tidak dikirim.
     */
    public function test_tanggal_bayar_boleh_tidak_dikirim(): void
    {
        $id = $this->buatPenghunianPembayaran();

        $this->withoutMiddleware()->postJson(
            '/api/pembayaran',
            [
                'id_penghunian' => $id,
                'jenis_pembayaran' => 'dp',
                'jumlah' => 300000,
                'metode_pembayaran' => 'transfer',
            ]
        )->assertCreated();

        $this->assertDatabaseHas('pembayaran', [
            'id_penghunian' => $id,
            'jenis_pembayaran' => 'dp',
            'tanggal_bayar' => null,
        ]);
    }

    /**
     * 20. Tanggal bayar boleh bernilai null.
     */
    public function test_tanggal_bayar_boleh_null(): void
    {
        $id = $this->buatPenghunianPembayaran();

        $this->withoutMiddleware()->postJson(
            '/api/pembayaran',
            [
                'id_penghunian' => $id,
                'jenis_pembayaran' => 'dp',
                'jumlah' => 300000,
                'tanggal_bayar' => null,
                'metode_pembayaran' => 'transfer',
            ]
        )->assertCreated();

        $this->assertDatabaseHas('pembayaran', [
            'id_penghunian' => $id,
            'tanggal_bayar' => null,
        ]);
    }
}
