<?php

use App\Livewire\Shifts\ShiftReportPage;
use App\Livewire\Shifts\ShiftSessionsPage;
use App\Models\Setting;
use App\Models\ShiftSession;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Shifts\ShiftSessionService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->service = app(ShiftSessionService::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('owner');
});

function shiftRecordCashSale(ShiftSession $session, int $total): Transaction
{
    return Transaction::query()->create([
        'code' => 'TRX-'.uniqid(),
        'external_id' => 'EXT-'.uniqid(),
        'name' => 'Guest',
        'checkout_link' => '',
        'subtotal' => $total,
        'total' => $total,
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'shift_session_id' => $session->id,
    ]);
}

/**
 * Sesi ditutup dengan kas fisik tertentu (kas seharusnya = kas awal + penjualan
 * tunai), lalu nominal transaksinya diubah supaya hitungan live menyimpang.
 */
function shiftClosedSession(ShiftSessionService $service, int $openingCash, int $cashSales, int $closingCash, int $mutatedTotal): ShiftSession
{
    $cashier = User::factory()->create();
    $session = $service->open($cashier, null, $openingCash);
    $transaction = shiftRecordCashSale($session, $cashSales);
    $closed = $service->close($session, $closingCash, $cashier);

    $transaction->update(['total' => $mutatedTotal]);

    return $closed;
}

/**
 * Dua sesi tertutup (kas fisik pas dan kurang 10.000) plus satu sesi terbuka.
 *
 * @return array{closedA: ShiftSession, closedB: ShiftSession, open: ShiftSession}
 */
function shiftReconciliationScenario(ShiftSessionService $service): array
{
    $closedA = shiftClosedSession($service, openingCash: 100000, cashSales: 50000, closingCash: 150000, mutatedTotal: 90000);
    $closedB = shiftClosedSession($service, openingCash: 200000, cashSales: 30000, closingCash: 220000, mutatedTotal: 40000);

    $openCashier = User::factory()->create();
    $open = $service->open($openCashier, null, 50000);
    shiftRecordCashSale($open, 20000);

    return ['closedA' => $closedA, 'closedB' => $closedB, 'open' => $open];
}

function shiftMoney(int $amount): string
{
    return 'Rp'.number_format($amount, 0, ',', '.');
}

test('cash session list uses stored figures for closed sessions and live figures for open sessions', function () {
    $scenario = shiftReconciliationScenario($this->service);

    $component = Livewire::actingAs($this->admin)->test(ShiftSessionsPage::class)->assertOk();

    $summaries = $component->viewData('summaries');
    $closedA = $summaries[(int) $scenario['closedA']->id];
    $closedB = $summaries[(int) $scenario['closedB']->id];
    $open = $summaries[(int) $scenario['open']->id];

    expect($closedA['expected_cash'])->toBe(150000)
        ->and($closedA['closing_cash'])->toBe(150000)
        ->and($closedA['variance'])->toBe(0)
        ->and($closedB['expected_cash'])->toBe(230000)
        ->and($closedB['closing_cash'])->toBe(220000)
        ->and($closedB['variance'])->toBe(-10000)
        ->and($open['expected_cash'])->toBe(70000)
        ->and($open['closing_cash'])->toBeNull()
        ->and($open['variance'])->toBeNull();

    $component
        ->assertSee(shiftMoney($closedA['expected_cash']))
        ->assertSee(shiftMoney($closedB['expected_cash']))
        ->assertSee(shiftMoney($open['expected_cash']))
        // Hitung ulang sesi A (100.000 + 90.000) tidak boleh tampil.
        ->assertDontSee(shiftMoney(190000));
});

test('shift report uses stored figures for closed sessions and totals match the rows', function () {
    $scenario = shiftReconciliationScenario($this->service);

    $component = Livewire::actingAs($this->admin)->test(ShiftReportPage::class)->assertOk();

    $summaries = $component->viewData('summaries');
    $totals = $component->viewData('totals');

    expect($summaries[(int) $scenario['closedA']->id]['expected_cash'])->toBe(150000)
        ->and($summaries[(int) $scenario['closedA']->id]['variance'])->toBe(0)
        ->and($summaries[(int) $scenario['closedB']->id]['expected_cash'])->toBe(230000)
        ->and($summaries[(int) $scenario['closedB']->id]['variance'])->toBe(-10000)
        ->and($summaries[(int) $scenario['open']->id]['expected_cash'])->toBe(70000)
        ->and($summaries[(int) $scenario['open']->id]['variance'])->toBeNull()
        ->and($totals)->toMatchArray([
            'sessions' => 3,
            'sessions_open' => 1,
            'expected_cash' => 450000,
            'closing_cash' => 370000,
            'variance' => -10000,
        ]);

    // Total kartu harus sama dengan penjumlahan nilai baris yang ditampilkan.
    expect(array_sum(array_column($summaries, 'expected_cash')))->toBe($totals['expected_cash'])
        ->and(array_sum(array_column($summaries, 'cash_sales')))->toBe($totals['cash_sales']);

    $component
        ->assertSee(shiftMoney($totals['expected_cash']))
        ->assertSee(shiftMoney($totals['variance']));
});

