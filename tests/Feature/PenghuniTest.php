<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PenghuniTest extends TestCase
{
    use RefreshDatabase;

    private function buatKamar(): int
    {
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

        return DB::table('kamar')->insertGetId([
            'no_kamar' => '05',
            'tipe_kamar' => 'Standard',
            'harga' => 750000,
            'status' => 'kosong',
            'id_kost' => $idKost,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function buatPenghuni(array $override = []): int
    {
        return DB::table('penghuni')->insertGetId(array_merge([
            'nama_penghuni' => 'Rina',
            'no_telepon' => '081234567890',
            'email' => null,
            'alamat' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $override));
    }

    public function test_penghuni_berhasil_disimpan_dengan_data_valid(): void
    {
        $response = $this->postJson('/api/penghuni', [
            'nama_penghuni' => 'Rina',
            'no_telepon' => '081234567890',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Penghuni berhasil ditambahkan')
            ->assertJsonPath('data.nama_penghuni', 'Rina');

        $this->assertDatabaseHas('penghuni', [
            'nama_penghuni' => 'Rina',
            'no_telepon' => '081234567890',
        ]);
    }

    public function test_penghuni_bisa_disimpan_jika_nomor_telepon_kosong(): void
    {
        $response = $this->postJson('/api/penghuni', [
            'nama_penghuni' => 'Rina',
            'no_telepon' => '',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('penghuni', [
            'nama_penghuni' => 'Rina',
            'no_telepon' => null,
        ]);
    }

    public function test_nama_penghuni_wajib_diisi_dengan_format_galat_kontrak(): void
    {
        $response = $this->postJson('/api/penghuni', [
            'nama_penghuni' => '',
            'no_telepon' => '081234567890',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'The given data was invalid.')
            ->assertJsonStructure(['message', 'errors' => ['nama_penghuni']]);

        $this->assertDatabaseCount('penghuni', 0);
    }

    public function test_email_invalid_mengembalikan_galat_422_sesuai_kontrak(): void
    {
        $response = $this->postJson('/api/penghuni', [
            'nama_penghuni' => 'Rina',
            'email' => 'bukan-email',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'The given data was invalid.')
            ->assertJsonStructure(['message', 'errors' => ['email']]);
    }

    public function test_penghuni_pertama_otomatis_menjadi_penyewa_utama_dan_kamar_terisi(): void
    {
        $idKamar = $this->buatKamar();
        $idPenghuni = $this->buatPenghuni();

        $response = $this->postJson('/api/penghunian', [
            'id_kamar' => $idKamar,
            'id_penghuni' => $idPenghuni,
            'tanggal_mulai' => '2026-10-13',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.penyewa_utama', true);

        $this->assertDatabaseHas('penghunian', [
            'id_kamar' => $idKamar,
            'id_penghuni' => $idPenghuni,
            'status' => 'aktif',
            'penyewa_utama' => true,
        ]);

        $this->assertDatabaseHas('kamar', [
            'id_kamar' => $idKamar,
            'status' => 'terisi',
        ]);
    }

    public function test_penghunian_bisa_disimpan_tanpa_tanggal_mulai_dan_tetap_terhubung_ke_kamar(): void
    {
        $idKamar = $this->buatKamar();
        $idPenghuni = $this->buatPenghuni([
            'nama_penghuni' => 'Rina',
            'no_telepon' => null,
        ]);

        $response = $this->postJson('/api/penghunian', [
            'id_kamar' => $idKamar,
            'id_penghuni' => $idPenghuni,
            'tanggal_mulai' => '',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.id_kamar', $idKamar)
            ->assertJsonPath('data.id_penghuni', $idPenghuni)
            ->assertJsonPath('data.tanggal_mulai', null)
            ->assertJsonPath('data.penyewa_utama', true);

        $this->assertDatabaseHas('penghunian', [
            'id_kamar' => $idKamar,
            'id_penghuni' => $idPenghuni,
            'tanggal_mulai' => null,
            'status' => 'aktif',
            'penyewa_utama' => true,
        ]);

        $this->assertDatabaseHas('kamar', [
            'id_kamar' => $idKamar,
            'status' => 'terisi',
        ]);
    }

    public function test_penghuni_kedua_bukan_penyewa_utama_di_kamar_yang_sama(): void
    {
        $idKamar = $this->buatKamar();
        $idPenghuni1 = $this->buatPenghuni();
        $idPenghuni2 = $this->buatPenghuni([
            'nama_penghuni' => 'Dewi',
            'no_telepon' => null,
        ]);

        $this->postJson('/api/penghunian', [
            'id_kamar' => $idKamar,
            'id_penghuni' => $idPenghuni1,
            'tanggal_mulai' => '2026-10-13',
        ])->assertCreated();

        $this->postJson('/api/penghunian', [
            'id_kamar' => $idKamar,
            'id_penghuni' => $idPenghuni2,
            'tanggal_mulai' => '2026-10-13',
        ])->assertCreated()->assertJsonPath('data.penyewa_utama', false);

        $this->assertSame(1, DB::table('penghunian')
            ->where('id_kamar', $idKamar)
            ->where('status', 'aktif')
            ->where('penyewa_utama', true)
            ->count());
    }

    public function test_kamar_wajib_dipilih_saat_mencatat_penghunian(): void
    {
        $idPenghuni = $this->buatPenghuni();

        $response = $this->postJson('/api/penghunian', [
            'id_penghuni' => $idPenghuni,
            'tanggal_mulai' => '2026-10-13',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'The given data was invalid.')
            ->assertJsonStructure(['message', 'errors' => ['id_kamar']]);

        $this->assertDatabaseCount('penghunian', 0);
    }
}
