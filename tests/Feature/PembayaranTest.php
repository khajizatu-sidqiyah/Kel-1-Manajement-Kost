<?php

namespace Tests\Feature;

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
        string $status = 'aktif'
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
            'skema_sewa' => 'tahunan',
            'total_sewa' => $totalSewa,
            'dp_minimum' => $dpMinimum,
            'tenggat_pelunasan' => '2027-01-05',
            'status_pembayaran' => 'belum_lunas',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function bayar(int $id, string $jenis, float $jumlah)
    {
        return $this->withoutMiddleware()->postJson("/penghunian/{$id}/pembayaran", [
            'jenis_pembayaran' => $jenis,
            'jumlah' => $jumlah,
            'tanggal_bayar' => '2026-11-05',
            'metode_pembayaran' => 'transfer',
        ]);
    }

    public function test_dp_berhasil_dicatat_dan_sisa_sewa_dihitung(): void
    {
        $id = $this->buatPenghunianPembayaran();

        $this->bayar($id, 'dp', 300000)
            ->assertCreated()
            ->assertJsonPath('data.jenis_pembayaran', 'dp')
            ->assertJsonPath('data.total_sewa', 5000000)
            ->assertJsonPath('data.total_terbayar', 300000)
            ->assertJsonPath('data.sisa_pembayaran', 4700000)
            ->assertJsonPath('data.status_pembayaran', 'belum_lunas');

        $this->assertDatabaseHas('pembayaran', [
            'id_penghunian' => $id,
            'status' => 'dp',
            'jumlah' => 300000,
        ]);
    }

    public function test_dp_kedua_untuk_penghunian_yang_sama_ditolak(): void
    {
        $id = $this->buatPenghunianPembayaran();
        $this->bayar($id, 'dp', 300000)->assertCreated();

        $this->bayar($id, 'dp', 300000)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'DP untuk penghunian ini sudah tercatat.');

        $this->assertDatabaseCount('pembayaran', 1);
    }

    public function test_dp_di_bawah_minimum_dicatat_dengan_peringatan(): void
    {
        $id = $this->buatPenghunianPembayaran(totalSewa: 5000000, dpMinimum: 500000);

        $this->bayar($id, 'dp', 300000)
            ->assertCreated()
            ->assertJsonPath('warning', true)
            ->assertJsonPath('data.sisa_pembayaran', 4700000);
    }

    public function test_pembayaran_melebihi_sisa_sewa_ditolak(): void
    {
        $id = $this->buatPenghunianPembayaran();

        $this->bayar($id, 'dp', 5000001)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Nominal pembayaran melebihi sisa sewa Rp5.000.000.');

        $this->assertDatabaseCount('pembayaran', 0);
    }

    public function test_pelunasan_dengan_nominal_sisa_mengubah_status_menjadi_lunas(): void
    {
        $id = $this->buatPenghunianPembayaran();
        $this->bayar($id, 'dp', 300000)->assertCreated();
        $this->bayar($id, 'cicilan', 2000000)->assertCreated();

        $this->bayar($id, 'pelunasan', 2700000)
            ->assertCreated()
            ->assertJsonPath('data.total_terbayar', 5000000)
            ->assertJsonPath('data.sisa_pembayaran', 0)
            ->assertJsonPath('data.status_pembayaran', 'lunas');

        $this->assertDatabaseHas('penghunian', [
            'id_penghunian' => $id,
            'status_pembayaran' => 'lunas',
        ]);
    }

    public function test_penghunian_bukan_penyewa_utama_ditolak_mencatat_pembayaran(): void
    {
        $id = $this->buatPenghunianPembayaran(penyewaUtama: false);

        $this->bayar($id, 'dp', 300000)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Pembayaran harus dicatat pada penghunian penyewa utama.');

        $this->assertDatabaseCount('pembayaran', 0);
    }

    public function test_pelunasan_tidak_boleh_kurang_dari_sisa_sewa(): void
    {
        $id = $this->buatPenghunianPembayaran();
        $this->bayar($id, 'dp', 300000)->assertCreated();

        $this->bayar($id, 'pelunasan', 1000000)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Nominal pelunasan harus sama dengan sisa pembayaran Rp4.700.000.');
    }

    public function test_jenis_pembayaran_yang_tidak_dikenal_ditolak(): void
    {
        $id = $this->buatPenghunianPembayaran();

        $this->bayar($id, 'angsuran-aneh', 100000)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['jenis_pembayaran']);
    }
}
