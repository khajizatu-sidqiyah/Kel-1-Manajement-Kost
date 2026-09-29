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
        Schema::create('kamar', function (Blueprint $table) {
            $table->id('id_kamar');
            $table->string('no_kamar', 20);
            $table->string('tipe_kamar', 50)->nullable();
            $table->decimal('harga', 12, 2);
            $table->string('status', 20)->default('kosong');

            $table->foreignId('id_kost')
                ->constrained('kos', 'id_kost')
                ->cascadeOnDelete();
                
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kamar');
    }
};
