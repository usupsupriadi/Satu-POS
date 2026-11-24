<?php

use App\Livewire\Pos\PosPage;
use App\Livewire\Vouchers\VoucherCampaignsPage;
use App\Livewire\Vouchers\VoucherCodesPage;
use App\Livewire\Vouchers\VoucherRedemptionsPage;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VoucherCampaign;
use App\Models\VoucherRedemption;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->viewer = User::factory()->create();
    $this->viewer->givePermissionTo('vouchers.view');
});

function makeVoucherReportOrder(string $paymentStatus, int $voucherDiscount, ?string $paidAt, string $code): Transaction
{
    return Transaction::query()->create([
        'code' => $code,
        'member_id' => null,
        'channel' => 'self_order',
        'name' => 'Guest',
        'phone' => '628123',
        'email' => null,
        'order_type' => 'take_away',
        'dining_table_id' => null,
        'voucher_campaign_id' => 1,
        'voucher_code_id' => 1,
        'voucher_code' => 'AKHIR10',
        'subtotal' => 10000,
        'voucher_discount_amount' => $voucherDiscount,
        'manual_discount_amount' => 0,
        'discount_total_amount' => $voucherDiscount,
        'tax_percentage' => 0,
        'tax_amount' => 0,
        'payment_fee_amount' => 0,
        'rounding_amount' => 0,
        'total' => 10000 - $voucherDiscount,
        'checkout_link' => '-',
        'payment_method' => 'qris_midtrans',
        'payment_status' => $paymentStatus,
        'order_status' => 'new',
        'paid_at' => $paidAt,
        'external_id' => (string) Str::uuid(),
        'self_order_token' => Str::random(40),
    ]);
}

