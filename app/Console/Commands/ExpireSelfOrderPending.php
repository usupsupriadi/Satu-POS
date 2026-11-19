<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\Transactions\ReleaseOrderBenefits;
use App\Services\Transactions\TransactionEventService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireSelfOrderPending extends Command
{
    protected $signature = 'self-order:expire-pending {--minutes= : Batas waktu dalam menit (default dari config)}';

    protected $description = 'Batalkan pesanan self-order tunai yang tidak diambil kasir';

    public function handle(TransactionEventService $events, ReleaseOrderBenefits $benefits): int
    {
        $minutes = (int) ($this->option('minutes') ?? config('self_order.pending_expiry_minutes', 20));
        $minutes = max(1, $minutes);
        $cutoff = now()->subMinutes($minutes);

        $expired = 0;

        Transaction::query()
            ->where('channel', 'self_order')
            ->where('payment_method', 'cash')
            ->where('payment_status', 'pending')
            ->whereNull('paid_at')
            ->whereNull('voided_at')
            ->where('created_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($transactions) use (&$expired, $events, $benefits, $minutes): void {
                foreach ($transactions as $transaction) {
                    $didExpire = DB::transaction(function () use ($transaction, $events, $benefits, $minutes): bool {
                        $updated = Transaction::query()
                            ->whereKey($transaction->id)
                            ->where('payment_status', 'pending')
                            ->whereNull('paid_at')
                            ->whereNull('voided_at')
                            ->update([
                                'payment_status' => 'expired',
                                'order_status' => 'cancelled',
                                'updated_at' => now(),
                            ]);

                        if ($updated !== 1) {
                            return false;
                        }

                        $transaction->refresh();

                        // Transisi status di atas hanya berhasil sekali, jadi pengembalian
                        // poin/voucher di sini tidak bisa dobel.
                        $released = $benefits->release($transaction);

                        $events->record($transaction, 'self_order.expired', [
                            'reason' => 'cash_pending_timeout',
                            'minutes' => $minutes,
                            'points_refunded' => $released['points_refunded'],
                            'voucher_released' => $released['voucher_released'],
                        ]);

                        return true;
                    });

                    if ($didExpire) {
                        $expired++;
                    }
                }
            });

        $this->info('Pesanan self-order tunai kedaluwarsa: '.$expired.' (batas '.$minutes.' menit).');

        return self::SUCCESS;
    }
}
