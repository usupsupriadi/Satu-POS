<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('business_date');
            $table->timestamp('opened_at');
            $table->unsignedBigInteger('opening_cash')->default(0);
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('closing_cash')->nullable();
            $table->unsignedBigInteger('expected_cash')->nullable();
            $table->bigInteger('variance')->nullable();
            $table->string('status', 20)->default('open');
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('business_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_sessions');
    }
};
