<?php

use App\Models\Member;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createMidtransPendingOrder(array $overrides = []): array
{
    $orderId = (string) Str::uuid();

    $trx = Transaction::query()->create(array_merge([
        'code' => 'TRX-'.Str::upper(Str::random(6)),
        'member_id' => null,
        'channel' => 'self_order',
        'name' => 'Guest',
        'phone' => '628123',
        'email' => null,
        'order_type' => 'take_away',
        'dining_table_id' => null,
        'subtotal' => 10000,
        'voucher_discount_amount' => 0,
        'manual_discount_amount' => 0,
        'discount_total_amount' => 0,
        'tax_percentage' => 0,
        'tax_amount' => 0,
        'payment_fee_amount' => 0,
        'rounding_amount' => 0,
        'total' => 10000,
        'checkout_link' => 'https://example.test/pay',
        'payment_method' => 'qris_midtrans',
        'payment_status' => 'pending',
        'order_status' => 'new',
        'external_id' => $orderId,
        'self_order_token' => Str::random(40),
    ], $overrides));

    return [$trx, $orderId];
}

function postMidtransWebhook(object $testCase, string $orderId, string $status): void
{
    $statusCode = '200';
    $grossAmount = '10000.00';
    $signature = hash('sha512', $orderId.$statusCode.$grossAmount.'test-server-key');

    $testCase->postJson(route('self-order.payment.webhook'), [
        'order_id' => $orderId,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'signature_key' => $signature,
        'transaction_status' => $status,
    ])->assertOk();
}

beforeEach(function () {
    config(['midtrans.server_key' => 'test-server-key']);
});

test('a settled transaction is never downgraded to pending by unknown midtrans statuses', function () {
    [$trx, $orderId] = createMidtransPendingOrder();

    postMidtransWebhook($this, $orderId, 'settlement');
    expect((string) $trx->fresh()->payment_status)->toBe('paid');

    postMidtransWebhook($this, $orderId, 'chargeback');
    expect((string) $trx->fresh()->payment_status)->toBe('paid');

    postMidtransWebhook($this, $orderId, 'pending');
    expect((string) $trx->fresh()->payment_status)->toBe('paid')
        ->and($trx->fresh()->paid_at)->not->toBeNull();
});

test('an expired online order returns the redeemed points to the member', function () {
    $member = Member::query()->create([
        'name' => 'Member A',
        'email' => 'member@example.com',
        'phone' => '628111',
        'points' => 30,
        'email_verified_at' => now(),
        'verification_token' => null,
    ]);

    [$trx, $orderId] = createMidtransPendingOrder([
        'member_id' => $member->id,
        'points_redeemed' => 20,
        'point_discount_amount' => 20,
        'discount_total_amount' => 20,
        'total' => 9980,
    ]);

    postMidtransWebhook($this, $orderId, 'expire');

    $trx->refresh();

    expect((string) $trx->payment_status)->toBe('expired')
        ->and($trx->points_refunded_at)->not->toBeNull()
        ->and((int) $member->fresh()->points)->toBe(50);
});

test('a cancelled online order releases the reserved voucher quota', function () {
    \Illuminate\Support\Facades\DB::table('voucher_campaigns')->insert([
        'id' => 1,
        'name' => 'Promo',
        'discount_type' => 'fixed_amount',
        'discount_value' => 1000,
        'is_active' => true,
        'is_member_only' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    \Illuminate\Support\Facades\DB::table('voucher_codes')->insert([
        'id' => 1,
        'voucher_campaign_id' => 1,
        'code' => 'ONCE10',
        'is_active' => true,
        'times_redeemed' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    [$trx, $orderId] = createMidtransPendingOrder([
        'voucher_campaign_id' => 1,
        'voucher_code_id' => 1,
        'voucher_code' => 'ONCE10',
        'voucher_discount_amount' => 1000,
        'discount_total_amount' => 1000,
        'total' => 9000,
    ]);

    \Illuminate\Support\Facades\DB::table('voucher_redemptions')->insert([
        'voucher_campaign_id' => 1,
        'voucher_code_id' => 1,
        'transaction_id' => $trx->id,
        'member_id' => null,
        'guest_identifier' => '628123',
        'discount_amount' => 1000,
        'snapshot' => null,
        'redeemed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    postMidtransWebhook($this, $orderId, 'cancel');

    $trx->refresh();

    expect((string) $trx->payment_status)->toBe('failed')
        ->and(\App\Models\VoucherRedemption::query()->where('transaction_id', $trx->id)->exists())->toBeFalse()
        ->and((int) \App\Models\VoucherCode::query()->whereKey(1)->value('times_redeemed'))->toBe(0);
});
