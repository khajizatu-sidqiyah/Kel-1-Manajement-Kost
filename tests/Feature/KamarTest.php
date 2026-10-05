<?php

namespace Tests\Feature;

use App\Models\Kamar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KamarTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test POST kamar berhasil jika data valid.
     */
    public function test_kamar_berhasil_dibuat_dengan_data_valid()
    {
        $response = $this->postJson('/api/kamar', [
            'nomor_kamar' => 'A01',
            'harga' => 750000,
            'status' => 'kosong',
        ]);

        $response
            ->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('kamar', [
            'nomor_kamar' => 'A01',
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
            'nomor_kamar',
            'harga',
            'status',
        ]);
    }

    /**
     * Test harga harus berupa angka.
     */
    public function test_harga_harus_numerik()
    {
        $response = $this->postJson('/api/kamar', [
            'nomor_kamar' => 'A01',
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
        $response = $this->postJson('/api/kamar', [
            'nomor_kamar' => 'A01',
            'harga' => 750000,
            'status' => 'booking',
        ]);

        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'status',
        ]);
    }
}