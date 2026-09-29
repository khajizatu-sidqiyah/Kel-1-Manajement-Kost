<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('kos', function (Blueprint $table) {
            $table->id('id_kost');
            $table->string('nama_kost', 100);
            $table->text('alamat')->nullable();
            $table->text('deskripsi')->nullable();
            $table->text('fasilitas')->nullable();

            $table->foreignId('id_pemilik')
                ->constrained('pemilik', 'id_pemilik')
                ->cascadeOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kos');
    }
};
