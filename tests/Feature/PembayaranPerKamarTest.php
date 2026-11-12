<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PembayaranPerKamarTest extends TestCase
{
    use RefreshDatabase;

    private function buatKamarDenganPenghuni(
        string $nomor,
        array $penghuni,
        string $skema = 'bulanan',
        float $total = 600000,
        string $tenggat = '2026-11-05'
    ): array {
        $idPemilik = DB::table('pemilik')->insertGetId([
            'nama_pemilik' => 'Pemilik Test',
            'email' => uniqid('pemilik') . '@test.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $idKost = DB::table('kos')->insertGetId([
            'nama_kost' => 'Kost Test',
            'id_pemilik' => $idPemilik,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $idKamar = DB::table('kamar')->insertGetId([
            'no_kamar' => $nomor,
            'tipe_kamar' => 'Standard',
            'harga' => $total,
            'harga_tahunan' => 5000000,
            'harga_tahunan_1_orang' => 5000000,
            'harga_tahunan_2_orang' => 6900000,
            'status' => 'terisi',
            'id_kost' => $idKost,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ids = [];

        foreach ($penghuni as $index => $nama) {
            $idPenghuni = DB::table('penghuni')->insertGetId([
                'nama_penghuni' => $nama,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $ids[] = DB::table('penghunian')->insertGetId([
                'id_kamar' => $idKamar,
                'id_penghuni' => $idPenghuni,
                'tanggal_mulai' => '2026-11-05',
                'status' => 'aktif',
                'penyewa_utama' => $index === 0,
                'skema_sewa' => $skema,
                'total_sewa' => $total,
                'dp_minimum' => 300000,
                'tenggat_pelunasan' => $tenggat,
                'status_pembayaran' => 'belum_lunas',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [
            'id_kamar' => $idKamar,
            'id_penghunian' => $ids,
        ];
    }

    private function catatPembayaran(int $idPenghunian, string $jenis, float $jumlah)
    {
        return $this->withoutMiddleware()->postJson('/api/pembayaran', [
            'id_penghunian' => $idPenghunian,
            'jenis_pembayaran' => $jenis,
            'tanggal_bayar' => '2026-11-05',
            'jumlah' => $jumlah,
            'metode_pembayaran' => 'transfer',
        ]);
    }

    public function test_daftar_per_kamar_satu_baris_dan_menampilkan_penyewa_utama(): void
    {
        $kamar05 = $this->buatKamarDenganPenghuni(
            '05',
            ['Rina', 'Sari'],
            'tahunan',
            6900000
        );

        $kamar06 = $this->buatKamarDenganPenghuni(
            '06',
            ['Budi']
        );

        DB::table('pembayaran')->insert([
            [
                'id_penghunian' => $kamar05['id_penghunian'][0],
                'jenis_pembayaran' => 'dp',
                'tanggal_bayar' => '2026-11-05',
                'jumlah' => 300000,
                'metode_pembayaran' => 'transfer',
                'status' => 'terverifikasi',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id_penghunian' => $kamar05['id_penghunian'][0],
                'jenis_pembayaran' => 'pelunasan',
                'tanggal_bayar' => '2026-11-06',
                'jumlah' => 6600000,
                'metode_pembayaran' => 'transfer',
                'status' => 'terverifikasi',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->withoutMiddleware()
            ->getJson('/api/pembayaran/per-kamar')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $response->assertJsonPath('data.0.penyewa_utama.nama_penghuni', 'Rina');
        $response->assertJsonPath('data.0.jumlah_penghuni_aktif', 2);
        $response->assertJsonPath('data.1.penyewa_utama.nama_penghuni', 'Budi');
        $response->assertJsonPath('data.1.jumlah_penghuni_aktif', 1);
    }

    public function test_riwayat_kamar_menggabungkan_pembayaran_semua_penghuni(): void
    {
        $kamar = $this->buatKamarDenganPenghuni(
            '05',
            ['Rina', 'Sari']
        );

        foreach ($kamar['id_penghunian'] as $idPenghunian) {
            DB::table('pembayaran')->insert([
                'id_penghunian' => $idPenghunian,
                'jenis_pembayaran' => 'pelunasan',
                'tanggal_bayar' => '2026-11-05',
                'jumlah' => 600000,
                'metode_pembayaran' => 'transfer',
                'status' => 'terverifikasi',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->withoutMiddleware()
            ->getJson("/api/pembayaran/kamar/{$kamar['id_kamar']}/riwayat")
            ->assertOk()
            ->assertJsonCount(2, 'data.pembayaran')
            ->assertJsonPath('data.penyewa_utama', 'Rina');
    }

    public function test_daftar_pembayaran_kosong_menampilkan_pesan(): void
    {
        $this->withoutMiddleware()
            ->getJson('/api/pembayaran/per-kamar')
            ->assertOk()
            ->assertJsonPath('message', 'Belum ada pembayaran.')
            ->assertJsonCount(0, 'data');
    }

    public function test_tenggat_bulanan_hanya_maju_setelah_periode_lunas(): void
    {
        $kamar = $this->buatKamarDenganPenghuni('06', ['Budi']);
        $id = $kamar['id_penghunian'][0];

        $this->catatPembayaran($id, 'dp', 300000)->assertCreated();

        $this->assertDatabaseHas('penghunian', [
            'id_penghunian' => $id,
            'tenggat_pelunasan' => '2026-11-05',
        ]);

        $this->catatPembayaran($id, 'pelunasan', 300000)
            ->assertCreated()
            ->assertJsonPath('data.status_pembayaran', 'lunas')
            ->assertJsonPath('data.tenggat_pelunasan_baru', '2026-12-05');

        $this->assertDatabaseHas('penghunian', [
            'id_penghunian' => $id,
            'tenggat_pelunasan' => '2026-12-05',
            'status_pembayaran' => 'belum_lunas',
        ]);

        // Tagihan Desember dihitung sebagai periode baru, tidak tercampur November.
        $this->catatPembayaran($id, 'pelunasan', 600000)
            ->assertCreated()
            ->assertJsonPath('data.periode_tagihan', '2026-12-01')
            ->assertJsonPath('data.tenggat_pelunasan_baru', '2027-01-05');
    }

    public function test_verifikasi_pembayaran_bulanan_memajukan_tenggat_satu_kali(): void
    {
        $kamar = $this->buatKamarDenganPenghuni('09', ['Nina']);
        $idPenghunian = $kamar['id_penghunian'][0];

        $idPembayaran = DB::table('pembayaran')->insertGetId([
            'id_penghunian' => $idPenghunian,
            'jenis_pembayaran' => 'pelunasan',
            'tanggal_bayar' => '2026-11-05',
            'periode_tagihan' => '2026-11-01',
            'jumlah' => 600000,
            'metode_pembayaran' => 'transfer',
            'status' => 'menunggu_verifikasi',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withoutMiddleware()
            ->putJson("/api/pembayaran/{$idPembayaran}/status", [
                'status' => 'terverifikasi',
            ])
            ->assertOk()
            ->assertJsonPath('data.tenggat_pelunasan_baru', '2026-12-05');

        $this->withoutMiddleware()
            ->putJson("/api/pembayaran/{$idPembayaran}/status", [
                'status' => 'terverifikasi',
            ])
            ->assertOk()
            ->assertJsonPath('data.tenggat_pelunasan_baru', null);

        $this->assertDatabaseHas('penghunian', [
            'id_penghunian' => $idPenghunian,
            'tenggat_pelunasan' => '2026-12-05',
        ]);
    }

    public function test_harga_tahunan_satu_penghuni_menggunakan_tarif_satu_orang(): void
    {
        $kamar = $this->buatKamarDenganPenghuni(
            '07',
            ['Budi'],
            'tahunan',
            5000000
        );

        DB::table('pengaturan_sewa')->insert([
            'dp_minimum_tahunan' => 300000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withoutMiddleware()
            ->postJson("/api/penghunian/{$kamar['id_penghunian'][0]}/hitung-dp", [
                'skema_sewa' => 'tahunan',
            ])
            ->assertOk()
            ->assertJsonPath('data.total_sewa', 5000000);
    }

    public function test_harga_tahunan_dua_penghuni_menggunakan_tarif_dua_orang(): void
    {
        $kamar = $this->buatKamarDenganPenghuni(
            '08',
            ['Rina', 'Sari'],
            'tahunan',
            6900000
        );

        DB::table('pengaturan_sewa')->insert([
            'dp_minimum_tahunan' => 300000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withoutMiddleware()
            ->postJson("/api/penghunian/{$kamar['id_penghunian'][0]}/hitung-dp", [
                'skema_sewa' => 'tahunan',
            ])
            ->assertOk()
            ->assertJsonPath('data.total_sewa', 6900000);
    }

    public function test_kamar_tidak_ditemukan_pada_riwayat(): void
    {
        $this->withoutMiddleware()
            ->getJson('/api/pembayaran/kamar/99999/riwayat')
            ->assertNotFound()
            ->assertJsonPath('error.code', 404)
            ->assertJsonPath('error.status', 'NOT_FOUND');
    }
}
