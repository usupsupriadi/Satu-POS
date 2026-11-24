<?php

namespace App\Observers;

use App\Models\Transaction;
use App\Models\TransactionEvent;
use App\Services\Inventory\InventoryService;
use App\Services\PointService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TransactionObserver
{
    public function saved(Transaction $transaction): void
    {
        if ($transaction->inventory_applied_at) {
            return;
        }

        $paidStatuses = ['paid', 'settlement', 'capture', 'success'];

        if (! in_array((string) $transaction->payment_status, $paidStatuses, true)) {
            return;
        }

        try {
            app(InventoryService::class)->applyTransaction($transaction);
        } catch (ValidationException $e) {
            // Transaksi sudah ditandai lunas sebelum observer berjalan, jadi
            // kegagalan inventory tidak boleh hilang begitu saja.
            $message = (string) (collect($e->errors())->flatten()->first() ?? $e->getMessage());

            Log::warning('Inventory gagal diterapkan pada transaksi lunas', [
                'transaction_id' => (int) $transaction->id,
                'code' => (string) $transaction->code,
                'message' => $message,
            ]);

            $alreadyRecorded = TransactionEvent::query()
                ->where('transaction_id', (int) $transaction->id)
                ->where('action', 'inventory_failed')
                ->exists();

            if (! $alreadyRecorded) {
                TransactionEvent::query()->create([
                    'transaction_id' => (int) $transaction->id,
                    'actor_user_id' => null,
                    'action' => 'inventory_failed',
                    'meta' => ['message' => $message],
                ]);
            }
        }

        app(PointService::class)->awardPoints($transaction);
    }
}
