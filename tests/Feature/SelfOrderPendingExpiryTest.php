<?php

use App\Livewire\Pos\PosPage;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Member;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function makeSelfOrderCashTransaction(array $overrides = []): Transaction
{
    $table = DiningTable::query()->firstOrCreate(
        ['table_number' => 'A1'],
        ['image' => null, 'qr_value' => 'TBL-A1'],
    );

    $transaction = Transaction::query()->create(array_merge([
        'code' => 'TRX'.strtoupper(substr(md5((string) mt_rand()), 0, 6)),
        'channel' => 'self_order',
        'name' => 'Tamu',
        'phone' => '628111',
        'order_type' => 'dine_in',
        'dining_table_id' => $table->id,
        'subtotal' => 10000,
        'tax_percentage' => 0,
        'tax_amount' => 0,
        'rounding_amount' => 0,
        'refunded_amount' => 0,
        'total' => 10000,
        'checkout_link' => '-',
        'payment_method' => 'cash',
        'payment_status' => 'pending',
        'external_id' => 'ext-'.uniqid(),
        'is_midtrans_processed' => false,
    ], $overrides));

    if (isset($overrides['_created_minutes_ago'])) {
        DB::table('transactions')->where('id', $transaction->id)->update([
            'created_at' => now()->subMinutes((int) $overrides['_created_minutes_ago']),
        ]);
        $transaction->refresh();
    }

    return $transaction;
}

it('expires self-order cash pending transactions left untouched by the cashier', function () {
    $old = makeSelfOrderCashTransaction(['_created_minutes_ago' => 30]);
    $fresh = makeSelfOrderCashTransaction();

    $this->artisan('self-order:expire-pending')->assertExitCode(0);

    expect($old->fresh()->payment_status)->toBe('expired');
    expect($old->fresh()->order_status)->toBe('cancelled');
    expect($fresh->fresh()->payment_status)->toBe('pending');
});

it('does not expire a transaction that was already paid or voided', function () {
    $paid = makeSelfOrderCashTransaction([
        '_created_minutes_ago' => 60,
        'payment_status' => 'paid',
        'paid_at' => now()->subMinutes(40),
    ]);

    $this->artisan('self-order:expire-pending')->assertExitCode(0);

    expect($paid->fresh()->payment_status)->toBe('paid');
});

it('refuses to pay an expired transaction from a stale cashier screen', function () {
    $this->seed(RolePermissionSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('cashier');
    $this->actingAs($user);

    $expired = makeSelfOrderCashTransaction(['payment_status' => 'expired']);

    Livewire::test(PosPage::class)
        ->call('loadPending', $expired->id)
        ->assertSet('editingTransactionId', null);
});

it('keeps unpaid online transactions untouched', function () {
    $online = makeSelfOrderCashTransaction([
        '_created_minutes_ago' => 60,
        'payment_method' => 'qris_midtrans',
        'payment_status' => 'pending',
    ]);

    $this->artisan('self-order:expire-pending')->assertExitCode(0);

    expect($online->fresh()->payment_status)->toBe('pending');
});

it('returns redeemed points to the member when the order expires', function () {
    $member = Member::query()->create([
        'name' => 'Member A',
        'email' => 'member@example.com',
        'phone' => '628111',
        'points' => 0,
        'email_verified_at' => now(),
        'verification_token' => null,
    ]);

    $transaction = makeSelfOrderCashTransaction([
        '_created_minutes_ago' => 60,
        'member_id' => $member->id,
        'points_redeemed' => 25,
        'point_discount_amount' => 2500,
        'total' => 7500,
    ]);

    $this->artisan('self-order:expire-pending')->assertExitCode(0);

    $transaction->refresh();

    expect($transaction->payment_status)->toBe('expired');
    expect($transaction->points_refunded_at)->not()->toBeNull();
    expect($transaction->net_points_redeemed)->toBe(0);
    expect((int) $member->fresh()->points)->toBe(25);

    // Menjalankan command lagi tidak boleh mengembalikan poin dua kali.
    $this->artisan('self-order:expire-pending')->assertExitCode(0);

    expect((int) $member->fresh()->points)->toBe(25);
    expect($transaction->fresh()->points_refunded_at)->not()->toBeNull();
});

it('marks refunded points so reports can exclude them', function () {
    $member = Member::query()->create([
        'name' => 'Member A',
        'email' => 'member2@example.com',
        'phone' => '628222',
        'points' => 0,
        'email_verified_at' => now(),
        'verification_token' => null,
    ]);

    $refunded = makeSelfOrderCashTransaction([
        '_created_minutes_ago' => 60,
        'member_id' => $member->id,
        'points_redeemed' => 10,
        'point_discount_amount' => 1000,
        'total' => 9000,
    ]);

    $paid = makeSelfOrderCashTransaction([
        '_created_minutes_ago' => 60,
        'member_id' => $member->id,
        'points_redeemed' => 7,
        'point_discount_amount' => 700,
        'total' => 9300,
        'payment_status' => 'paid',
        'paid_at' => now()->subMinutes(30),
    ]);

    $this->artisan('self-order:expire-pending')->assertExitCode(0);

    expect($refunded->fresh()->net_points_redeemed)->toBe(0);
    expect($paid->fresh()->net_points_redeemed)->toBe(7);

    $totalActivePoints = Transaction::query()
        ->whereNull('points_refunded_at')
        ->sum('points_redeemed');

    expect((int) $totalActivePoints)->toBe(7);
});

it('uses the configured expiry window', function () {
    config(['self_order.pending_expiry_minutes' => 5]);

    $tenMinutesOld = makeSelfOrderCashTransaction(['_created_minutes_ago' => 10]);

    $this->artisan('self-order:expire-pending')->assertExitCode(0);

    expect($tenMinutesOld->fresh()->payment_status)->toBe('expired');
});

it('refuses to let the cashier pay a transaction that expired while it was open', function () {
    $this->seed(RolePermissionSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('cashier');
    $this->actingAs($user);

    $category = Category::query()->create(['name' => 'Makanan']);

    $product = Product::query()->create([
        'name' => 'Nasi Goreng',
        'description' => '-',
        'image' => '-',
        'category_id' => $category->id,
        'printer_source_id' => null,
    ]);

    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'name' => 'Regular',
        'price' => 10000,
        'price_afterdiscount' => null,
        'percent' => null,
        'hpp' => 0,
    ]);

    $transaction = makeSelfOrderCashTransaction();

    TransactionItem::query()->create([
        'transaction_id' => $transaction->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'quantity' => 1,
        'price' => 10000,
        'hpp_unit' => null,
        'hpp_total' => 0,
        'subtotal' => 10000,
        'note' => null,
    ]);

    $component = Livewire::test(PosPage::class)
        ->call('loadPending', $transaction->id)
        ->assertSet('editingTransactionId', $transaction->id);

    // Scheduler membatalkan pesanan saat kasir masih membuka pembayarannya.
    DB::table('transactions')->where('id', $transaction->id)->update([
        'payment_status' => 'expired',
        'order_status' => 'cancelled',
        'updated_at' => now(),
    ]);

    $component
        ->set('paymentMethod', 'cash')
        ->set('cashReceived', '20000')
        ->call('checkout')
        ->assertDispatched('toast', type: 'error');

    $transaction->refresh();

    expect($transaction->payment_status)->toBe('expired');
    expect($transaction->paid_at)->toBeNull();
    expect($transaction->total)->toBe(10000);
});
