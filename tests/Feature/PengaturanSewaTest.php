<?php

namespace Tests\Feature;

use App\Models\PengaturanSewa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PengaturanSewaTest extends TestCase
{
    use RefreshDatabase;


private function loginSebagaiPemilik(): void
{
    /** @var \App\Models\User $user */
    $user = User::factory()->create([
        'role' => 'pemilik',
    ]);

    $this->actingAs($user);
}


    public function test_pemilik_dapat_menyimpan_dp_minimum_tahunan(): void
    {
        $this->loginSebagaiPemilik();

        $response = $this->put('/pengaturan-sewa', [
            'dp_minimum_tahunan' => 400000,
        ]);

        $response->assertRedirect(route('pengaturan-sewa.index'));

        $this->assertDatabaseHas('pengaturan_sewa', [
            'dp_minimum_tahunan' => '400000.00',
        ]);
    }

    public function test_dp_minimum_tahunan_tidak_boleh_nol(): void
    {
        $this->loginSebagaiPemilik();

        $response = $this->from('/pengaturan-sewa')->put('/pengaturan-sewa', [
            'dp_minimum_tahunan' => 0,
        ]);

        $response->assertRedirect('/pengaturan-sewa');
        $response->assertSessionHasErrors('dp_minimum_tahunan');
    }

    public function test_dp_minimum_tahunan_tidak_boleh_negatif(): void
    {
        $this->loginSebagaiPemilik();

        $response = $this->from('/pengaturan-sewa')->put('/pengaturan-sewa', [
            'dp_minimum_tahunan' => -100000,
        ]);

        $response->assertRedirect('/pengaturan-sewa');
        $response->assertSessionHasErrors('dp_minimum_tahunan');
    }

    public function test_dp_minimum_tahunan_harus_berupa_angka(): void
    {
        $this->loginSebagaiPemilik();

        $response = $this->from('/pengaturan-sewa')->put('/pengaturan-sewa', [
            'dp_minimum_tahunan' => 'empat ratus ribu',
        ]);

        $response->assertRedirect('/pengaturan-sewa');
        $response->assertSessionHasErrors('dp_minimum_tahunan');
    }

    public function test_dp_minimum_tahunan_maksimal_dua_angka_desimal(): void
    {
        $this->loginSebagaiPemilik();

        $response = $this->from('/pengaturan-sewa')->put('/pengaturan-sewa', [
            'dp_minimum_tahunan' => '400000.123',
        ]);

        $response->assertRedirect('/pengaturan-sewa');
        $response->assertSessionHasErrors('dp_minimum_tahunan');
    }

    public function test_pengaturan_dp_minimum_yang_sudah_ada_dapat_diperbarui(): void
    {
        $this->loginSebagaiPemilik();

        PengaturanSewa::create([
            'dp_minimum_tahunan' => 300000,
        ]);

        $response = $this->put('/pengaturan-sewa', [
            'dp_minimum_tahunan' => 500000,
        ]);

        $response->assertRedirect(route('pengaturan-sewa.index'));

        $this->assertDatabaseHas('pengaturan_sewa', [
            'dp_minimum_tahunan' => '500000.00',
        ]);

        $this->assertDatabaseCount('pengaturan_sewa', 1);
    }
}