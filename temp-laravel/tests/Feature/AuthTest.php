<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_berhasil_dengan_password_benar(): void
    {
        $user = User::factory()->create([
            'email' => 'pemilik@gmail.com',
            'password' => Hash::make('password123'),
            'role' => 'pemilik',
        ]);

        $response = $this->post('/login', [
            'email' => 'pemilik@gmail.com',
            'password' => 'password123',
        ]);

        $this->assertAuthenticatedAs($user);

        $response->assertRedirect('/dashboard');
    }

    public function test_login_gagal_dengan_password_salah(): void
    {
        User::factory()->create([
            'email' => 'pemilik@gmail.com',
            'password' => Hash::make('password123'),
            'role' => 'pemilik',
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'pemilik@gmail.com',
            'password' => 'password_salah',
        ]);

        $this->assertGuest();

        $response->assertRedirect('/login');

        $response->assertSessionHasErrors([
            'login' => 'Email atau password salah.',
        ]);
    }
}