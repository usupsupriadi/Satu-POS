<?php

namespace App\Services\Shifts;

use App\Models\Setting;
use App\Models\Shift;
use App\Models\ShiftCashMovement;
use App\Models\ShiftSession;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ShiftSessionService
{
    public function activeFor(?User $user): ?ShiftSession
    {
        if (! $user) {
            return null;
        }

        return ShiftSession::query()
            ->where('user_id', $user->id)
            ->open()
            ->latest('opened_at')
            ->first();
    }

    public function requiredForPos(): bool
    {
        return (bool) (Setting::current()->shift_required_for_pos ?? false);
    }

    /**
     * Tanggal bisnis = tanggal jam mulai shift. Untuk shift yang melewati
     * tengah malam, sesi yang dibuka dini hari dihitung milik hari sebelumnya.
     */
    public function businessDateFor(?Shift $shift, ?CarbonImmutable $moment = null): string
    {
        $moment = $moment ?? CarbonImmutable::now();

        if (! $shift) {
            return $moment->toDateString();
        }

        $minutesNow = ((int) $moment->format('H') * 60) + (int) $moment->format('i');

        if ($shift->crossesMidnight() && $minutesNow < $shift->endTimeMinutes()) {
            return $moment->subDay()->toDateString();
        }

        return $moment->toDateString();
    }

    public function open(User $user, ?Shift $shift, int $openingCash, ?string $note = null): ShiftSession
    {
        if ($openingCash < 0) {
            throw new RuntimeException('Kas awal tidak boleh negatif.');
        }

        return DB::transaction(function () use ($user, $shift, $openingCash, $note) {
            $existing = ShiftSession::query()
                ->where('user_id', $user->id)
                ->open()
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw new RuntimeException('Anda masih memiliki sesi kas yang terbuka. Tutup dulu sebelum membuka sesi baru.');
            }

            if ($shift && ! $shift->is_active) {
                throw new RuntimeException('Shift ini sedang nonaktif.');
            }

            $openedAt = CarbonImmutable::now();

            return ShiftSession::query()->create([
                'shift_id' => $shift?->id,
                'user_id' => $user->id,
                'business_date' => $this->businessDateFor($shift, $openedAt),
                'opened_at' => $openedAt,
                'opening_cash' => $openingCash,
                'status' => 'open',
                'note' => $note,
            ]);
        });
    }

    /**
     * Ringkasan kas transaksi yang tertaut ke sesi ini. Hanya transaksi lunas
     * yang dihitung, refund tunai dikurangkan, dan gerakan kas (setoran/ambil)
     * ditambahkan ke kas seharusnya.
     *
     * @return array{transaction_count: int, total_sales: int, cash_sales: int, non_cash_sales: int, cash_refunds: int, cash_movements_in: int, cash_movements_out: int, cash_movement_count: int, expected_cash: int}
     */
    public function cashSummary(ShiftSession $session): array
    {
        $base = Transaction::query()
            ->where('shift_session_id', $session->id)
            ->where('payment_status', 'paid');

        $transactionCount = (int) (clone $base)->count();
        $totalSales = (int) (clone $base)->sum('total');
        $cashSales = (int) (clone $base)->where('payment_method', 'cash')->sum('total');
        $cashRefunds = (int) (clone $base)->where('payment_method', 'cash')->sum('refunded_amount');

        $movementsIn = (int) ShiftCashMovement::query()
            ->where('shift_session_id', $session->id)
            ->where('type', 'in')
            ->sum('amount');
        $movementsOut = (int) ShiftCashMovement::query()
            ->where('shift_session_id', $session->id)
            ->where('type', 'out')
            ->sum('amount');
        $movementCount = (int) ShiftCashMovement::query()
            ->where('shift_session_id', $session->id)
            ->count();

        $expectedCash = (int) $session->opening_cash + $cashSales - $cashRefunds + $movementsIn - $movementsOut;

        return [
            'transaction_count' => $transactionCount,
            'total_sales' => $totalSales,
            'cash_sales' => $cashSales,
            'non_cash_sales' => $totalSales - $cashSales,
            'cash_refunds' => $cashRefunds,
            'cash_movements_in' => $movementsIn,
            'cash_movements_out' => $movementsOut,
            'cash_movement_count' => $movementCount,
            'expected_cash' => $expectedCash,
        ];
    }

    /**
     * Angka rekonsiliasi untuk ditampilkan. Sesi yang sudah ditutup memakai
     * nilai tersimpan (agar historis tidak berubah), sesi terbuka dihitung live.
     *
     * @return array{transaction_count: int, total_sales: int, cash_sales: int, non_cash_sales: int, cash_refunds: int, expected_cash: int, closing_cash: int|null, variance: int|null}
     */
    public function reconciliation(ShiftSession $session): array
    {
        $summary = $this->cashSummary($session);

        if ($session->isOpen()) {
            $summary['closing_cash'] = null;
            $summary['variance'] = null;

            return $summary;
        }

        $summary['expected_cash'] = $session->expected_cash ?? $summary['expected_cash'];
        $summary['closing_cash'] = $session->closing_cash;
        $summary['variance'] = $session->variance;

        return $summary;
    }

    /**
     * Catat gerakan kas (setoran/ambil) pada sesi yang masih terbuka.
     *
     * @throws RuntimeException bila sesi sudah ditutup atau input tidak valid.
     */
    public function recordMovement(
        ShiftSession $session,
        string $type,
        int $amount,
        ?string $category = null,
        ?string $note = null,
        ?User $user = null,
    ): ShiftCashMovement {
        if (! in_array($type, ['in', 'out'], true)) {
            throw new RuntimeException('Jenis gerakan kas tidak valid.');
        }

        if ($amount <= 0) {
            throw new RuntimeException('Nilai gerakan kas harus lebih dari 0.');
        }

        return DB::transaction(function () use ($session, $type, $amount, $category, $note, $user): ShiftCashMovement {
            $locked = ShiftSession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isOpen()) {
                throw new RuntimeException('Sesi kas ini sudah ditutup; gerakan kas tidak bisa dicatat.');
            }

            return ShiftCashMovement::query()->create([
                'shift_session_id' => $locked->id,
                'type' => $type,
                'amount' => $amount,
                'category' => $category !== null && trim($category) !== '' ? trim($category) : null,
                'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
                'created_by_user_id' => $user?->id ?? auth()->id(),
            ]);
        });
    }

    /**
     * Hapus gerakan kas, hanya bila sesi masih terbuka.
     *
     * @throws RuntimeException bila sesi sudah ditutup.
     */
    public function deleteMovement(ShiftCashMovement $movement): void
    {
        DB::transaction(function () use ($movement): void {
            $session = ShiftSession::query()
                ->whereKey($movement->shift_session_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $session->isOpen()) {
                throw new RuntimeException('Sesi kas ini sudah ditutup; gerakan kas tidak bisa dihapus.');
            }

            $movement->delete();
        });
    }

    public function close(ShiftSession $session, int $closingCash, User $closedBy, ?string $note = null): ShiftSession
    {
        if ($closingCash < 0) {
            throw new RuntimeException('Kas fisik tidak boleh negatif.');
        }

        return DB::transaction(function () use ($session, $closingCash, $closedBy, $note) {
            $locked = ShiftSession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isOpen()) {
                throw new RuntimeException('Sesi kas ini sudah ditutup.');
            }

            $summary = $this->cashSummary($locked);

            $locked->forceFill([
                'status' => 'closed',
                'closed_at' => CarbonImmutable::now(),
                'closing_cash' => $closingCash,
                'expected_cash' => $summary['expected_cash'],
                'variance' => $closingCash - $summary['expected_cash'],
                'closed_by_user_id' => $closedBy->id,
                'note' => $note !== null && trim($note) !== '' ? trim($note) : $locked->note,
            ])->save();

            return $locked;
        });
    }

    public function assertCanTrade(?User $user): void
    {
        if (! $this->requiredForPos()) {
            return;
        }

        if ($this->activeFor($user)) {
            return;
        }

        throw new RuntimeException('Buka sesi kas terlebih dahulu sebelum bertransaksi di POS.');
    }
}
