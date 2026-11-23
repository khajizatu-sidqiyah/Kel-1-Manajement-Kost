<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KamarTest extends TestCase
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

    private function buatKos(): int
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

    public function test_kamar_berhasil_dibuat_dengan_data_valid(): void
    {
        $idKost = $this->buatKos();

        $this->postJson('/api/kamar', [
            'id_kost' => $idKost,
            'no_kamar' => 'A01',
            'harga' => 750000,
            'status' => 'kosong',
        ])
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Data kamar berhasil ditambahkan.'
            )
            ->assertJsonPath('data.no_kamar', 'A01')
            ->assertJsonPath('data.harga', 750000);

        $this->assertDatabaseHas('kamar', [
            'id_kost' => $idKost,
            'no_kamar' => 'A01',
            'harga' => 750000,
            'status' => 'kosong',
        ]);
    }

    public function test_nomor_kamar_dan_harga_wajib_diisi(): void
    {
        $this->buatKos();

        $this->postJson('/api/kamar', [])
            ->assertUnprocessable()
            ->assertJsonStructure([
                'message',
                'error' => [
                    'code',
                    'status',
                    'details' => [
                        'no_kamar',
                        'harga',
                    ],
                ],
            ]);

        $this->assertDatabaseCount('kamar', 0);
    }

    public function test_harga_kamar_harus_numerik(): void
    {
        $this->buatKos();

        $this->postJson('/api/kamar', [
            'no_kamar' => 'A01',
            'harga' => 'bukan angka',
            'status' => 'kosong',
        ])
            ->assertUnprocessable()
            ->assertJsonStructure([
                'error' => [
                    'details' => ['harga'],
                ],
            ]);

        $this->assertDatabaseCount('kamar', 0);
    }

    public function test_status_kamar_hanya_boleh_kosong_atau_terisi(): void
    {
        $this->buatKos();

        $this->postJson('/api/kamar', [
            'no_kamar' => 'A01',
            'harga' => 750000,
            'status' => 'tersedia',
        ])
            ->assertUnprocessable()
            ->assertJsonStructure([
                'error' => [
                    'details' => ['status'],
                ],
            ]);

        $this->assertDatabaseCount('kamar', 0);
    }
}

