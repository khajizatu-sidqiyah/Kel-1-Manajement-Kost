<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PenghuniTest extends TestCase
{
    use RefreshDatabase;

    private function buatKamar(string $nomor = 'A01', string $status = 'kosong'): int
    {
        $idPemilik = DB::table('pemilik')->insertGetId([
            'nama_pemilik' => 'Pemilik Test', 'no_telepon' => '081234567890',
            'email' => 'pemilik@test.com', 'alamat' => 'Kuningan',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $idKost = DB::table('kos')->insertGetId([
            'nama_kost' => 'Kost Test', 'alamat' => 'Alamat Test', 'deskripsi' => null,
            'fasilitas' => null, 'id_pemilik' => $idPemilik,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return DB::table('kamar')->insertGetId([
            'id_kost' => $idKost, 'no_kamar' => $nomor, 'tipe_kamar' => null,
            'harga' => 750000, 'status' => $status,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function tambahPenghuni(int $idKamar, string $nama, ?string $email = null, ?string $telepon = null)
    {
        return $this->postJson('/api/penghuni', [
            'nama_penghuni' => $nama,
            'email' => $email,
            'no_telepon' => $telepon,
            'alamat' => 'Kuningan',
            'id_kamar' => $idKamar,
            'tanggal_mulai' => '2026-10-14',
        ]);
    }

    public function test_penghuni_pertama_disimpan_sebagai_penyewa_utama_dan_kamar_terisi(): void
    {
        $idKamar = $this->buatKamar();
        $response = $this->tambahPenghuni($idKamar, 'Tata', 'tata@example.com');

        $response->assertCreated()->assertJsonPath('data.is_penyewa_utama', true)
            ->assertJsonPath('data.can_catat_dp', true);
        $this->assertDatabaseHas('kamar', ['id_kamar' => $idKamar, 'status' => 'terisi']);
        $this->assertDatabaseHas('penghunian', ['id_kamar' => $idKamar, 'is_penyewa_utama' => true, 'status' => 'aktif']);
    }

    public function test_nomor_telepon_boleh_kosong_dan_bisa_dilengkapi_tanpa_entri_ganda(): void
    {
        $idKamar = $this->buatKamar();
        $create = $this->tambahPenghuni($idKamar, 'Tata', 'tata@example.com');
        $create->assertCreated()->assertJsonPath('data.no_telepon', null);
        $idPenghuni = $create->json('data.id_penghuni');

        $this->putJson('/api/penghuni/'.$idPenghuni, [
            'nama_penghuni' => 'Tata', 'email' => 'tata@example.com',
            'no_telepon' => '081234567890', 'alamat' => 'Kuningan',
        ])->assertOk();

        $this->assertDatabaseCount('penghuni', 1);
        $this->assertDatabaseHas('penghuni', ['id_penghuni' => $idPenghuni, 'no_telepon' => '081234567890']);
    }

    public function test_nama_kosong_dan_kamar_tidak_dipilih_ditolak(): void
    {
        $idKamar = $this->buatKamar();
        $this->postJson('/api/penghuni', [
            'nama_penghuni' => '', 'id_kamar' => $idKamar, 'tanggal_mulai' => '2026-10-14',
        ])->assertUnprocessable()->assertJsonPath('error.status', 'VALIDATION_ERROR');

        $this->postJson('/api/penghuni', [
            'nama_penghuni' => 'Tata', 'tanggal_mulai' => '2026-10-14',
        ])->assertUnprocessable()->assertJsonPath('error.status', 'VALIDATION_ERROR');
    }

    public function test_penghuni_kedua_bisa_menempati_kamar_tanpa_mengubah_penyewa_utama(): void
    {
        $idKamar = $this->buatKamar();
        $first = $this->tambahPenghuni($idKamar, 'Tata', 'tata@example.com');
        $first->assertCreated();
        $idUtama = $first->json('data.id_penghuni');

        $second = $this->tambahPenghuni($idKamar, 'Dewi', 'dewi@example.com');
        $second->assertCreated()->assertJsonPath('data.is_penyewa_utama', false)
            ->assertJsonPath('data.can_catat_dp', false);

        $this->assertDatabaseCount('penghuni', 2);
        $this->assertDatabaseCount('penghunian', 2);
        $this->assertDatabaseHas('penghunian', ['id_penghuni' => $idUtama, 'is_penyewa_utama' => true]);
        $this->assertDatabaseHas('kamar', ['id_kamar' => $idKamar, 'status' => 'terisi']);
    }

    public function test_penggantian_penyewa_utama_menjaga_hanya_satu_utama_per_kamar(): void
    {
        $idKamar = $this->buatKamar();
        $first = $this->tambahPenghuni($idKamar, 'Tata', 'tata@example.com');
        $second = $this->tambahPenghuni($idKamar, 'Dewi', 'dewi@example.com');
        $idKedua = $second->json('data.id_penghuni');

        $this->patchJson('/api/penghuni/'.$idKedua.'/penyewa-utama')
            ->assertOk()->assertJsonPath('data.can_catat_dp', true);

        $this->assertDatabaseHas('penghunian', ['id_penghuni' => $idKedua, 'is_penyewa_utama' => true]);
        $this->assertSame(1, DB::table('penghunian')->where('id_kamar', $idKamar)->where('status', 'aktif')->where('is_penyewa_utama', true)->count());
    }

    public function test_daftar_penghuni_kosong_mengembalikan_array_kosong(): void
    {
        $this->getJson('/api/penghuni')->assertOk()->assertJsonPath('data', []);
    }
}
