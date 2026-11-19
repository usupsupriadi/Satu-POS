<?php

use App\Livewire\Transaction\TransactionShowPage;
use App\Models\Member;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\TransactionEvent;
use App\Models\User;
use App\Models\VoucherCampaign;
use App\Models\VoucherCode;
use App\Models\VoucherRedemption;
use App\Services\Transactions\ReleaseOrderBenefits;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeRefundActor(): User
{
    $role = Role::firstOrCreate(['name' => 'refund_actor_test']);
    $role->givePermissionTo(['transactions.refund', 'transactions.details']);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function allowQuickRefund(): void
{
    Setting::current()->update([
        'corrections_refund_quick_max_count_per_day' => 10,
        'corrections_refund_quick_max_amount' => 1000000,
        'corrections_refund_requires_approval_for_cash' => false,
    ]);
}

function makePaidSelfOrderWithBenefits(string $channel = 'self_order'): array
{
    $member = Member::query()->create([
        'name' => 'Member Refund',
        'email' => 'refund-member@example.com',
        'phone' => '628112',
        // Poin yang didapat dari transaksi ini sudah masuk ke saldo member.
        'points' => 8,
        'email_verified_at' => now(),
        'verification_token' => null,
    ]);

    $campaign = VoucherCampaign::query()->create([
        'name' => 'Voucher Refund',
        'description' => null,
        'discount_type' => 'fixed_amount',
        'discount_value' => 1000,
        'max_discount_amount' => null,
        'min_eligible_subtotal' => null,
        'is_active' => true,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'usage_limit_total' => 10,
        'usage_limit_per_user' => null,
        'eligible_member_type' => null,
        'meta' => null,
        'terms' => null,
        'created_by_user_id' => null,
    ]);

    $voucherCode = VoucherCode::query()->create([
        'voucher_campaign_id' => $campaign->id,
        'code' => 'REFUND10',
        'is_active' => true,
        'usage_limit_total' => 10,
        'usage_limit_per_user' => null,
        'times_redeemed' => 1,
    ]);

    $transaction = Transaction::query()->create([
        'code' => 'TRX-SO-REFUND-1',
        'external_id' => 'EXT-SO-REFUND-1',
        'member_id' => $member->id,
        'channel' => $channel,
        'name' => 'Member Refund',
        'phone' => '628112',
        'email' => 'refund-member@example.com',
        'order_type' => 'dine_in',
        'subtotal' => 10000,
        'voucher_campaign_id' => $campaign->id,
        'voucher_code_id' => $voucherCode->id,
        'voucher_code' => 'REFUND10',
        'voucher_discount_amount' => 1000,
        'points_redeemed' => 10,
        'points_earned' => 8,
        'point_discount_amount' => 1000,
        'discount_total_amount' => 2000,
        'tax_percentage' => 0,
        'tax_amount' => 0,
        'rounding_amount' => 0,
        'refunded_amount' => 0,
        'total' => 8000,
        'checkout_link' => '-',
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'paid_at' => now()->subHour(),
        'is_midtrans_processed' => false,
    ]);

    VoucherRedemption::query()->create([
        'voucher_campaign_id' => $campaign->id,
        'voucher_code_id' => $voucherCode->id,
        'transaction_id' => $transaction->id,
        'member_id' => $member->id,
        'guest_identifier' => null,
        'discount_amount' => 1000,
        'snapshot' => null,
        'redeemed_at' => now()->subHour(),
    ]);

    return [$transaction, $member, $voucherCode];
}

it('returns points and voucher quota on a full refund of a paid self-order', function () {
    $this->seed(RolePermissionSeeder::class);
    allowQuickRefund();

    [$transaction, $member, $voucherCode] = makePaidSelfOrderWithBenefits();

    Livewire::actingAs(makeRefundActor())
        ->test(TransactionShowPage::class, ['transaction' => $transaction])
        ->set('correctionReason', 'Pelanggan komplain, uang dikembalikan')
        ->set('refundAmount', 8000)
        ->call('refundTransaction')
        ->assertHasNoErrors();

    $transaction->refresh();

    expect((string) $transaction->payment_status)->toBe('refunded');
    expect($transaction->points_refunded_at)->not->toBeNull();
    expect($transaction->points_earned_reversed_at)->not->toBeNull();
    expect($transaction->net_points_redeemed)->toBe(0);
    // 8 poin earned ditarik kembali, 10 poin terpakai dikembalikan.
    expect((int) $member->fresh()->points)->toBe(10);

    expect(VoucherRedemption::query()->where('transaction_id', $transaction->id)->exists())->toBeFalse();
    expect((int) $voucherCode->fresh()->times_redeemed)->toBe(0);

    $event = TransactionEvent::query()
        ->where('transaction_id', $transaction->id)
        ->where('action', 'order_benefits_released')
        ->latest()
        ->first();

    expect($event)->not->toBeNull();
    expect((string) ($event->meta['source'] ?? ''))->toBe('refund');
    expect((int) ($event->meta['points_refunded'] ?? 0))->toBe(10);
    expect((int) ($event->meta['points_earned_reversed'] ?? 0))->toBe(8);
    expect((int) ($event->meta['points_earned_shortfall'] ?? -1))->toBe(0);
});

it('keeps points and voucher on a partial refund', function () {
    $this->seed(RolePermissionSeeder::class);
    allowQuickRefund();

    [$transaction, $member, $voucherCode] = makePaidSelfOrderWithBenefits();

    Livewire::actingAs(makeRefundActor())
        ->test(TransactionShowPage::class, ['transaction' => $transaction])
        ->set('correctionReason', 'Refund sebagian')
        ->set('refundAmount', 3000)
        ->call('refundTransaction')
        ->assertHasNoErrors();

    $transaction->refresh();

    expect((string) $transaction->payment_status)->toBe('partial_refund');
    expect($transaction->points_refunded_at)->toBeNull();
    expect((int) $member->fresh()->points)->toBe(8);
    expect(VoucherRedemption::query()->where('transaction_id', $transaction->id)->exists())->toBeTrue();
    expect((int) $voucherCode->fresh()->times_redeemed)->toBe(1);
});

it('releases benefits when the remaining amount is refunded later', function () {
    $this->seed(RolePermissionSeeder::class);
    allowQuickRefund();

    [$transaction, $member] = makePaidSelfOrderWithBenefits();

    $component = Livewire::actingAs(makeRefundActor())
        ->test(TransactionShowPage::class, ['transaction' => $transaction])
        ->set('correctionReason', 'Refund sebagian')
        ->set('refundAmount', 3000)
        ->call('refundTransaction')
        ->assertHasNoErrors();

    $component
        ->set('correctionReason', 'Sisa refund')
        ->set('refundAmount', 5000)
        ->call('refundTransaction')
        ->assertHasNoErrors();

    $transaction->refresh();

    expect((string) $transaction->payment_status)->toBe('refunded');
    expect($transaction->points_refunded_at)->not->toBeNull();
    expect((int) $member->fresh()->points)->toBe(10);
});

it('records unreversed earned points in the event when the member balance is insufficient', function () {
    $this->seed(RolePermissionSeeder::class);
    allowQuickRefund();

    [$transaction, $member] = makePaidSelfOrderWithBenefits();
    $transaction->update(['points_redeemed' => 0, 'points_earned' => 8]);
    VoucherRedemption::query()->where('transaction_id', $transaction->id)->delete();
    $member->update(['points' => 2]);

    Livewire::actingAs(makeRefundActor())
        ->test(TransactionShowPage::class, ['transaction' => $transaction])
        ->set('correctionReason', 'Refund penuh')
        ->set('refundAmount', 8000)
        ->call('refundTransaction')
        ->assertHasNoErrors();

    $transaction->refresh();

    expect((string) $transaction->payment_status)->toBe('refunded');
    expect($transaction->points_earned_reversed_at)->not->toBeNull();
    expect((int) $member->fresh()->points)->toBe(0);

    $event = TransactionEvent::query()
        ->where('transaction_id', $transaction->id)
        ->where('action', 'order_benefits_released')
        ->latest()
        ->first();

    expect($event)->not->toBeNull();
    expect((int) ($event->meta['points_earned_reversed'] ?? 0))->toBe(2);
    expect((int) ($event->meta['points_earned_shortfall'] ?? 0))->toBe(6);
});

it('returns points and voucher quota on a full refund of a paid cashier order', function () {
    $this->seed(RolePermissionSeeder::class);
    allowQuickRefund();

    [$transaction, $member, $voucherCode] = makePaidSelfOrderWithBenefits('pos');

    Livewire::actingAs(makeRefundActor())
        ->test(TransactionShowPage::class, ['transaction' => $transaction])
        ->call('openRefundModal')
        ->assertSee('Refund penuh mengembalikan poin & melepas kuota voucher')
        ->set('correctionReason', 'Salah input transaksi kasir')
        ->set('refundAmount', 8000)
        ->call('refundTransaction')
        ->assertHasNoErrors();

    $transaction->refresh();

    expect((string) $transaction->payment_status)->toBe('refunded');
    expect($transaction->points_refunded_at)->not->toBeNull();
    expect($transaction->points_earned_reversed_at)->not->toBeNull();
    // 8 poin earned ditarik kembali, 10 poin terpakai dikembalikan.
    expect((int) $member->fresh()->points)->toBe(10);

    expect(VoucherRedemption::query()->where('transaction_id', $transaction->id)->exists())->toBeFalse();
    expect((int) $voucherCode->fresh()->times_redeemed)->toBe(0);

    $event = TransactionEvent::query()
        ->where('transaction_id', $transaction->id)
        ->where('action', 'order_benefits_released')
        ->latest()
        ->first();

    expect($event)->not->toBeNull();
    expect((string) ($event->meta['source'] ?? ''))->toBe('refund');
    expect((int) ($event->meta['points_refunded'] ?? 0))->toBe(10);
    expect((int) ($event->meta['points_earned_reversed'] ?? 0))->toBe(8);
    expect((int) ($event->meta['points_earned_shortfall'] ?? -1))->toBe(0);
});

it('keeps points and voucher on a partial refund of a paid cashier order', function () {
    $this->seed(RolePermissionSeeder::class);
    allowQuickRefund();

    [$transaction, $member, $voucherCode] = makePaidSelfOrderWithBenefits('pos');

    Livewire::actingAs(makeRefundActor())
        ->test(TransactionShowPage::class, ['transaction' => $transaction])
        ->set('correctionReason', 'Refund sebagian kasir')
        ->set('refundAmount', 3000)
        ->call('refundTransaction')
        ->assertHasNoErrors();

    $transaction->refresh();

    expect((string) $transaction->payment_status)->toBe('partial_refund');
    expect($transaction->points_refunded_at)->toBeNull();
    expect($transaction->points_earned_reversed_at)->toBeNull();
    expect((int) $member->fresh()->points)->toBe(8);
    expect(VoucherRedemption::query()->where('transaction_id', $transaction->id)->exists())->toBeTrue();
    expect((int) $voucherCode->fresh()->times_redeemed)->toBe(1);
});

it('reverses earned points only once even when release runs twice', function () {
    [$transaction, $member] = makePaidSelfOrderWithBenefits();

    $benefits = app(ReleaseOrderBenefits::class);

    DB::transaction(fn () => $benefits->release($transaction->fresh(), reverseEarnedPoints: true));
    DB::transaction(fn () => $benefits->release($transaction->fresh(), reverseEarnedPoints: true));

    expect($transaction->fresh()->points_earned_reversed_at)->not->toBeNull();
    // 8 poin earned hanya ditarik sekali: 8 + 10 - 8.
    expect((int) $member->fresh()->points)->toBe(10);
});
