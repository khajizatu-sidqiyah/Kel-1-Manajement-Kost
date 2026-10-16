
<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Kolom penyewa_utama sudah ditangani
        // oleh migration sebelumnya.
    }

    public function down(): void
    {
        // Tidak menghapus kolom yang digunakan aplikasi.
    }
};