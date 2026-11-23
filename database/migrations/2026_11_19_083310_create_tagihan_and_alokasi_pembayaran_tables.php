<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagihan', function (Blueprint $table) {
            $table->id('id_tagihan');

            $table->foreignId('id_penghunian')
                ->constrained('penghunian', 'id_penghunian')
                ->cascadeOnDelete();

            // Bulanan atau tahunan.
            $table->string('jenis_tagihan', 20);

            // Contoh: 2026-11-01. NULL untuk tagihan tahunan.
            $table->date('periode')->nullable();

            // Dipakai untuk mencegah tagihan ganda.
            // Contoh: 2026-11 atau tahunan.
            $table->string('kode_periode', 20);

            $table->date('jatuh_tempo')->nullable();
            $table->decimal('jumlah_tagihan', 12, 2);
            $table->decimal('jumlah_terbayar', 12, 2)->default(0);
            $table->decimal('sisa_tagihan', 12, 2);

            $table->enum('status', [
                'belum_bayar',
                'belum_lunas',
                'sudah_lunas',
            ])->default('belum_bayar');

            $table->timestamps();

            $table->unique(
                ['id_penghunian', 'kode_periode'],
                'tagihan_penghunian_periode_unique'
            );
        });

        Schema::create('alokasi_pembayaran', function (Blueprint $table) {
            $table->id('id_alokasi');

            $table->foreignId('id_tagihan')
                ->constrained('tagihan', 'id_tagihan')
                ->cascadeOnDelete();

            $table->foreignId('id_pembayaran')
                ->constrained('pembayaran', 'id_pembayaran')
                ->cascadeOnDelete();

            $table->decimal('jumlah_dialokasikan', 12, 2);

            $table->timestamps();

            $table->unique(
                ['id_tagihan', 'id_pembayaran'],
                'alokasi_tagihan_pembayaran_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alokasi_pembayaran');
        Schema::dropIfExists('tagihan');
    }
};