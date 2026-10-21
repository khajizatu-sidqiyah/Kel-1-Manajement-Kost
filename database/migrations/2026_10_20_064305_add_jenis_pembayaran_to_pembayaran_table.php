
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('pembayaran', 'jenis_pembayaran')) {
            Schema::table('pembayaran', function (Blueprint $table) {
                $table->string('jenis_pembayaran', 20)
                    ->default('dp')
                    ->after('id_penghunian');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pembayaran', 'jenis_pembayaran')) {
            Schema::table('pembayaran', function (Blueprint $table) {
                $table->dropColumn('jenis_pembayaran');
            });
        }
    }
};
