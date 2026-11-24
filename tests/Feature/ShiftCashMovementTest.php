<?php

use App\Livewire\Shifts\ShiftSessionsPage;
use App\Models\ShiftCashMovement;
use App\Models\ShiftSession;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Shifts\ShiftSessionService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->service = app(ShiftSessionService::class);
});

function makeOpenSessionFor(User $user, int $openingCash = 100000): ShiftSession
{
    return app(ShiftSessionService::class)->open($user, null, $openingCash);
}

function makeShiftTransaction(ShiftSession $session, array $attributes = []): Transaction
{
    return Transaction::query()->create(array_merge([
        'code' => 'TRX-'.uniqid(),
        'external_id' => 'EXT-'.uniqid(),
        'name' => 'Guest',
        'checkout_link' => '',
        'subtotal' => 100,
        'total' => 100,
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'shift_session_id' => $session->id,
    ], $attributes));
}

function makeClosedMovement(User $user, int $amount = 10000): ShiftCashMovement
{
    $session = makeOpenSessionFor($user, 100000);
    $movement = app(ShiftSessionService::class)->recordMovement($session, 'in', $amount, null, null, $user);
    app(ShiftSessionService::class)->close($session->fresh(), 100000, $user);

    return $movement;
}

test('records cash movement in and out and affects expected cash', function () {
    $user = User::factory()->create();
    $session = makeOpenSessionFor($user, 100000);

    $in = $this->service->recordMovement($session, 'in', 50000, 'Setoran', 'tambah modal', $user);
    $out = $this->service->recordMovement($session, 'out', 20000, 'Ambil', 'beli galon', $user);

    expect($in->type)->toBe('in')
        ->and($out->type)->toBe('out')
        ->and(ShiftCashMovement::query()->where('shift_session_id', $session->id)->count())->toBe(2);

    $summary = $this->service->cashSummary($session->fresh());

    expect($summary['cash_movements_in'])->toBe(50000)
        ->and($summary['cash_movements_out'])->toBe(20000)
        ->and($summary['cash_movement_count'])->toBe(2)
        ->and($summary['expected_cash'])->toBe(130000);
});

test('recordMovement rejects invalid type', function () {
    $user = User::factory()->create();
    $session = makeOpenSessionFor($user);

    expect(fn () => $this->service->recordMovement($session, 'sideways', 1000, null, null, $user))
        ->toThrow(RuntimeException::class);
});

test('recordMovement rejects non-positive amount', function () {
    $user = User::factory()->create();
    $session = makeOpenSessionFor($user);

    expect(fn () => $this->service->recordMovement($session, 'in', 0, null, null, $user))
        ->toThrow(RuntimeException::class);
});

test('recordMovement rejects closed session', function () {
    $user = User::factory()->create();
    $session = makeOpenSessionFor($user);
    $this->service->close($session, 0, $user);

    expect(fn () => $this->service->recordMovement($session->fresh(), 'in', 1000, null, null, $user))
        ->toThrow(RuntimeException::class);
});

test('deleteMovement removes movement on open session', function () {
    $user = User::factory()->create();
    $session = makeOpenSessionFor($user);
    $movement = $this->service->recordMovement($session, 'in', 50000, null, null, $user);

    $this->service->deleteMovement($movement);

    expect(ShiftCashMovement::query()->whereKey($movement->id)->exists())->toBeFalse();
});

test('deleteMovement rejects closed session', function () {
    $user = User::factory()->create();
    $session = makeOpenSessionFor($user);
    $movement = $this->service->recordMovement($session, 'in', 50000, null, null, $user);

    $this->service->close($session->fresh(), 0, $user);

    expect(fn () => $this->service->deleteMovement($movement))
        ->toThrow(RuntimeException::class);

    expect(ShiftCashMovement::query()->whereKey($movement->id)->exists())->toBeTrue();
});

test('reversing a closed session movement appends the opposite row and keeps stored figures', function () {
    $user = User::factory()->create();
    $session = makeOpenSessionFor($user, 100000);
    $movement = $this->service->recordMovement($session, 'in', 50000, 'Setoran', null, $user);

    $closed = $this->service->close($session->fresh(), 150000, $user);
    $before = $this->service->reconciliation($closed->fresh());

    $reversal = $this->service->reverseMovement($movement, $user, 'Salah input setoran');

    expect($reversal->type)->toBe('out')
        ->and((int) $reversal->amount)->toBe(50000)
        ->and((int) $reversal->reversal_of_id)->toBe((int) $movement->id)
        ->and((int) $reversal->shift_session_id)->toBe((int) $session->id)
        ->and((int) $reversal->created_by_user_id)->toBe((int) $user->id)
        ->and($reversal->note)->toBe('Pembatalan: Salah input setoran');

    expect($movement->fresh()->isReversed())->toBeTrue()
        ->and(ShiftCashMovement::query()->where('shift_session_id', $session->id)->count())->toBe(2)
        // Append-only: koreksi tidak mengubah angka sesi yang sudah ditutup.
        ->and($this->service->reconciliation($closed->fresh()))->toMatchArray($before);
});

