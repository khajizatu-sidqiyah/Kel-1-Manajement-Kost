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
        Schema::create('penghunian', function (Blueprint $table) {
            $table->id('id_penghunian');

            $table->foreignId('id_kamar')
                ->constrained('kamar', 'id_kamar')
                ->cascadeOnDelete();

            $table->foreignId('id_penghuni')
                ->constrained('penghuni', 'id_penghuni')
                ->cascadeOnDelete();

            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai')->nullable();
            $table->string('status', 20)->default('aktif');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penghunian');
    }
};
