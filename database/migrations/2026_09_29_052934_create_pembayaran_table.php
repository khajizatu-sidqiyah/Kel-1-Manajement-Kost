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
        Schema::create('pembayaran', function (Blueprint $table) {
            $table->id('id_pembayaran');

            $table->foreignId('id_penghunian')
                ->constrained('penghunian', 'id_penghunian')
                ->cascadeOnDelete();

            $table->date('tanggal_bayar');
            $table->decimal('jumlah', 12, 2);
            $table->string('metode_pembayaran', 30);
            $table->string('bukti_pembayaran')->nullable();
            $table->string('status', 20)->default('pending');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pembayaran');
    }
};
