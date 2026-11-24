<?php

use App\Livewire\Pos\PosPage;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\TransactionEvent;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\Shifts\ShiftSessionService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->category = Category::query()->create(['name' => 'Food']);

    $this->product = Product::query()->create([
        'name' => 'Nasi Goreng',
        'description' => 'Test',
        'image' => 'test.png',
        'is_available' => true,
        'category_id' => $this->category->id,
    ]);

    $this->variant = ProductVariant::query()->create([
        'product_id' => $this->product->id,
        'name' => 'Normal',
        'price' => 50000,
        'stock' => 10,
        'hpp' => 0,
    ]);
});

test('self-order cash pending paid at POS is attributed to the cashier shift', function () {
    $cashier = User::factory()->create();
    $cashier->assignRole('cashier');

    $table = DiningTable::query()->create([
        'table_number' => 'A1',
        'image' => null,
        'qr_value' => null,
    ]);

    $session = app(ShiftSessionService::class)->open($cashier, null, 100000);

    $trx = Transaction::query()->create([
        'code' => 'TRX-SELF-CASH',
        'member_id' => null,
        'channel' => 'self_order',
        'name' => 'Guest',
        'phone' => '628123',
        'email' => null,
        'order_type' => 'dine_in',
        'dining_table_id' => $table->id,
        'subtotal' => 10000,
        'voucher_discount_amount' => 0,
        'manual_discount_amount' => 0,
        'discount_total_amount' => 0,
        'tax_percentage' => 0,
        'tax_amount' => 0,
        'rounding_amount' => 0,
        'total' => 10000,
        'checkout_link' => '-',
        'payment_method' => 'cash',
        'payment_status' => 'pending',
        'order_status' => 'new',
        'external_id' => (string) Str::uuid(),
        'self_order_token' => Str::random(40),
    ]);

    TransactionItem::query()->create([
        'transaction_id' => $trx->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 1,
        'price' => 10000,
        'subtotal' => 10000,
        'note' => null,
    ]);

    Livewire::actingAs($cashier)
        ->test(PosPage::class)
        ->call('loadPending', $trx->id)
        ->set('paymentMethod', 'cash')
        ->set('cashReceived', 100000)
        ->call('checkout')
        ->assertHasNoErrors();

    $trx->refresh();

    expect((string) $trx->payment_status)->toBe('paid')
        ->and((int) $trx->shift_session_id)->toBe((int) $session->id);

    $summary = app(ShiftSessionService::class)->cashSummary($session);
    expect((int) $summary['transaction_count'])->toBe(1)
        ->and((int) $summary['cash_sales'])->toBe(10000);
});

test('cashier without settings.edit cannot zero out the tax rate', function () {
    Setting::current()->update([
        'tax_rate' => 10,
        'rounding_base' => 0,
        'discount_applies_before_tax' => true,
    ]);

    $cashier = User::factory()->create();
    $cashier->assignRole('cashier');

    Livewire::actingAs($cashier)
        ->test(PosPage::class)
        ->call('addVariantToCart', $this->variant->id)
        ->set('taxRate', 0)
        ->set('customerName', 'Budi')
        ->set('paymentMethod', 'cash')
        ->set('cashReceived', 100000)
        ->call('checkout')
        ->assertHasNoErrors();

    $trx = Transaction::query()->latest('id')->first();

    expect($trx)->not->toBeNull()
        ->and((float) $trx->tax_percentage)->toBe(10.0)
        ->and((int) $trx->tax_amount)->toBe(5000)
        ->and(TransactionEvent::query()->where('action', 'tax_override')->count())->toBe(0);
});

test('authorized tax override is kept and written to the audit trail', function () {
    Setting::current()->update([
        'tax_rate' => 10,
        'rounding_base' => 0,
        'discount_applies_before_tax' => true,
    ]);

    $manager = User::factory()->create();
    $manager->givePermissionTo(['pos.access', 'settings.edit']);

    Livewire::actingAs($manager)
        ->test(PosPage::class)
        ->call('addVariantToCart', $this->variant->id)
        ->set('taxRate', 0)
        ->set('customerName', 'Budi')
        ->set('paymentMethod', 'cash')
        ->set('cashReceived', 100000)
        ->call('checkout')
        ->assertHasNoErrors();

    $trx = Transaction::query()->latest('id')->first();

    expect((float) $trx->tax_percentage)->toBe(0.0)
        ->and((int) $trx->tax_amount)->toBe(0)
        ->and(TransactionEvent::query()->where('action', 'tax_override')->count())->toBe(1);
});
