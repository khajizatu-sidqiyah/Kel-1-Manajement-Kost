<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    // Test login berhasil
    public function test_login_berhasil(): void
    {
        $user = User::create([
            'name' => 'Pemilik Kost',
            'email' => 'pemilik@gmail.com',
            'password' => Hash::make('password123'),
            'role' => 'pemilik',
        ]);

        $response = $this->post('/login', [
            'email' => 'pemilik@gmail.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    // Test login gagal dengan password yang salah
    public function test_login_gagal(): void
    {
        User::create([
            'name' => 'Pemilik Kost',
            'email' => 'pemilik@gmail.com',
            'password' => Hash::make('password123'),
            'role' => 'pemilik',
        ]);

        $response = $this->post('/login', [
            'email' => 'pemilik@gmail.com',
            'password' => 'password_salah',
        ]);

        $response->assertSessionHasErrors('login');

        $this->assertGuest();
    }
}