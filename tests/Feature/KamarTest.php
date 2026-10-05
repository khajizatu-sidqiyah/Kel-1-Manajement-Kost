<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KamarTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Membuat data pemilik dan kos untuk kebutuhan testing.
     */
    private function buatKos()
    {
        $idPemilik = DB::table('pemilik')->insertGetId([
            'nama_pemilik' => 'Pemilik Test',
            'no_telepon' => '08123456789',
            'email' => 'pemilik@test.com',
            'alamat' => 'Alamat Test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('kos')->insertGetId([
            'nama_kost' => 'Kost Test',
            'alamat' => 'Alamat Kost Test',
            'deskripsi' => 'Kost untuk testing',
            'fasilitas' => 'WiFi',
            'id_pemilik' => $idPemilik,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Test POST kamar berhasil jika data valid.
     */
    public function test_kamar_berhasil_dibuat_dengan_data_valid()
    {
        $idKost = $this->buatKos();

        $response = $this->postJson('/api/kamar', [
            'id_kost' => $idKost,
            'no_kamar' => 'A01',
            'harga' => 750000,
            'status' => 'kosong',
        ]);

        $response
            ->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('kamar', [
            'id_kost' => $idKost,
            'no_kamar' => 'A01',
            'harga' => 750000,
            'status' => 'kosong',
        ]);
    }

    /**
     * Test field wajib tidak boleh kosong.
     */
    public function test_validasi_field_wajib_kamar()
    {
        $response = $this->postJson('/api/kamar', []);

        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'id_kost',
            'no_kamar',
            'harga',
            'status',
        ]);
    }

    /**
     * Test harga harus berupa angka.
     */
    public function test_harga_harus_numerik()
    {
        $idKost = $this->buatKos();

        $response = $this->postJson('/api/kamar', [
            'id_kost' => $idKost,
            'no_kamar' => 'A01',
            'harga' => 'bukan angka',
            'status' => 'kosong',
        ]);

        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'harga',
        ]);
    }

    /**
     * Test status hanya boleh kosong atau terisi.
     */
    public function test_status_hanya_boleh_kosong_atau_terisi()
    {
        $idKost = $this->buatKos();

        $response = $this->postJson('/api/kamar', [
            'id_kost' => $idKost,
            'no_kamar' => 'A01',
            'harga' => 750000,
            'status' => 'tersedia',
        ]);

        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'status',
        ]);
    }
}