function seedVoucherReportFixtures(): array
{
    DB::table('voucher_campaigns')->insert([
        'id' => 1,
        'name' => 'Promo Akhir Tahun',
        'discount_type' => 'fixed_amount',
        'discount_value' => 1000,
        'is_active' => true,
        'is_member_only' => false,
        'usage_limit_total' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('voucher_codes')->insert([
        'id' => 1,
        'voucher_campaign_id' => 1,
        'code' => 'AKHIR10',
        'is_active' => true,
        'times_redeemed' => 2,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Satu order lunas dan satu reservasi order pending (seperti QRIS yang
    // belum dibayar): keduanya punya baris redemption.
    $paid = makeVoucherReportOrder('paid', 1000, now()->toDateTimeString(), 'TRX-VOUCHER-PAID');
    $pending = makeVoucherReportOrder('pending', 500, null, 'TRX-VOUCHER-PENDING');

    DB::table('voucher_redemptions')->insert([
        [
            'voucher_campaign_id' => 1,
            'voucher_code_id' => 1,
            'transaction_id' => $paid->id,
            'member_id' => null,
            'guest_identifier' => '628123',
            'discount_amount' => 1000,
            'snapshot' => null,
            'redeemed_at' => now(),
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'voucher_campaign_id' => 1,
            'voucher_code_id' => 1,
            'transaction_id' => $pending->id,
            'member_id' => null,
            'guest_identifier' => '628123',
            'discount_amount' => 500,
            'snapshot' => json_encode(['reserved_before_payment' => true]),
            'redeemed_at' => now(),
            'paid_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    return [$paid, $pending];
}

test('voucher usage history only counts paid redemptions', function () {
    [$paid, $pending] = seedVoucherReportFixtures();

    Livewire::actingAs($this->viewer)
        ->test(VoucherRedemptionsPage::class)
        ->assertViewHas('summaryCount', fn ($value) => (int) $value === 1)
        ->assertViewHas('summaryDiscount', fn ($value) => (int) $value === 1000)
        ->assertViewHas('rows', function ($rows) use ($paid, $pending) {
            return $rows->total() === 1
                && (int) $rows->first()->transaction_id === (int) $paid->id
                && (int) $rows->first()->transaction_id !== (int) $pending->id;
        });
});

test('voucher code page counts only paid redemptions but keeps quota consumption', function () {
    seedVoucherReportFixtures();

    $campaign = VoucherCampaign::query()->findOrFail(1);

    Livewire::actingAs($this->viewer)
        ->test(VoucherCodesPage::class, ['campaign' => $campaign])
        ->assertViewHas('codes', function ($codes) {
            $row = $codes->first();

            return (int) ($row->paid_redemptions_count ?? -1) === 1
                && (int) $row->times_redeemed === 2;
        });
});

test('voucher campaign page reports paid usage and discount only', function () {
    seedVoucherReportFixtures();

    Livewire::actingAs($this->viewer)
        ->test(VoucherCampaignsPage::class)
        ->assertViewHas('campaigns', function ($campaigns) {
            $campaign = $campaigns->first();

            return (int) $campaign->paid_redemptions_count === 1
                && (int) $campaign->paid_discount_sum === 1000
                && (int) $campaign->codes_sum_times_redeemed === 2;
        });
});

test('midtrans settlement turns a reserved redemption into a counted one', function () {
    config(['midtrans.server_key' => 'test-server-key']);

    $paid = seedVoucherReportFixtures()[0];
    $pending = Transaction::query()->where('code', 'TRX-VOUCHER-PENDING')->firstOrFail();

    $statusCode = '200';
    $grossAmount = (string) number_format((float) $pending->total, 2, '.', '');
    $signature = hash('sha512', $pending->external_id.$statusCode.$grossAmount.'test-server-key');

    $this->postJson(route('self-order.payment.webhook'), [
        'order_id' => $pending->external_id,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'signature_key' => $signature,
        'transaction_status' => 'settlement',
    ])->assertOk();

    $redemption = VoucherRedemption::query()->where('transaction_id', $pending->id)->first();

    expect($pending->fresh()->payment_status)->toBe('paid')
        ->and($redemption)->not->toBeNull()
        ->and($redemption->paid_at)->not->toBeNull();

    Livewire::actingAs($this->viewer)
        ->test(VoucherRedemptionsPage::class)
        ->assertViewHas('summaryCount', fn ($value) => (int) $value === 2);

    $campaign = VoucherCampaign::query()->findOrFail(1);

    Livewire::actingAs($this->viewer)
        ->test(VoucherCodesPage::class, ['campaign' => $campaign])
        ->assertViewHas('codes', fn ($codes) => (int) ($codes->first()->paid_redemptions_count ?? -1) === 2);

    expect($paid->fresh()->payment_status)->toBe('paid');
});

test('voucher redeemed through POS checkout is counted immediately', function () {
    $cashier = User::factory()->create();
    $cashier->givePermissionTo('pos.access');

    $category = Category::query()->create(['name' => 'Food']);
    $product = Product::query()->create([
        'name' => 'Nasi Goreng',
        'description' => 'Test',
        'image' => 'test.png',
        'is_available' => true,
        'category_id' => $category->id,
    ]);
    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'name' => 'Normal',
        'price' => 50000,
        'hpp' => 0,
    ]);

    $campaign = VoucherCampaign::query()->create([
        'name' => 'Diskon 10%',
        'description' => null,
        'discount_type' => 'percent',
        'discount_value' => 10,
        'max_discount_amount' => null,
        'min_eligible_subtotal' => null,
        'is_active' => true,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'usage_limit_total' => null,
        'usage_limit_per_user' => null,
        'is_member_only' => false,
        'meta' => null,
        'terms' => null,
        'created_by_user_id' => null,
    ]);

    $voucherCode = $campaign->codes()->create([
        'code' => 'POS10',
        'is_active' => true,
        'usage_limit_total' => null,
        'usage_limit_per_user' => null,
        'times_redeemed' => 0,
    ]);

    Livewire::actingAs($cashier)
        ->test(PosPage::class)
        ->call('addVariantToCart', $variant->id)
        ->set('voucherCodeInput', 'POS10')
        ->set('customerName', 'Guest')
        ->set('customerPhone', '08123456789')
        ->set('paymentMethod', 'cash')
        ->set('cashReceived', 100000)
        ->call('checkout')
        ->assertHasNoErrors();

    $trx = Transaction::query()->latest('id')->firstOrFail();
    $redemption = VoucherRedemption::query()->where('transaction_id', $trx->id)->first();

    expect($trx->payment_status)->toBe('paid')
        ->and($redemption)->not->toBeNull()
        ->and((int) $redemption->voucher_code_id)->toBe((int) $voucherCode->id)
        ->and($redemption->paid_at)->not->toBeNull();

    Livewire::actingAs($this->viewer)
        ->test(VoucherRedemptionsPage::class)
        ->assertViewHas('summaryCount', fn ($value) => (int) $value === 1);
});
