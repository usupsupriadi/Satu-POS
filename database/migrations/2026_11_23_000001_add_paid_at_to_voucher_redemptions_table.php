<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Voucher kini direservasi saat order dibuat, sehingga `redeemed_at`
     * terisi walau order belum lunas. `paid_at` menandai pemakaian yang
     * benar-benar dibayar supaya laporan voucher tidak menghitung order
     * pending/kedaluwarsa.
     */
    public function up(): void
    {
        Schema::table('voucher_redemptions', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable()->after('redeemed_at');
            $table->index('paid_at');
        });

        $paidStatuses = ['paid', 'partial_refund', 'refunded'];

        DB::table('voucher_redemptions')
            ->select(['id', 'transaction_id'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($paidStatuses): void {
                $transactions = DB::table('transactions')
                    ->whereIn('id', $rows->pluck('transaction_id')->all())
                    ->whereIn('payment_status', $paidStatuses)
                    ->get(['id', 'paid_at', 'created_at'])
                    ->keyBy('id');

                foreach ($rows as $row) {
                    $transaction = $transactions->get($row->transaction_id);

                    if (! $transaction) {
                        continue;
                    }

                    DB::table('voucher_redemptions')->where('id', $row->id)->update([
                        'paid_at' => $transaction->paid_at ?: $transaction->created_at,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('voucher_redemptions', function (Blueprint $table) {
            $table->dropIndex(['paid_at']);
            $table->dropColumn('paid_at');
        });
    }
};
