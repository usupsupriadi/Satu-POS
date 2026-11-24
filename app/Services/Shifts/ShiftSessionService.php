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
    /**
     * Status transaksi yang uangnya sudah masuk ke laci kas. Transaksi yang
     * direfund tetap dihitung di sini supaya kas hanya turun sebesar nilai
     * refund (lihat refunded_amount), bukan sebesar total penjualannya.
     *
     * @var list<string>
     */
    public const SETTLED_PAYMENT_STATUSES = ['paid', 'partial_refund', 'refunded'];

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
            // Kunci baris kasir lebih dulu: lockForUpdate pada sesi open yang
            // belum ada tidak mengunci apa pun, sehingga dua request bersamaan
            // bisa sama-sama lolos pengecekan dan membuat dua sesi open.
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

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
     * Ringkasan kas transaksi yang tertaut ke sesi ini. Transaksi lunas dan
     * yang sudah direfund (penuh/sebagian) ikut dihitung, refund tunai
     * dikurangkan, dan gerakan kas (setoran/ambil) ditambahkan ke kas
     * seharusnya.
     *
     * @return array{transaction_count: int, total_sales: int, cash_sales: int, non_cash_sales: int, cash_refunds: int, cash_movements_in: int, cash_movements_out: int, cash_movement_count: int, expected_cash: int}
     */
    public function cashSummary(ShiftSession $session): array
    {
        $base = Transaction::query()
            ->where('shift_session_id', $session->id)
            ->whereIn('payment_status', self::SETTLED_PAYMENT_STATUSES);

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
     * snapshot penuh yang disimpan saat penutupan (agar historis tidak berubah
     * ketika transaksi/gerakan kas diubah), sesi terbuka dihitung live.
     *
     * @return array{transaction_count: int, total_sales: int, cash_sales: int, non_cash_sales: int, cash_refunds: int, cash_movements_in: int, cash_movements_out: int, cash_movement_count: int, expected_cash: int, closing_cash: int|null, variance: int|null}
     */
    public function reconciliation(ShiftSession $session): array
    {
        if ($session->isOpen()) {
            $summary = $this->cashSummary($session);
            $summary['closing_cash'] = null;
            $summary['variance'] = null;

            return $summary;
        }

        $summary = $this->closedSummary($session);

        $summary['expected_cash'] = $session->expected_cash ?? $summary['expected_cash'];
        $summary['closing_cash'] = $session->closing_cash;
        $summary['variance'] = $session->variance;

        return $summary;
    }

    /**
     * Versi batch dari reconciliation() untuk daftar/laporan: sesi yang butuh
     * hitungan live diambil dengan dua query agregat, bukan tujuh query per
     * sesi.
     *
     * @param  iterable<ShiftSession>  $sessions
     * @return array<int, array{transaction_count: int, total_sales: int, cash_sales: int, non_cash_sales: int, cash_refunds: int, cash_movements_in: int, cash_movements_out: int, cash_movement_count: int, expected_cash: int, closing_cash: int|null, variance: int|null}>
     */
    public function reconciliations(iterable $sessions): array
    {
        $summaries = [];
        $live = [];

        foreach ($sessions as $session) {
            if (! $session->isOpen() && is_array($session->summary_snapshot)) {
                $summary = $this->closedSummary($session);
                $summary['expected_cash'] = $session->expected_cash ?? $summary['expected_cash'];
                $summary['closing_cash'] = $session->closing_cash;
                $summary['variance'] = $session->variance;
                $summaries[(int) $session->id] = $summary;
            } else {
                $live[(int) $session->id] = $session;
            }
        }

        if ($live === []) {
            return $summaries;
        }

        $transactionTotals = Transaction::query()
            ->whereIn('shift_session_id', array_keys($live))
            ->whereIn('payment_status', self::SETTLED_PAYMENT_STATUSES)
            ->groupBy('shift_session_id')
            ->selectRaw('shift_session_id, COUNT(*) as transaction_count, COALESCE(SUM(total), 0) as total_sales')
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total ELSE 0 END), 0) as cash_sales")
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN refunded_amount ELSE 0 END), 0) as cash_refunds")
            ->toBase()
            ->get()
            ->keyBy('shift_session_id');

        $movementTotals = ShiftCashMovement::query()
            ->whereIn('shift_session_id', array_keys($live))
            ->groupBy('shift_session_id')
            ->selectRaw('shift_session_id, COUNT(*) as movement_count')
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END), 0) as movements_in")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END), 0) as movements_out")
            ->toBase()
            ->get()
            ->keyBy('shift_session_id');

        foreach ($live as $id => $session) {
            $transactions = $transactionTotals->get($id);
            $movements = $movementTotals->get($id);

            $totalSales = (int) ($transactions->total_sales ?? 0);
            $cashSales = (int) ($transactions->cash_sales ?? 0);
            $cashRefunds = (int) ($transactions->cash_refunds ?? 0);
            $movementsIn = (int) ($movements->movements_in ?? 0);
            $movementsOut = (int) ($movements->movements_out ?? 0);

            $summary = [
                'transaction_count' => (int) ($transactions->transaction_count ?? 0),
                'total_sales' => $totalSales,
                'cash_sales' => $cashSales,
                'non_cash_sales' => $totalSales - $cashSales,
                'cash_refunds' => $cashRefunds,
                'cash_movements_in' => $movementsIn,
                'cash_movements_out' => $movementsOut,
                'cash_movement_count' => (int) ($movements->movement_count ?? 0),
                'expected_cash' => (int) $session->opening_cash + $cashSales - $cashRefunds + $movementsIn - $movementsOut,
                'closing_cash' => $session->isOpen() ? null : $session->closing_cash,
                'variance' => $session->isOpen() ? null : $session->variance,
            ];

            if (! $session->isOpen()) {
                $summary['expected_cash'] = $session->expected_cash ?? $summary['expected_cash'];
            }

            $summaries[$id] = $summary;
        }

        return $summaries;
    }

    /**
     * Ringkasan tersimpan untuk sesi tertutup. Sesi lama yang belum punya
     * snapshot dihitung live sebagai fallback.
     *
     * @return array{transaction_count: int, total_sales: int, cash_sales: int, non_cash_sales: int, cash_refunds: int, cash_movements_in: int, cash_movements_out: int, cash_movement_count: int, expected_cash: int}
     */
    private function closedSummary(ShiftSession $session): array
    {
        $snapshot = $session->summary_snapshot;

        if (! is_array($snapshot)) {
            return $this->cashSummary($session);
        }

        return [
            'transaction_count' => (int) ($snapshot['transaction_count'] ?? 0),
            'total_sales' => (int) ($snapshot['total_sales'] ?? 0),
            'cash_sales' => (int) ($snapshot['cash_sales'] ?? 0),
            'non_cash_sales' => (int) ($snapshot['non_cash_sales'] ?? 0),
            'cash_refunds' => (int) ($snapshot['cash_refunds'] ?? 0),
            'cash_movements_in' => (int) ($snapshot['cash_movements_in'] ?? 0),
            'cash_movements_out' => (int) ($snapshot['cash_movements_out'] ?? 0),
            'cash_movement_count' => (int) ($snapshot['cash_movement_count'] ?? 0),
            'expected_cash' => (int) ($snapshot['expected_cash'] ?? 0),
        ];
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

    /**
     * Batalkan gerakan kas pada sesi yang sudah ditutup secara append-only:
     * koreksinya dicatat sebagai baris baru berlawanan arah yang menunjuk baris
     * aslinya. Snapshot rekonsiliasi sesi tidak berubah sehingga angka kas
     * historis tetap dan koreksi tetap terlihat di jejak gerakan kas.
     *
     * @throws RuntimeException bila sesi masih terbuka, alasan kosong, atau
     *                          gerakan kas sudah pernah dibatalkan.
     */
    public function reverseMovement(ShiftCashMovement $movement, User $user, string $reason): ShiftCashMovement
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new RuntimeException('Alasan pembatalan gerakan kas wajib diisi.');
        }

        return DB::transaction(function () use ($movement, $user, $reason): ShiftCashMovement {
            $session = ShiftSession::query()
                ->whereKey($movement->shift_session_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($session->isOpen()) {
                throw new RuntimeException('Sesi kas masih terbuka; hapus gerakan kas alih-alih membatalkannya.');
            }

            $original = ShiftCashMovement::query()
                ->whereKey($movement->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($original->isReversed()) {
                throw new RuntimeException('Gerakan kas ini sudah dibatalkan.');
            }

            return ShiftCashMovement::query()->create([
                'shift_session_id' => $original->shift_session_id,
                'type' => $original->isIn() ? 'out' : 'in',
                'amount' => $original->amount,
                'note' => 'Pembatalan: '.$reason,
                'created_by_user_id' => $user->id,
                'reversal_of_id' => $original->id,
            ]);
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
                'summary_snapshot' => $summary,
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
