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

        $table->unsignedBigInteger('id_penghunian');

        $table->date('tanggal_bayar');
        $table->decimal('jumlah', 12, 2);
        $table->string('metode_pembayaran', 100);
        $table->string('status', 50)->default('DP');

        $table->timestamps();

        $table->foreign('id_penghunian')
            ->references('id_penghunian')
            ->on('penghunian')
            ->cascadeOnDelete();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pembayarans');
    }
};