test('batch reconciliations match per-session reconciliation', function () {
    $scenario = shiftReconciliationScenario($this->service);
    $this->service->recordMovement($scenario['open'], 'in', 15000, null, null, $scenario['open']->user);

    // Sesi tertutup lama tanpa snapshot: harus dihitung live seperti reconciliation().
    $legacy = shiftClosedSession($this->service, openingCash: 80000, cashSales: 25000, closingCash: 105000, mutatedTotal: 40000);
    $legacy->forceFill(['summary_snapshot' => null])->save();

    $sessions = ShiftSession::query()
        ->whereIn('id', [
            $scenario['closedA']->id,
            $scenario['closedB']->id,
            $scenario['open']->id,
            $legacy->id,
        ])
        ->get();

    $batch = $this->service->reconciliations($sessions);

    expect($batch)->toHaveCount(4);

    foreach ($sessions as $session) {
        expect($batch[(int) $session->id])->toBe($this->service->reconciliation($session));
    }
});

function shiftQueryCount(Closure $callback, ?Closure $filter = null): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $callback();
    } finally {
        $queries = collect(DB::getQueryLog());

        if ($filter !== null) {
            $queries = $queries->filter($filter);
        }

        $count = $queries->count();
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    return $count;
}

/**
 * Sesi terbuka dengan penjualan tunai dan satu gerakan kas: jalur live yang
 * paling mahal bila diringkas per baris.
 */
function shiftLiveSessions(ShiftSessionService $service, int $count): void
{
    for ($i = 0; $i < $count; $i++) {
        $cashier = User::factory()->create();
        $session = $service->open($cashier, null, 100000);
        shiftRecordCashSale($session, 20000 + $i * 1000);
        $service->recordMovement($session, 'in', 5000, null, null, $cashier);
    }
}

/**
 * Jumlah query satu render halaman Livewire penuh. Satu render pemanasan
 * dimuat lebih dulu supaya query cache peran/izin tidak ikut terhitung.
 *
 * Lookup tabel `settings` tidak dihitung: berapa query yang dipakai
 * Setting::current() bergantung pada implementasi cache-nya, sedangkan batas
 * di tes ini mengukur struktur query halaman shift. Tanpa pengecualian ini,
 * batas 8/13 hanya lulus bila cache Setting sudah ada.
 */
function shiftPageQueries(string $page, User $user): int
{
    $render = fn () => Livewire::actingAs($user)->test($page)->assertOk();

    $render();

    // Regresi: cache Setting::current() dikosongkan sebelum render yang diukur,
    // jadi batas 8/13 harus tetap lulus walau render berikutnya menembak query
    // settings. Kalau query settings ikut terhitung, render dingin ini gagal.
    Setting::forgetCurrent();

    return shiftQueryCount(
        $render,
        fn (array $query): bool => preg_match('/\b(?:from|join)\s+["`]?settings["`]?\b/i', (string) ($query['query'] ?? '')) !== 1,
    );
}

test('cash sessions page summarizes live sessions with aggregate queries only', function () {
    shiftReconciliationScenario($this->service);
    shiftLiveSessions($this->service, 6);

    $rows = ShiftSession::query()->orderBy('id')->get();

    $batchQueries = shiftQueryCount(fn () => $this->service->reconciliations($rows));
    $perRowQueries = shiftQueryCount(fn () => $rows->each(fn (ShiftSession $session) => $this->service->reconciliation($session)));
    $pageQueries = shiftPageQueries(ShiftSessionsPage::class, $this->admin);

    // 7 sesi live x 7 query cashSummary(); sesi tertutup bersnapshot tanpa query.
    // Terukur: batch 2 query, per baris 49 query, render halaman 8 query.
    expect($batchQueries)->toBe(2)
        ->and($perRowQueries)->toBe(49)
        ->and($pageQueries)->toBeLessThan($perRowQueries)
        // Batas ketat di angka terukur: gagal bila halaman menambah query.
        ->and($pageQueries)->toBeLessThanOrEqual(8);
});

