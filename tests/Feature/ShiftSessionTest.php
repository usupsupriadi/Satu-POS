<?php

use App\Models\Shift;
use App\Models\ShiftSession;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Shifts\ShiftSessionService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->service = app(ShiftSessionService::class);
});

function makeShift(array $attributes = []): Shift
{
    return Shift::query()->create(array_merge([
        'name' => 'Pagi',
        'start_time' => '08:00',
        'end_time' => '16:00',
        'is_active' => true,
        'sort_order' => 1,
    ], $attributes));
}

function makePaidTransaction(ShiftSession $session, int $total, string $paymentMethod = 'cash'): Transaction
{
    return Transaction::query()->create([
        'code' => 'TRX-'.uniqid(),
        'external_id' => 'EXT-'.uniqid(),
        'name' => 'Guest',
        'checkout_link' => '',
        'subtotal' => $total,
        'total' => $total,
        'payment_method' => $paymentMethod,
        'payment_status' => 'paid',
        'shift_session_id' => $session->id,
    ]);
}

test('opens a cash session with opening cash and business date', function () {
    $user = User::factory()->create();
    $shift = makeShift();

    $session = $this->service->open($user, $shift, 200000);

    expect($session->status)->toBe('open')
        ->and((int) $session->opening_cash)->toBe(200000)
        ->and((int) $session->user_id)->toBe((int) $user->id)
        ->and((int) $session->shift_id)->toBe((int) $shift->id)
        ->and($session->business_date->toDateString())->toBe(CarbonImmutable::now()->toDateString());
});

test('rejects a second open session for the same cashier', function () {
    $user = User::factory()->create();

    $this->service->open($user, null, 0);

    expect(fn () => $this->service->open($user, null, 0))
        ->toThrow(RuntimeException::class);

    expect(ShiftSession::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('closing a session stores expected cash and variance from paid transactions', function () {
    $user = User::factory()->create();

    $session = $this->service->open($user, null, 100000);

    makePaidTransaction($session, 50000, 'cash');
    makePaidTransaction($session, 30000, 'qris');
    makePaidTransaction($session, 99000, 'cash')->update(['payment_status' => 'pending']);

    $closed = $this->service->close($session, 145000, $user, 'Hitung ulang');

    expect($closed->status)->toBe('closed')
        ->and((int) $closed->expected_cash)->toBe(150000)
        ->and((int) $closed->closing_cash)->toBe(145000)
        ->and((int) $closed->variance)->toBe(-5000)
        ->and((int) $closed->closed_by_user_id)->toBe((int) $user->id)
        ->and($closed->note)->toBe('Hitung ulang');
});

test('refunded cash reduces expected cash', function () {
    $user = User::factory()->create();
    $session = $this->service->open($user, null, 0);

    $transaction = makePaidTransaction($session, 80000, 'cash');
    $transaction->update(['refunded_amount' => 30000]);

    $summary = $this->service->cashSummary($session);

    expect($summary['cash_sales'])->toBe(80000)
        ->and($summary['cash_refunds'])->toBe(30000)
        ->and($summary['expected_cash'])->toBe(50000);
});

test('rejects closing an already closed session', function () {
    $user = User::factory()->create();
    $session = $this->service->open($user, null, 0);

    $this->service->close($session, 0, $user);

    expect(fn () => $this->service->close($session->fresh(), 0, $user))
        ->toThrow(RuntimeException::class);
});

test('cross midnight shift uses the previous business date when opened after midnight', function () {
    $shift = makeShift(['name' => 'Malam', 'start_time' => '22:00', 'end_time' => '06:00']);

    expect($shift->crossesMidnight())->toBeTrue()
        ->and($this->service->businessDateFor($shift, CarbonImmutable::parse('2026-11-19 02:00:00')))->toBe('2026-11-18')
        ->and($this->service->businessDateFor($shift, CarbonImmutable::parse('2026-11-19 22:30:00')))->toBe('2026-11-19');
});

test('closed session reconciliation keeps stored cash figures', function () {
    $user = User::factory()->create();
    $session = $this->service->open($user, null, 100000);

    $transaction = makePaidTransaction($session, 50000, 'cash');
    $closed = $this->service->close($session, 150000, $user);

    // Transaksi berubah setelah sesi ditutup; angka rekonsiliasi tidak boleh ikut berubah.
    $transaction->update(['total' => 90000]);

    $closedSummary = $this->service->reconciliation($closed->fresh());
    $openSummary = $this->service->reconciliation($this->service->open($user, null, 100000));

    expect($closedSummary['expected_cash'])->toBe(150000)
        ->and($closedSummary['closing_cash'])->toBe(150000)
        ->and($closedSummary['variance'])->toBe(0)
        ->and($openSummary['expected_cash'])->toBe(100000)
        ->and($openSummary['closing_cash'])->toBeNull()
        ->and($openSummary['variance'])->toBeNull();
});

test('active session lookup returns only the open session of that cashier', function () {
    $cashier = User::factory()->create();
    $other = User::factory()->create();

    $session = $this->service->open($cashier, null, 0);
    $this->service->open($other, null, 0);

    $this->service->close($session, 0, $cashier);

    expect($this->service->activeFor($cashier))->toBeNull()
        ->and($this->service->activeFor($other))->not->toBeNull();
});
