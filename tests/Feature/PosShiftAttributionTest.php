<?php

use App\Livewire\Pos\PosPage;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\ShiftSession;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Shifts\ShiftSessionService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $category = Category::create(['name' => 'Food']);
    $product = Product::create([
        'name' => 'Nasi Goreng',
        'description' => 'Test',
        'image' => 'test.png',
        'is_available' => true,
        'category_id' => $category->id,
    ]);

    $this->variant = ProductVariant::create([
        'product_id' => $product->id,
        'name' => 'Normal',
        'price' => 50000,
        'stock' => 10,
        'hpp' => 20000,
    ]);

    $this->cashier = User::factory()->create();
    $this->cashier->givePermissionTo(['pos.access', 'shifts.view', 'shifts.sessions.open', 'shifts.sessions.close']);
});

test('pos checkout links the transaction to the open cash session', function () {
    $session = app(ShiftSessionService::class)->open($this->cashier, null, 100000);

    Livewire::actingAs($this->cashier)
        ->test(PosPage::class)
        ->call('addVariantToCart', $this->variant->id)
        ->set('customerName', 'Guest')
        ->set('paymentMethod', 'cash')
        ->set('cashReceived', 50000)
        ->call('checkout')
        ->assertHasNoErrors();

    $transaction = Transaction::query()->latest('id')->first();

    expect($transaction)->not->toBeNull()
        ->and((int) $transaction->shift_session_id)->toBe((int) $session->id);
});

test('pos checkout without an open session leaves the attribution empty', function () {
    Livewire::actingAs($this->cashier)
        ->test(PosPage::class)
        ->call('addVariantToCart', $this->variant->id)
        ->set('customerName', 'Guest')
        ->set('paymentMethod', 'cash')
        ->set('cashReceived', 50000)
        ->call('checkout')
        ->assertHasNoErrors();

    expect(Transaction::query()->latest('id')->first()?->shift_session_id)->toBeNull();
});

test('pos blocks checkout when shift session is required and none is open', function () {
    Setting::current()->update(['shift_required_for_pos' => true]);

    Livewire::actingAs($this->cashier)
        ->test(PosPage::class)
        ->call('addVariantToCart', $this->variant->id)
        ->set('customerName', 'Guest')
        ->set('paymentMethod', 'cash')
        ->set('cashReceived', 50000)
        ->call('checkout');

    expect(Transaction::query()->count())->toBe(0);
});

test('pos allows checkout when shift session is required and a session is open', function () {
    Setting::current()->update(['shift_required_for_pos' => true]);
    $session = app(ShiftSessionService::class)->open($this->cashier, null, 0);

    Livewire::actingAs($this->cashier)
        ->test(PosPage::class)
        ->call('addVariantToCart', $this->variant->id)
        ->set('customerName', 'Guest')
        ->set('paymentMethod', 'cash')
        ->set('cashReceived', 50000)
        ->call('checkout')
        ->assertHasNoErrors();

    $transaction = Transaction::query()->latest('id')->first();

    expect($transaction)->not->toBeNull()
        ->and((int) $transaction->shift_session_id)->toBe((int) $session->id)
        ->and(ShiftSession::query()->count())->toBe(1);
});
