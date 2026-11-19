<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('shift_session_id')
                ->nullable()
                ->after('channel')
                ->constrained('shift_sessions')
                ->nullOnDelete();

            $table->index('shift_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['shift_session_id']);
            $table->dropConstrainedForeignId('shift_session_id');
        });
    }
};
