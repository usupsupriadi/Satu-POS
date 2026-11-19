<?php

use App\Livewire\Transaction\TransactionShowPage;
use App\Models\Member;
use App\Models\Transaction;
use App\Models\TransactionEvent;
use App\Models\User;
use App\Models\VoucherCampaign;
use App\Models\VoucherCode;
use App\Models\VoucherRedemption;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeVoidActor(): User
{
    $role = Role::firstOrCreate(['name' => 'void_actor_test']);
    $role->givePermissionTo(['pos.access', 'transactions.void', 'transactions.details']);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function makePendingSelfOrderWithBenefits(): array
{
    $member = Member::query()->create([
        'name' => 'Member A',
        'email' => 'void-member@example.com',
        'phone' => '628111',
        'points' => 0,
        'email_verified_at' => now(),
        'verification_token' => null,
    ]);

    $campaign = VoucherCampaign::query()->create([
        'name' => 'Voucher Void',
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
        'code' => 'VOID10',
        'is_active' => true,
        'usage_limit_total' => 10,
        'usage_limit_per_user' => null,
        'times_redeemed' => 1,
    ]);

    $transaction = Transaction::query()->create([
        'code' => 'TRX-SO-VOID-1',
        'external_id' => 'EXT-SO-VOID-1',
        'member_id' => $member->id,
        'channel' => 'self_order',
        'name' => 'Member A',
        'phone' => '628111',
        'email' => 'void-member@example.com',
        'order_type' => 'dine_in',
        'subtotal' => 10000,
        'voucher_campaign_id' => $campaign->id,
        'voucher_code_id' => $voucherCode->id,
        'voucher_code' => 'VOID10',
        'voucher_discount_amount' => 1000,
        'points_redeemed' => 10,
        'point_discount_amount' => 1000,
        'discount_total_amount' => 2000,
        'tax_percentage' => 0,
        'tax_amount' => 0,
        'rounding_amount' => 0,
        'refunded_amount' => 0,
        'total' => 8000,
        'checkout_link' => '-',
        'payment_method' => 'cash',
        'payment_status' => 'pending',
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
        'redeemed_at' => now(),
    ]);

    return [$transaction, $member, $voucherCode];
}

it('returns points and voucher quota when a pending self-order is voided', function () {
    $this->seed(RolePermissionSeeder::class);

    [$transaction, $member, $voucherCode] = makePendingSelfOrderWithBenefits();

    Livewire::actingAs(makeVoidActor())
        ->test(TransactionShowPage::class, ['transaction' => $transaction])
        ->set('correctionReason', 'Pelanggan membatalkan pesanan')
        ->call('voidTransaction')
        ->assertHasNoErrors();

    $transaction->refresh();

    expect((string) $transaction->payment_status)->toBe('voided');
    expect($transaction->points_refunded_at)->not->toBeNull();
    expect($transaction->net_points_redeemed)->toBe(0);
    expect((int) $member->fresh()->points)->toBe(10);

    expect(VoucherRedemption::query()->where('transaction_id', $transaction->id)->exists())->toBeFalse();
    expect((int) $voucherCode->fresh()->times_redeemed)->toBe(0);

    $event = TransactionEvent::query()
        ->where('transaction_id', $transaction->id)
        ->where('action', 'order_benefits_released')
        ->latest()
        ->first();

    expect($event)->not->toBeNull();
    expect((int) ($event->meta['points_refunded'] ?? 0))->toBe(10);
    expect((bool) ($event->meta['voucher_released'] ?? false))->toBeTrue();
});

it('does not refund points twice when the same transaction is voided again', function () {
    $this->seed(RolePermissionSeeder::class);

    [$transaction, $member] = makePendingSelfOrderWithBenefits();

    $component = Livewire::actingAs(makeVoidActor())
        ->test(TransactionShowPage::class, ['transaction' => $transaction])
        ->set('correctionReason', 'Pesanan dibatalkan')
        ->call('voidTransaction')
        ->assertHasNoErrors();

    $component
        ->set('correctionReason', 'Coba void lagi')
        ->call('voidTransaction')
        ->assertHasErrors(['correctionReason']);

    expect((int) $member->fresh()->points)->toBe(10);
    expect($transaction->fresh()->points_refunded_at)->not->toBeNull();
});
