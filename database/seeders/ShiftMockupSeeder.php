<?php

namespace Database\Seeders;

use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\ShiftSession;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Data mockup untuk simulasi alur shift: jadwal 7 hari, sesi kas tertutup
 * lengkap dengan transaksi contoh, dan satu sesi yang masih terbuka.
 *
 * Jalankan manual: php artisan db:seed --class=Database\\Seeders\\ShiftMockupSeeder
 */
class ShiftMockupSeeder extends Seeder
{
    private const OPENING_CASH = 200000;

    private const MOCKUP_NOTE = 'Mockup simulasi';

    /** Variasi selisih kas (rupiah) agar laporan tidak terlihat rata. */
    private const VARIANCES = [0, -5000, 0, 2000, 0, -10000, 0];

    public function run(): void
    {
        if (ShiftSession::query()->where('note', self::MOCKUP_NOTE)->exists()) {
            $this->command?->info('Data mockup shift sudah ada, seeder dilewati.');

            return;
        }

        $shifts = Shift::query()->active()->orderBy('sort_order')->orderBy('start_time')->take(2)->get();

        if ($shifts->count() < 2) {
            $this->command?->warn('Butuh minimal 2 shift aktif. Jalankan ShiftSeeder lebih dulu.');

            return;
        }

        $cashiers = User::query()->where('is_active', true)->orderBy('id')->take(2)->get();

        if ($cashiers->isEmpty()) {
            $this->command?->warn('Tidak ada user aktif untuk dijadikan kasir mockup.');

            return;
        }

        $pagiShift = $shifts[0];
        $soreShift = $shifts[1];
        $pagiUser = $cashiers[0];
        $soreUser = $cashiers[1] ?? $cashiers[0];

        $today = CarbonImmutable::now()->startOfDay();

        for ($day = 0; $day < 7; $day++) {
            $date = $today->subDays($day)->toDateString();
            $this->assign($pagiShift, $pagiUser, $date);
            $this->assign($soreShift, $soreUser, $date);
        }

        for ($day = 6; $day >= 0; $day--) {
            $date = $today->subDays($day);

            $this->closedSession($pagiShift, $pagiUser, $date, self::VARIANCES[$day]);

            if ($day > 0) {
                $this->closedSession($soreShift, $soreUser, $date, self::VARIANCES[($day + 3) % count(self::VARIANCES)]);
            }
        }

        $this->openSession($soreShift, $soreUser, $today);

        $this->command?->info('Data mockup shift dibuat: 7 hari jadwal, 13 sesi tertutup, 1 sesi terbuka.');
    }

    private function assign(Shift $shift, User $user, string $date): void
    {
        ShiftAssignment::query()->firstOrCreate(
            ['shift_id' => $shift->id, 'user_id' => $user->id, 'work_date' => $date],
            ['note' => self::MOCKUP_NOTE, 'created_by_user_id' => $user->id],
        );
    }

    private function closedSession(Shift $shift, User $user, CarbonImmutable $date, int $variance): void
    {
        $openedAt = $this->startOf($shift, $date);
        $closedAt = $openedAt->addMinutes($shift->durationMinutes())->subMinutes(10);

        $session = ShiftSession::query()->create([
            'shift_id' => $shift->id,
            'user_id' => $user->id,
            'business_date' => $date->toDateString(),
            'opened_at' => $openedAt,
            'opening_cash' => self::OPENING_CASH,
            'status' => 'open',
            'note' => self::MOCKUP_NOTE,
        ]);

        $this->seedTransactions($session, $openedAt, $date);

        $cashSales = (int) Transaction::query()
            ->where('shift_session_id', $session->id)
            ->where('payment_status', 'paid')
            ->where('payment_method', 'cash')
            ->sum('total');

        $expectedCash = self::OPENING_CASH + $cashSales;

        $session->forceFill([
            'status' => 'closed',
            'closed_at' => $closedAt,
            'closing_cash' => $expectedCash + $variance,
            'expected_cash' => $expectedCash,
            'variance' => $variance,
            'closed_by_user_id' => $user->id,
        ])->save();
    }

    private function openSession(Shift $shift, User $user, CarbonImmutable $today): void
    {
        if (ShiftSession::query()->where('user_id', $user->id)->open()->exists()) {
            return;
        }

        $openedAt = $this->startOf($shift, $today);

        if ($openedAt->isFuture()) {
            $openedAt = CarbonImmutable::now()->subHours(2);
        }

        $session = ShiftSession::query()->create([
            'shift_id' => $shift->id,
            'user_id' => $user->id,
            'business_date' => $today->toDateString(),
            'opened_at' => $openedAt,
            'opening_cash' => 250000,
            'status' => 'open',
            'note' => self::MOCKUP_NOTE,
        ]);

        $this->seedTransactions($session, CarbonImmutable::now()->subMinutes(50), $today);
    }

    private function seedTransactions(ShiftSession $session, CarbonImmutable $anchor, CarbonImmutable $date): void
    {
        $sales = [
            ['method' => 'cash', 'total' => 18000],
            ['method' => 'qris', 'total' => 23000],
            ['method' => 'cash', 'total' => 15000],
        ];

        foreach ($sales as $index => $sale) {
            $paidAt = $anchor->addMinutes(45 + ($index * 70));

            if ($paidAt->isFuture()) {
                $paidAt = CarbonImmutable::now()->subMinutes(5);
            }

            $code = sprintf('MOCK-%s-%d-%d', $date->format('Ymd'), (int) $session->id, $index + 1);

            Transaction::query()->firstOrCreate(['code' => $code], [
                'channel' => 'pos',
                'shift_session_id' => $session->id,
                'name' => 'Pelanggan Mockup',
                'order_type' => 'take_away',
                'subtotal' => $sale['total'],
                'total' => $sale['total'],
                'checkout_link' => '-',
                'payment_method' => $sale['method'],
                'payment_status' => 'paid',
                'order_status' => 'completed',
                'paid_at' => $paidAt,
                'external_id' => 'MOCK-EXT-'.$code,
                // Ditandai sudah diproses agar observer tidak memotong stok.
                'inventory_applied_at' => $paidAt,
                'created_at' => $paidAt,
                'updated_at' => $paidAt,
            ]);
        }
    }

    private function startOf(Shift $shift, CarbonImmutable $date): CarbonImmutable
    {
        return $date->addMinutes($shift->startTimeMinutes());
    }
}
