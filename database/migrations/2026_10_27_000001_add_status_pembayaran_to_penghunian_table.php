<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('penghunian', 'status_pembayaran')) {
            Schema::table('penghunian', function (Blueprint $table) {
                $table->string('status_pembayaran', 20)->default('belum_lunas')->after('tenggat_pelunasan');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('penghunian', 'status_pembayaran')) {
            Schema::table('penghunian', function (Blueprint $table) {
                $table->dropColumn('status_pembayaran');
            });
        }
    }
};
