<?php

namespace App\Services\Transactions;

use App\Models\Member;
use App\Models\Transaction;
use App\Models\VoucherCode;
use App\Models\VoucherRedemption;

/**
 * Melepas manfaat yang melekat pada sebuah transaksi:
 * - poin yang sudah dipakai dikembalikan (sekali saja, ditandai
 *   points_refunded_at),
 * - kuota voucher yang terpakai dikembalikan,
 * - opsional, poin yang didapat (points_earned) ditarik kembali
 *   (sekali saja, ditandai points_earned_reversed_at).
 *
 * Dipakai oleh alur expire, void, dan refund.
 * Harus dipanggil di dalam transaksi database milik pemanggil.
 */
class ReleaseOrderBenefits
{
    /**
     * @return array{points_refunded: int, voucher_released: bool, points_earned_reversed: int, points_earned_shortfall: int}
     */
    public function release(Transaction $transaction, bool $reverseEarnedPoints = false): array
    {
        // Poin terpakai dikembalikan lebih dulu agar saldo member sempat pulih
        // sebelum poin earned ditarik kembali.
        $pointsRefunded = $this->refundRedeemedPoints($transaction);
        $voucherReleased = $this->releaseVoucherQuota($transaction);

        $earned = $reverseEarnedPoints
            ? $this->reverseEarnedPoints($transaction)
            : ['reversed' => 0, 'shortfall' => 0];

        return [
            'points_refunded' => $pointsRefunded,
            'voucher_released' => $voucherReleased,
            'points_earned_reversed' => $earned['reversed'],
            'points_earned_shortfall' => $earned['shortfall'],
        ];
    }

    /**
     * Kembalikan poin yang dipakai saat checkout (hanya sekali).
     */
    private function refundRedeemedPoints(Transaction $transaction): int
    {
        $points = (int) ($transaction->points_redeemed ?? 0);

        if ($points <= 0 || ! $transaction->member_id || $transaction->points_refunded_at !== null) {
            return 0;
        }

        $transaction->forceFill(['points_refunded_at' => now()])->save();
        Member::query()->whereKey($transaction->member_id)->increment('points', $points);

        return $points;
    }

    /**
     * Tarik kembali poin yang didapat dari transaksi (hanya sekali).
     * Bila saldo member tidak cukup, penarikan dibatasi saldo tersisa dan
     * sisanya dilaporkan sebagai shortfall agar bisa ditindaklanjuti admin.
     *
     * @return array{reversed: int, shortfall: int}
     */
    private function reverseEarnedPoints(Transaction $transaction): array
    {
        $earned = (int) ($transaction->points_earned ?? 0);

        if ($earned <= 0 || ! $transaction->member_id || $transaction->points_earned_reversed_at !== null) {
            return ['reversed' => 0, 'shortfall' => 0];
        }

        $transaction->forceFill(['points_earned_reversed_at' => now()])->save();

        $member = Member::query()
            ->whereKey($transaction->member_id)
            ->lockForUpdate()
            ->first();

        if (! $member) {
            return ['reversed' => 0, 'shortfall' => $earned];
        }

        $reversed = min($earned, max(0, (int) $member->points));

        if ($reversed > 0) {
            $member->decrement('points', $reversed);
        }

        return ['reversed' => $reversed, 'shortfall' => $earned - $reversed];
    }

    /**
     * Lepas kembali kuota voucher yang terpakai pada transaksi ini.
     */
    private function releaseVoucherQuota(Transaction $transaction): bool
    {
        $redemptions = VoucherRedemption::query()
            ->where('transaction_id', (int) $transaction->id)
            ->get();

        foreach ($redemptions as $redemption) {
            if ($redemption->voucher_code_id) {
                VoucherCode::query()
                    ->whereKey((int) $redemption->voucher_code_id)
                    ->where('times_redeemed', '>', 0)
                    ->decrement('times_redeemed');
            }

            $redemption->delete();
        }

        return $redemptions->isNotEmpty();
    }
}
