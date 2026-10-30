<?php

namespace tests\Feature;

use App\Models\PengaturanSewa;
use App\Models\Penghunian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PenghunianDpTenggatTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Membuat data penghunian untuk kebutuhan pengujian.
     */
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

    /**
     * Helper untuk menjalankan perhitungan DP.
     */
    private function hitung(int $id, string $skema)
    {
        return $this->withoutMiddleware()
            ->postJson("/penghunian/{$id}/hitung", [
                'skema_sewa' => $skema,
            ]);
    }

    public function test_dp_bulanan_adalah_lima_puluh_persen_harga_kamar(): void
    {
        $id = $this->buatPenghunian('2026-01-31');

        $this->hitung($id, 'bulanan')
            ->assertOk()
            ->assertJsonPath('total_sewa', 750000)
            ->assertJsonPath('dp_minimum', 375000)
            ->assertJsonPath('tenggat_pelunasan', '2026-01-31');
    }

    public function test_dp_tahunan_menggunakan_pengaturan_database(): void
    {
        $id = $this->buatPenghunian('2026-01-15');

        PengaturanSewa::create([
            'dp_minimum_tahunan' => 400000,
        ]);

        $this->hitung($id, 'tahunan')
            ->assertOk()
            ->assertJsonPath('total_sewa', 9000000)
            ->assertJsonPath('dp_minimum', 400000)
            ->assertJsonPath('tenggat_pelunasan', '2026-04-15');
    }

    public function test_dp_tetap_dihitung_jika_tanggal_masuk_kosong(): void
    {
        $id = $this->buatPenghunian(null);

        $this->hitung($id, 'bulanan')
            ->assertOk()
            ->assertJsonPath('dp_minimum', 375000)
            ->assertJsonPath('tenggat_pelunasan', null)
            ->assertJsonPath(
                'message',
                'DP berhasil dihitung. Isi tanggal masuk untuk menghitung tenggat.'
            );
    }

    public function test_skema_sewa_wajib_dipilih(): void
    {
        $id = $this->buatPenghunian();

        $this->withoutMiddleware()
            ->postJson("/penghunian/{$id}/hitung", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['skema_sewa']);
    }

    public function test_tenggat_bulanan_akhir_bulan_berpindah_dengan_benar(): void
    {
        $id = $this->buatPenghunian('2026-01-31');

        $penghunian = Penghunian::findOrFail($id);

        $penghunian->update([
            'skema_sewa' => 'bulanan',
            'tenggat_pelunasan' => '2026-01-31',
        ]);

        $this->withoutMiddleware()
            ->putJson("/penghunian/{$id}/tenggat-berikutnya")
            ->assertOk()
            ->assertJsonPath('tenggat_berikutnya', '2026-02-28');
    }

    public function test_tenggat_tahunan_bisa_diubah(): void
    {
        $id = $this->buatPenghunian('2026-01-15');

        $penghunian = Penghunian::findOrFail($id);

        $penghunian->update([
            'skema_sewa' => 'tahunan',
            'tenggat_pelunasan' => '2026-04-15',
        ]);

        $this->withoutMiddleware()
            ->putJson("/penghunian/{$id}/tenggat", [
                'tenggat_pelunasan' => '2026-05-20',
            ])
            ->assertOk()
            ->assertJsonPath('tenggat_pelunasan', '2026-05-20');
    }

    public function test_tenggat_tidak_boleh_sebelum_tanggal_masuk(): void
    {
        $id = $this->buatPenghunian('2026-04-15');

        $penghunian = Penghunian::findOrFail($id);

        $penghunian->update([
            'skema_sewa' => 'tahunan',
        ]);

        $this->withoutMiddleware()
            ->putJson("/penghunian/{$id}/tenggat", [
                'tenggat_pelunasan' => '2026-04-14',
            ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Tenggat tidak boleh sebelum tanggal mulai sewa.'
            );
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

        $penghunian = Penghunian::findOrFail($id);

        $penghunian->update([
            'skema_sewa' => 'bulanan',
            'tenggat_pelunasan' => '2028-01-31',
        ]);

        $this->withoutMiddleware()
            ->putJson("/penghunian/{$id}/tenggat-berikutnya")
            ->assertOk()
            ->assertJsonPath(
                'tenggat_berikutnya',
                '2028-02-29'
            );
    }

    public function test_skema_sewa_yang_tidak_valid_ditolak(): void
    {
        $id = $this->buatPenghunian();

        $this->hitung($id, 'mingguan')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['skema_sewa']);
    }

    public function test_pengaturan_dp_tahunan_yang_belum_tersedia_ditolak(): void
    {
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

        $penghunian = Penghunian::findOrFail($id);

        $penghunian->update([
            'skema_sewa' => 'tahunan',
        ]);

        $this->withoutMiddleware()
            ->putJson("/penghunian/{$id}/tenggat", [
                'tenggat_pelunasan' => '2026-05-20',
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

        PengaturanSewa::create([
            'dp_minimum_tahunan' => 400000,
        ]);

        $this->hitung($id, 'tahunan')
            ->assertOk()
            ->assertJsonPath('tenggat_pelunasan', '2026-04-30');
    }
}