test('shift report summarizes all rows with aggregate queries only', function () {
    shiftReconciliationScenario($this->service);
    shiftLiveSessions($this->service, 6);

    $rows = ShiftSession::query()->orderBy('id')->get();

    $batchQueries = shiftQueryCount(function () use ($rows): void {
        $this->service->reconciliations($rows);
        $this->service->reconciliations($rows);
    });
    $perRowQueries = shiftQueryCount(fn () => $rows->each(fn (ShiftSession $session) => $this->service->reconciliation($session)));
    $pageQueries = shiftPageQueries(ShiftReportPage::class, $this->admin);

    // Laporan merangkum baris dan total: dua pemanggilan batch = 4 query agregat.
    // Terukur: batch 4 query, per baris 49 query, render halaman 13 query.
    expect($batchQueries)->toBe(4)
        ->and($perRowQueries)->toBe(49)
        ->and($pageQueries)->toBeLessThan($perRowQueries)
        // Batas ketat di angka terukur: gagal bila halaman menambah query.
        ->and($pageQueries)->toBeLessThanOrEqual(13);
});

test('cash sessions page query count stays flat as live sessions grow', function () {
    shiftReconciliationScenario($this->service);
    shiftLiveSessions($this->service, 1);

    $small = shiftPageQueries(ShiftSessionsPage::class, $this->admin);

    shiftLiveSessions($this->service, 6);

    $large = shiftPageQueries(ShiftSessionsPage::class, $this->admin);

    // Tujuh sesi live tidak boleh menambah query dibanding satu sesi live.
    expect($large)->toBe($small);
});

/**
 * Sesi tertutup dengan sejumlah gerakan kas: jalur modal gerakan kas. Sesi
 * harus tertutup supaya view memanggil isReversed() untuk tiap baris.
 */
function shiftSessionWithMovements(ShiftSessionService $service, int $count): ShiftSession
{
    $cashier = User::factory()->create();
    $session = $service->open($cashier, null, 100000);

    foreach (range(1, $count) as $i) {
        $service->recordMovement($session, 'in', 5000 * $i, null, null, $cashier);
    }

    return $service->close($session, 100000, $cashier);
}

/**
 * Jumlah query satu render halaman dengan modal gerakan kas terbuka. Satu
 * pembukaan pemanasan dimuat lebih dulu supaya query cache peran/izin tidak
 * ikut terhitung.
 */
function shiftMovementModalQueries(ShiftSession $session, User $user): int
{
    $open = fn () => Livewire::actingAs($user)->test(ShiftSessionsPage::class)
        ->call('openMovementModal', $session->id)
        ->assertOk();

    $open();

    return shiftQueryCount($open);
}

test('cash movement modal query count stays flat as movements grow', function () {
    $single = shiftSessionWithMovements($this->service, 1);
    $many = shiftSessionWithMovements($this->service, 6);

    $small = shiftMovementModalQueries($single, $this->admin);
    $large = shiftMovementModalQueries($many, $this->admin);

    // Enam gerakan kas tidak boleh menambah query dibanding satu gerakan:
    // relasi modal di-eager-load, jadi isReversed() per baris tidak query.
    expect($large)->toBe($small);
});

test('cash movement modal eager loads reversal but not the unused creator relation', function () {
    $session = shiftSessionWithMovements($this->service, 1);

    $component = Livewire::actingAs($this->admin)->test(ShiftSessionsPage::class)
        ->call('openMovementModal', $session->id)
        ->assertOk();

    $movement = $component->viewData('movementMovements')->first();

    // reversal dipakai isReversed(); createdBy tidak dibaca view mana pun.
    expect($movement->relationLoaded('reversal'))->toBeTrue()
        ->and($movement->relationLoaded('createdBy'))->toBeFalse();
});

test('shift report query count stays flat as live sessions grow', function () {
    shiftReconciliationScenario($this->service);
    shiftLiveSessions($this->service, 1);

    $small = shiftPageQueries(ShiftReportPage::class, $this->admin);

    shiftLiveSessions($this->service, 6);

    $large = shiftPageQueries(ShiftReportPage::class, $this->admin);

    // Relasi kasir/shift di-eager-load, jadi jumlah baris tidak menambah query.
    expect($large)->toBe($small);
});
