<?php

namespace tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PembayaranTest extends TestCase
{
    use RefreshDatabase;

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

    private function bayar(
        int $id,
        string $jenis,
        float $jumlah,
        string $metode = 'transfer'
    ) {
        return $this->withoutMiddleware()->postJson('/api/pembayaran', [
            'id_penghunian' => $id,
            'jenis_pembayaran' => $jenis,
            'jumlah' => $jumlah,
            'tanggal_bayar' => '2026-11-05',
            'metode_pembayaran' => $metode,
        ]);
    }

    public function test_dp_berhasil_dicatat(): void
    {
        $id = $this->buatPenghunianPembayaran();

        $response = $this->bayar($id, 'dp', 300000);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.jenis_pembayaran',
                'dp'
            )
            ->assertJsonPath(
                'data.status',
                'menunggu_verifikasi'
            );

        $this->assertDatabaseHas('pembayaran', [
            'id_penghunian' => $id,
            'jenis_pembayaran' => 'dp',
            'jumlah' => 300000,
            'status' => 'menunggu_verifikasi',
        ]);
    }

    public function test_dp_kedua_untuk_penghunian_yang_sama_ditolak(): void
    {
        $id = $this->buatPenghunianPembayaran();

        $this->bayar($id, 'dp', 300000)
            ->assertCreated();

        $this->bayar($id, 'dp', 300000)
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'DP untuk penghunian ini sudah pernah dicatat.'
            );

        $this->assertDatabaseCount('pembayaran', 1);
    }

    public function test_dp_di_bawah_minimum_mendapat_peringatan(): void
    {
        $id = $this->buatPenghunianPembayaran(
            totalSewa: 5000000,
            dpMinimum: 500000
        );

        $this->bayar($id, 'dp', 300000)
            ->assertCreated()
            ->assertJsonPath(
                'peringatan',
                'DP di bawah minimum Rp500.000.'
            );

        $this->assertDatabaseHas('pembayaran', [
            'id_penghunian' => $id,
            'jenis_pembayaran' => 'dp',
            'jumlah' => 300000,
        ]);
    }

    public function test_pembayaran_nol_ditolak(): void
    {
        $id = $this->buatPenghunianPembayaran();

        $this->bayar($id, 'dp', 0)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['jumlah']);

        $this->assertDatabaseCount('pembayaran', 0);
    }

    public function test_pembayaran_negatif_ditolak(): void
    {
        $id = $this->buatPenghunianPembayaran();

        $this->bayar($id, 'dp', -100000)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['jumlah']);

        $this->assertDatabaseCount('pembayaran', 0);
    }

    public function test_pembayaran_melebihi_total_sewa_ditolak(): void
    {
        $id = $this->buatPenghunianPembayaran();

        $this->bayar($id, 'dp', 5000001)
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Jumlah pembayaran melebihi sisa tagihan.'
            );

        $this->assertDatabaseCount('pembayaran', 0);
    }

    public function test_cicilan_hanya_tersedia_untuk_skema_tahunan(): void
    {
        $id = $this->buatPenghunianPembayaran(
            skema: 'bulanan'
        );

        $this->bayar($id, 'cicilan', 200000)
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Jenis pembayaran cicilan hanya tersedia untuk skema tahunan.'
            );

        $this->assertDatabaseCount('pembayaran', 0);
    }

    public function test_cicilan_tahunan_berhasil_dicatat(): void
    {
        $id = $this->buatPenghunianPembayaran();

        $this->bayar($id, 'cicilan', 2000000)
            ->assertCreated()
            ->assertJsonPath('data.jenis_pembayaran', 'cicilan');

        $this->assertDatabaseHas('pembayaran', [
            'id_penghunian' => $id,
            'jenis_pembayaran' => 'cicilan',
            'jumlah' => 2000000,
            'status' => 'menunggu_verifikasi',
        ]);
    }

    public function test_pembayaran_penghunian_tidak_aktif_ditolak(): void
    {
        $id = $this->buatPenghunianPembayaran(
            status: 'selesai'
        );

        $this->bayar($id, 'dp', 300000)
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Pembayaran hanya dapat dicatat untuk penghunian aktif.'
            );

        $this->assertDatabaseCount('pembayaran', 0);
    }

    public function test_penghunian_tanpa_skema_sewa_ditolak(): void
    {
        $id = $this->buatPenghunianPembayaran();

        DB::table('penghunian')
            ->where('id_penghunian', $id)
            ->update([
                'skema_sewa' => null,
            ]);

        $this->bayar($id, 'dp', 300000)
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Skema sewa penghunian belum ditentukan.'
            );

        $this->assertDatabaseCount('pembayaran', 0);
    }

    public function test_pembayaran_menggunakan_penyewa_utama(): void
    {
        $idUtama = $this->buatPenghunianPembayaran();

        $penghunianUtama = DB::table('penghunian')
            ->where('id_penghunian', $idUtama)
            ->first();

        $idPenghuniKedua = DB::table('penghuni')->insertGetId([
            'nama_penghuni' => 'Sari Test',
            'no_telepon' => '081234567891',
            'email' => null,
            'alamat' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $idKedua = DB::table('penghunian')->insertGetId([
            'id_kamar' => $penghunianUtama->id_kamar,
            'id_penghuni' => $idPenghuniKedua,
            'tanggal_mulai' => '2026-11-05',
            'status' => 'aktif',
            'penyewa_utama' => false,
            'skema_sewa' => 'tahunan',
            'total_sewa' => 5000000,
            'dp_minimum' => 300000,
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