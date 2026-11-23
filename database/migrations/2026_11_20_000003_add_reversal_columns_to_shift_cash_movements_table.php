<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_cash_movements', function (Blueprint $table) {
            // Pembatalan bersifat append-only: baris koreksi menunjuk baris
            // aslinya, tanpa mengubah atau menandai ulang baris asli.
            $table->foreignId('reversal_of_id')->nullable()->constrained('shift_cash_movements')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shift_cash_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reversal_of_id');
        });
    }
};
