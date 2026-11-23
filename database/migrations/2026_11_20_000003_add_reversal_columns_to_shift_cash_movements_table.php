<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_cash_movements', function (Blueprint $table) {
            // Pembatalan gerakan kas bersifat append-only: baris asli hanya
            // ditandai, koreksinya dicatat sebagai baris baru berlawanan arah.
            $table->foreignId('reversal_of_id')->nullable()->constrained('shift_cash_movements')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reversal_reason', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shift_cash_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reversal_of_id');
            $table->dropConstrainedForeignId('reversed_by_user_id');
            $table->dropColumn(['reversed_at', 'reversal_reason']);
        });
    }
};
