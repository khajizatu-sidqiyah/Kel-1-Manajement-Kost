<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kamar', function (Blueprint $table) {
            $table->unsignedTinyInteger('lantai')
                ->nullable()
                ->after('no_kamar');
        });
    }

    public function down(): void
    {
        Schema::table('kamar', function (Blueprint $table) {
            $table->dropColumn('lantai');
        });
    }
};