test('reverseMovement rejects an open session', function () {
    $user = User::factory()->create();
    $session = makeOpenSessionFor($user);
    $movement = $this->service->recordMovement($session, 'in', 10000, null, null, $user);

    expect(fn () => $this->service->reverseMovement($movement, $user, 'Salah input'))
        ->toThrow(RuntimeException::class);

    expect($movement->fresh()->isReversed())->toBeFalse();
});

test('reverseMovement requires a reason and allows only one reversal', function () {
    $user = User::factory()->create();
    $movement = makeClosedMovement($user);
    $movements = fn () => ShiftCashMovement::query()->where('shift_session_id', $movement->shift_session_id)->count();

    expect(fn () => $this->service->reverseMovement($movement, $user, '   '))
        ->toThrow(RuntimeException::class);

    expect($movements())->toBe(1);

    $this->service->reverseMovement($movement, $user, 'Salah input');

    expect($movement->fresh()->isReversed())->toBeTrue()
        ->and($movements())->toBe(2);

    expect(fn () => $this->service->reverseMovement($movement, $user, 'Salah input lagi'))
        ->toThrow(RuntimeException::class);

    expect($movements())->toBe(2);
});

test('shift sessions page reverses a closed movement only with a reason', function () {
    $movement = makeClosedMovement(User::factory()->create(), 50000);

    $admin = User::factory()->create();
    $admin->assignRole('owner');

    Livewire::actingAs($admin)
        ->test(ShiftSessionsPage::class)
        ->call('openMovementModal', (int) $movement->shift_session_id)
        ->assertSet('movementModalOpen', true)
        ->assertSee('Sesi kas sudah ditutup')
        ->call('startReversal', (int) $movement->id)
        ->assertSet('reversalMovementId', (int) $movement->id)
        ->set('reversalReason', '   ')
        ->call('confirmReversal')
        ->assertHasErrors(['reversalReason']);

    expect($movement->fresh()->isReversed())->toBeFalse();

    Livewire::actingAs($admin)
        ->test(ShiftSessionsPage::class)
        ->call('startReversal', (int) $movement->id)
        ->set('reversalReason', 'Salah input setoran')
        ->call('confirmReversal')
        ->assertHasNoErrors()
        ->assertSee('Dibatalkan');

    expect($movement->fresh()->isReversed())->toBeTrue()
        ->and(ShiftCashMovement::query()->where('shift_session_id', $movement->shift_session_id)->count())->toBe(2);
});

test('cashier reverses own closed movement but not another cashiers', function () {
    $cashier = User::factory()->create();
    $cashier->assignRole('cashier');

    $own = makeClosedMovement($cashier);

    Livewire::actingAs($cashier)
        ->test(ShiftSessionsPage::class)
        ->call('startReversal', (int) $own->id)
        ->set('reversalReason', 'Salah input')
        ->call('confirmReversal')
        ->assertHasNoErrors();

    expect($own->fresh()->isReversed())->toBeTrue();

    $other = makeClosedMovement(User::factory()->create());

    Livewire::actingAs($cashier)
        ->test(ShiftSessionsPage::class)
        ->call('startReversal', (int) $other->id)
        ->assertStatus(403);

    expect($other->fresh()->isReversed())->toBeFalse();
});

test('close stores expected cash including movements', function () {
    $user = User::factory()->create();
    $session = makeOpenSessionFor($user, 100000);

    $this->service->recordMovement($session, 'in', 50000, null, null, $user);
    $this->service->recordMovement($session, 'out', 20000, null, null, $user);

    $closed = $this->service->close($session->fresh(), 130000, $user);

    expect($closed->status)->toBe('closed')
        ->and((int) $closed->expected_cash)->toBe(130000)
        ->and((int) $closed->closing_cash)->toBe(130000)
        ->and((int) $closed->variance)->toBe(0);
});

test('cannot create transaction linked to closed session', function () {
    $user = User::factory()->create();
    $session = makeOpenSessionFor($user);
    $this->service->close($session, 0, $user);

    expect(fn () => makeShiftTransaction($session))->toThrow(RuntimeException::class);
});

