<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_sessions', function (Blueprint $table) {
            // Snapshot penuh ringkasan kas saat sesi ditutup, agar laporan
            // historis tidak berubah ketika transaksi/gerakan kas diubah.
            $table->json('summary_snapshot')->nullable()->after('variance');
        });
    }

    public function down(): void
    {
        Schema::table('shift_sessions', function (Blueprint $table) {
            $table->dropColumn('summary_snapshot');
        });
    }
};
