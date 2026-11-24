<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Index untuk pola query yang dipakai laporan, rekap shift, dan
     * self-order:expire-pending (filter status + rentang created_at).
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['payment_status', 'created_at'], 'transactions_payment_status_created_at_index');
            $table->index(['shift_session_id', 'payment_status'], 'transactions_shift_session_payment_status_index');
            $table->index(['channel', 'payment_method', 'payment_status', 'created_at'], 'transactions_channel_payment_created_at_index');
        });

        Schema::table('transaction_events', function (Blueprint $table) {
            $table->index(['action', 'actor_user_id', 'created_at'], 'transaction_events_action_actor_created_at_index');
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->index(['reference_type', 'reference_id', 'type'], 'inventory_movements_reference_id_type_index');
            $table->index(['ingredient_id', 'happened_at'], 'inventory_movements_ingredient_happened_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_payment_status_created_at_index');
            $table->dropIndex('transactions_shift_session_payment_status_index');
            $table->dropIndex('transactions_channel_payment_created_at_index');
        });

        Schema::table('transaction_events', function (Blueprint $table) {
            $table->dropIndex('transaction_events_action_actor_created_at_index');
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropIndex('inventory_movements_reference_id_type_index');
            $table->dropIndex('inventory_movements_ingredient_happened_at_index');
        });
    }
};
