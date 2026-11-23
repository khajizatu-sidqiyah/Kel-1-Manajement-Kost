<?php

namespace Tests\Feature;

use App\Models\PengaturanSewa;
use App\Models\Penghunian;
use App\Models\Pembayaran;
use App\Models\Tagihan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PenghunianDpTenggatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(
            User::factory()->create([
                'role' => 'pemilik',
            ])
        );
    }

    private function buatPenghunian(
        ?string $tanggalMulai = '2026-01-31',
        float $harga = 750000
    ): int {
        $idPemilik = DB::table('pemilik')->insertGetId([
            'nama_pemilik' => 'Pemilik Test',
            'no_telepon' => '08123456789',
            'email' => 'pemilik@test.com',
            'alamat' => 'Alamat Test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $idKost = DB::table('kos')->insertGetId([
            'nama_kost' => 'Kost Test',
            'alamat' => 'Alamat Kost',
            'deskripsi' => 'Testing',
            'fasilitas' => 'WiFi',
            'id_pemilik' => $idPemilik,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $idKamar = DB::table('kamar')->insertGetId([
            'no_kamar' => '05',
            'tipe_kamar' => 'Standard',
            'harga' => $harga,
            'harga_tahunan' => $harga * 12,
            'harga_tahunan_1_orang' => $harga * 12,
            'harga_tahunan_2_orang' => $harga * 12,
            'status' => 'terisi',
            'id_kost' => $idKost,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $idPenghuni = DB::table('penghuni')->insertGetId([
            'nama_penghuni' => 'Rina',
            'no_telepon' => '081234567890',
            'email' => null,
            'alamat' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('penghunian')->insertGetId([
            'id_kamar' => $idKamar,
            'id_penghuni' => $idPenghuni,
            'tanggal_mulai' => $tanggalMulai,
            'tanggal_selesai' => null,
            'status' => 'aktif',
            'penyewa_utama' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function hitung(int $id, string $skema)
    {
        return $this->postJson(
            "/api/penghunian/{$id}/hitung-dp",
            ['skema_sewa' => $skema]
        );
    }

    private function buatTagihanLunas(
        int $idPenghunian,
        string $periode,
        string $jatuhTempo
    ): void {
        Tagihan::create([
            'id_penghunian' => $idPenghunian,
            'jenis_tagihan' => 'bulanan',
            'periode' => $periode,
            'kode_periode' => substr($periode, 0, 7),
            'jatuh_tempo' => $jatuhTempo,
            'jumlah_tagihan' => 750000,
            'jumlah_terbayar' => 750000,
            'sisa_tagihan' => 0,
            'status' => 'sudah_lunas',
        ]);
    }
    private function buatPembayaranLunas(int $idPenghunian): void
    {
        Pembayaran::create([
            'id_penghunian' => $idPenghunian,
            'jenis_pembayaran' => 'pelunasan',
            'tanggal_bayar' => '2026-01-15',
            'periode_tagihan' => '2026-01-01',
            'jumlah' => 750000,
            'metode_pembayaran' => 'transfer',
            'bukti_pembayaran' => null,
            'status' => 'terverifikasi',
        ]);
    }

    public function test_dp_bulanan_adalah_lima_puluh_persen_harga_kamar(): void
    {
        $id = $this->buatPenghunian('2026-01-31');

        $this->hitung($id, 'bulanan')
            ->assertOk()
            ->assertJsonPath('data.total_sewa', 750000)
            ->assertJsonPath('data.dp_minimum', 375000)
            ->assertJsonPath(
                'data.tenggat_pelunasan',
                '2026-01-31T00:00:00.000000Z'
            );
    }

    public function test_dp_tahunan_menggunakan_pengaturan_database(): void
    {
        $id = $this->buatPenghunian('2026-01-15');

        PengaturanSewa::query()
            ->firstOrFail()
            ->update([
                'dp_minimum_tahunan' => 400000,
            ]);

        $this->hitung($id, 'tahunan')
            ->assertOk()
            ->assertJsonPath('data.total_sewa', 9000000)
            ->assertJsonPath('data.dp_minimum', 400000)
            ->assertJsonPath(
                'data.tenggat_pelunasan',
                '2026-04-15T00:00:00.000000Z'
            );
    }

    public function test_dp_tetap_dihitung_jika_tanggal_masuk_kosong(): void
    {
        $id = $this->buatPenghunian(null);

        $this->hitung($id, 'bulanan')
            ->assertOk()
            ->assertJsonPath('data.dp_minimum', 375000)
            ->assertJsonPath('data.tenggat_pelunasan', null)
            ->assertJsonPath(
                'message',
                'DP berhasil dihitung. Isi tanggal masuk untuk menghitung tenggat.'
            );
    }

    public function test_skema_sewa_wajib_dipilih(): void
    {
        $id = $this->buatPenghunian();

        $this->postJson("/api/penghunian/{$id}/hitung-dp", [])
            ->assertUnprocessable()
            ->assertJsonPath('error.status', 'VALIDATION_ERROR')
            ->assertJsonStructure([
                'error' => [
                    'details' => ['skema_sewa'],
                ],
            ]);
    }

    public function test_tenggat_bulanan_akhir_bulan_berpindah_dengan_benar(): void
    {
        $id = $this->buatPenghunian('2026-01-31');

        Penghunian::findOrFail($id)->update([
            'skema_sewa' => 'bulanan',
            'total_sewa' => 750000,
            'dp_minimum' => 375000,
            'tenggat_pelunasan' => '2026-01-31',
        ]);

        $this->buatPembayaranLunas($id);

        $response = $this->postJson(
    "/api/penghunian/{$id}/tenggat-berikutnya"
);

$response->dump();

$response->assertOk()
    ->assertJsonPath('data.tenggat_sekarang', '2026-01-31')
    ->assertJsonPath('data.tenggat_berikutnya', '2026-02-28');

        $this->assertDatabaseHas('penghunian', [
            'id_penghunian' => $id,
            'tenggat_pelunasan' => '2026-02-28',
        ]);
    }

    public function test_tenggat_tahunan_bisa_diubah(): void
    {
        $id = $this->buatPenghunian('2026-01-15');

        Penghunian::findOrFail($id)->update([
            'skema_sewa' => 'tahunan',
            'tenggat_pelunasan' => '2026-04-15',
        ]);

        $this->putJson("/api/penghunian/{$id}/tenggat", [
            'tenggat_pelunasan' => '2026-05-20 00:00:00',
        ])
            ->assertOk()
            ->assertJsonPath(
                'data.tenggat_pelunasan',
                '2026-05-20T00:00:00.000000Z'
            );

        $this->assertDatabaseHas('penghunian', [
            'id_penghunian' => $id,
            'tenggat_pelunasan' => '2026-05-20 00:00:00',
        ]);
    }

    public function test_tenggat_tidak_boleh_sebelum_tanggal_masuk(): void
    {
        $id = $this->buatPenghunian('2026-04-15');

        Penghunian::findOrFail($id)->update([
            'skema_sewa' => 'tahunan',
        ]);

        $this->putJson("/api/penghunian/{$id}/tenggat", [
            'tenggat_pelunasan' => '2026-04-14',
        ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Tenggat tidak boleh sebelum tanggal mulai sewa.'
            )
            ->assertJsonPath('error.status', 'VALIDATION_ERROR');
    }

    public function test_harga_kamar_nol_ditolak_saat_menghitung_dp(): void
    {
        $id = $this->buatPenghunian('2026-01-15', 0);

        $this->hitung($id, 'bulanan')
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Harga sewa kamar belum diisi'
            );
    }

    public function test_tenggat_bulanan_akhir_februari_tahun_kabisat(): void
    {
        $id = $this->buatPenghunian('2028-01-31');

        Penghunian::findOrFail($id)->update([
            'skema_sewa' => 'bulanan',
            'total_sewa' => 750000,
            'dp_minimum' => 375000,
            'tenggat_pelunasan' => '2028-01-31',
        ]);

        Pembayaran::create([
            'id_penghunian' => $id,
            'jenis_pembayaran' => 'pelunasan',
            'tanggal_bayar' => '2028-01-15',
            'periode_tagihan' => '2028-01-01',
            'jumlah' => 750000,
            'metode_pembayaran' => 'transfer',
            'bukti_pembayaran' => null,
            'status' => 'terverifikasi',
        ]);

        $this->postJson("/api/penghunian/{$id}/tenggat-berikutnya")
            ->assertOk()
            ->assertJsonPath('data.tenggat_berikutnya', '2028-02-29');

        $this->assertDatabaseHas('penghunian', [
            'id_penghunian' => $id,
            'tenggat_pelunasan' => '2028-02-29',
        ]);
    }

    public function test_skema_sewa_yang_tidak_valid_ditolak(): void
    {
        $id = $this->buatPenghunian();

        $this->hitung($id, 'mingguan')
            ->assertUnprocessable()
            ->assertJsonPath('error.status', 'VALIDATION_ERROR')
            ->assertJsonStructure([
                'error' => [
                    'details' => ['skema_sewa'],
                ],
            ]);
    }

    public function test_pengaturan_dp_tahunan_yang_belum_tersedia_ditolak(): void
    {
        PengaturanSewa::query()->delete();

        $id = $this->buatPenghunian();

        $this->hitung($id, 'tahunan')
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Pengaturan DP minimum tahunan belum tersedia atau tidak valid.'
            );
    }

    public function test_tenggat_tahunan_tidak_bisa_diubah_jika_tanggal_masuk_kosong(): void
    {
        $id = $this->buatPenghunian(null);

        Penghunian::findOrFail($id)->update([
            'skema_sewa' => 'tahunan',
        ]);

        $this->putJson("/api/penghunian/{$id}/tenggat", [
            'tenggat_pelunasan' => '2026-05-20 00:00:00',
        ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Isi tanggal masuk terlebih dahulu sebelum mengubah tenggat.'
            );
    }

    public function test_tenggat_tahunan_tiga_bulan_dari_akhir_januari(): void
    {
        $id = $this->buatPenghunian('2026-01-31');

        PengaturanSewa::query()
            ->firstOrFail()
            ->update([
                'dp_minimum_tahunan' => 400000,
            ]);

        $this->hitung($id, 'tahunan')
            ->assertOk()
            ->assertJsonPath(
                'data.tenggat_pelunasan',
                '2026-04-30T00:00:00.000000Z'
            );
    }
}



