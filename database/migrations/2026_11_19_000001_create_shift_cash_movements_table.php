<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_session_id')->constrained('shift_sessions')->cascadeOnDelete();
            $table->string('type', 10); // in | out
            $table->integer('amount');
            $table->string('category', 100)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['shift_session_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_cash_movements');
    }
};