test('cannot reassign transaction to closed session', function () {
    $user = User::factory()->create();
    $openSession = makeOpenSessionFor($user);
    $closedSession = makeOpenSessionFor(User::factory()->create());
    $this->service->close($closedSession, 0, $closedSession->user);

    $transaction = makeShiftTransaction($openSession);

    $this->service->close($openSession->fresh(), 0, $user);

    expect(fn () => $transaction->update(['shift_session_id' => $closedSession->id]))
        ->toThrow(RuntimeException::class);
});

test('cannot detach a transaction from its cash session', function (bool $closed) {
    $user = User::factory()->create();
    $session = makeOpenSessionFor($user);

    $transaction = makeShiftTransaction($session);

    if ($closed) {
        $this->service->close($session->fresh(), 0, $user);
    }

    expect(fn () => $transaction->update(['shift_session_id' => null]))
        ->toThrow(RuntimeException::class);

    expect((int) $transaction->fresh()->shift_session_id)->toBe((int) $session->id);
})->with([
    'sesi tertutup' => true,
    'sesi terbuka' => false,
]);

test('cannot move transaction from closed session to another open session', function () {
    $user = User::factory()->create();
    $session = makeOpenSessionFor($user);

    $transaction = makeShiftTransaction($session);

    $this->service->close($session->fresh(), 0, $user);

    $otherSession = makeOpenSessionFor(User::factory()->create());

    expect(fn () => $transaction->update(['shift_session_id' => $otherSession->id]))
        ->toThrow(RuntimeException::class);

    expect((int) $transaction->fresh()->shift_session_id)->toBe((int) $session->id);
});

test('can edit other fields of transaction linked to closed session', function () {
    $user = User::factory()->create();
    $session = makeOpenSessionFor($user);

    $transaction = makeShiftTransaction($session);

    $this->service->close($session->fresh(), 0, $user);

    $transaction->update(['total' => 200, 'name' => 'Updated']);

    expect((int) $transaction->fresh()->total)->toBe(200)
        ->and($transaction->fresh()->name)->toBe('Updated');
});

test('can create transaction linked to open session', function () {
    $user = User::factory()->create();
    $session = makeOpenSessionFor($user);

    $transaction = makeShiftTransaction($session);

    expect($transaction)->not->toBeNull()
        ->and((int) $transaction->shift_session_id)->toBe((int) $session->id);
});

test('shift sessions page records movement via modal', function () {
    $cashier = User::factory()->create();
    $session = makeOpenSessionFor($cashier, 100000);

    $admin = User::factory()->create();
    $admin->assignRole('owner');

    Livewire::actingAs($admin)
        ->test(ShiftSessionsPage::class)
        ->call('openMovementModal', (int) $session->id)
        ->set('movementType', 'in')
        ->set('movementAmount', '50000')
        ->set('movementCategory', 'Setoran')
        ->call('saveMovement')
        ->assertHasNoErrors();

    expect(ShiftCashMovement::query()->where('shift_session_id', $session->id)->count())->toBe(1);
});

test('shift sessions page deletes movement via modal', function () {
    $cashier = User::factory()->create();
    $session = makeOpenSessionFor($cashier, 100000);
    $movement = $this->service->recordMovement($session, 'in', 50000, null, null, $cashier);

    $admin = User::factory()->create();
    $admin->assignRole('owner');

    Livewire::actingAs($admin)
        ->test(ShiftSessionsPage::class)
        ->call('openMovementModal', (int) $session->id)
        ->call('deleteMovement', (int) $movement->id)
        ->assertHasNoErrors();

    expect(ShiftCashMovement::query()->whereKey($movement->id)->exists())->toBeFalse();
});

test('close.any-only user can open and use cash movement modal on another users session', function () {
    $cashier = User::factory()->create();
    $session = makeOpenSessionFor($cashier, 100000);

    $role = Role::firstOrCreate(['name' => 'close_any_only']);
    $role->syncPermissions(['shifts.view', 'shifts.sessions.close.any']);

    $manager = User::factory()->create();
    $manager->assignRole('close_any_only');

    Livewire::actingAs($manager)
        ->test(ShiftSessionsPage::class)
        ->call('openMovementModal', (int) $session->id)
        ->assertSet('movementModalOpen', true)
        ->set('movementType', 'in')
        ->set('movementAmount', '25000')
        ->set('movementCategory', 'Setoran')
        ->call('saveMovement')
        ->assertHasNoErrors();

    $movement = ShiftCashMovement::query()->where('shift_session_id', $session->id)->first();
    expect($movement)->not->toBeNull();

    Livewire::actingAs($manager)
        ->test(ShiftSessionsPage::class)
        ->call('deleteMovement', (int) $movement->id)
        ->assertHasNoErrors();

    expect(ShiftCashMovement::query()->whereKey($movement->id)->exists())->toBeFalse();
});
