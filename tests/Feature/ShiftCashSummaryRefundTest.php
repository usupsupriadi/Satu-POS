<?php

use App\Models\ShiftSession;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Shifts\ShiftSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(ShiftSessionService::class);
});

function shiftRefundSession(int $openingCash = 100000): ShiftSession
{
    return app(ShiftSessionService::class)->open(User::factory()->create(), null, $openingCash);
}

function shiftRefundSale(ShiftSession $session, int $total, string $paymentMethod = 'cash'): Transaction
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

test('full cash refund reduces expected cash by the refunded amount', function () {
    $session = shiftRefundSession(100000);
    $sale = shiftRefundSale($session, 50000);

    $sale->forceFill([
        'payment_status' => 'refunded',
        'refunded_amount' => 50000,
        'refunded_at' => now(),
    ])->save();

    $summary = $this->service->cashSummary($session->fresh());

    expect($summary['transaction_count'])->toBe(1)
        ->and($summary['total_sales'])->toBe(50000)
        ->and($summary['cash_sales'])->toBe(50000)
        ->and($summary['cash_refunds'])->toBe(50000)
        ->and($summary['expected_cash'])->toBe(100000);

    $closed = $this->service->close($session->fresh(), 100000, $session->user);

    expect((int) $closed->expected_cash)->toBe(100000)
        ->and((int) $closed->variance)->toBe(0);
});

test('partial cash refund subtracts only the refunded amount', function () {
    $session = shiftRefundSession(100000);
    $sale = shiftRefundSale($session, 100000);

    $sale->forceFill([
        'payment_status' => 'partial_refund',
        'refunded_amount' => 30000,
        'refunded_at' => now(),
    ])->save();

    $summary = $this->service->cashSummary($session->fresh());

    expect($summary['transaction_count'])->toBe(1)
        ->and($summary['total_sales'])->toBe(100000)
        ->and($summary['cash_sales'])->toBe(100000)
        ->and($summary['cash_refunds'])->toBe(30000)
        ->and($summary['expected_cash'])->toBe(170000);
});

test('non-cash refund leaves expected cash untouched', function () {
    $session = shiftRefundSession(100000);
    $sale = shiftRefundSale($session, 50000, 'qris');

    $sale->forceFill([
        'payment_status' => 'refunded',
        'refunded_amount' => 50000,
        'refunded_at' => now(),
    ])->save();

    $summary = $this->service->cashSummary($session->fresh());

    expect($summary['transaction_count'])->toBe(1)
        ->and($summary['cash_sales'])->toBe(0)
        ->and($summary['cash_refunds'])->toBe(0)
        ->and($summary['expected_cash'])->toBe(100000);
});
