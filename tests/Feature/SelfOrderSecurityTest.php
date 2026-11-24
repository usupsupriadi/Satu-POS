<?php

use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Ingredient;
use App\Models\InventoryMovement;
use App\Models\Member;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantRecipe;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Services\SelfOrder\TableQrSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function seedSelfOrderProductFixture(float $ingredientOnHand): array
{
    $category = Category::query()->create(['name' => 'Food']);

    $table = DiningTable::query()->create([
        'table_number' => 'A1',
        'image' => null,
        'qr_value' => null,
    ]);

    $product = Product::query()->create([
        'name' => 'Nasi Goreng Spesial',
        'description' => 'Test',
        'image' => 'test.png',
        'is_available' => true,
        'category_id' => $category->id,
    ]);

    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'name' => 'Normal',
        'price' => 10000,
        'hpp' => 0,
    ]);

    $ingredient = Ingredient::query()->create([
        'name' => 'Beras',
        'sku' => null,
        'unit' => 'pcs',
        'cost_price' => 1000,
        'reorder_level' => 0,
        'is_active' => true,
    ]);

    InventoryMovement::query()->create([
        'ingredient_id' => $ingredient->id,
        'supplier_id' => null,
        'type' => 'opening_balance',
        'quantity' => $ingredientOnHand,
        'input_quantity' => null,
        'input_unit' => null,
        'unit_cost' => null,
        'input_unit_cost' => null,
        'reference_type' => null,
        'reference_id' => null,
        'note' => null,
        'happened_at' => now(),
    ]);

    ProductVariantRecipe::query()->create([
        'product_variant_id' => $variant->id,
        'ingredient_id' => $ingredient->id,
        'quantity' => 1,
    ]);

    return compact('table', 'product', 'variant', 'ingredient');
}

test('online self-order payment is rejected when ingredient stock is insufficient', function () {
    $fixture = seedSelfOrderProductFixture(0);

    $paymentToken = Str::random(32);
    $csrf = Str::random(40);

    $response = $this
        ->withSession([
            '_token' => $csrf,
            'dining_table_id' => $fixture['table']->id,
            'self_order_token' => Str::random(40),
            'customer_ready' => true,
            'name' => 'Guest',
            'phone' => '628123',
            'cart_items' => [[
                'id' => (int) $fixture['product']->id,
                'variant_id' => (int) $fixture['variant']->id,
                'quantity' => 1,
                'selected' => true,
                'note' => '',
            ]],
            'payment_token' => $paymentToken,
        ])
        ->post(route('self-order.payment.pay'), [
            '_token' => $csrf,
            'action' => 'pay',
            'method' => 'online',
            'token' => $paymentToken,
        ]);

    $response->assertRedirect(route('self-order.payment.cart'));
    $response->assertSessionHas('error');

    expect(Transaction::query()->count())->toBe(0);
});

test('payment status page hides order details unless the token is known', function () {
    Setting::current()->update(['tax_rate' => 0, 'rounding_base' => 0]);

    $fixture = seedSelfOrderProductFixture(10);

    $trx = Transaction::query()->create([
        'code' => 'TRX-PRIV-001',
        'member_id' => null,
        'channel' => 'self_order',
        'name' => 'Guest',
        'phone' => '628123',
        'email' => null,
        'order_type' => 'dine_in',
        'dining_table_id' => $fixture['table']->id,
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
        'payment_status' => 'paid',
        'order_status' => 'new',
        'paid_at' => now(),
        'external_id' => (string) Str::uuid(),
        'self_order_token' => Str::random(40),
    ]);

    TransactionItem::query()->create([
        'transaction_id' => $trx->id,
        'product_id' => $fixture['product']->id,
        'product_variant_id' => $fixture['variant']->id,
        'quantity' => 1,
        'price' => 10000,
        'subtotal' => 10000,
        'note' => null,
    ]);

    $this->get(route('self-order.payment.status', ['code' => $trx->code]))
        ->assertOk()
        ->assertSee('Rincian item hanya bisa dilihat dari perangkat yang memesan.')
        ->assertDontSee('Nasi Goreng Spesial')
        ->assertDontSee((string) $trx->self_order_token);

    $this->get(route('self-order.payment.status', [
        'code' => $trx->code,
        'token' => $trx->self_order_token,
    ]))
        ->assertOk()
        ->assertSee('Nasi Goreng Spesial');
});

test('member registration treats 08xx and 628xx as the same phone number', function () {
    Member::query()->create([
        'name' => 'Existing',
        'email' => 'existing@example.com',
        'phone' => '628111222333',
        'points' => 0,
        'email_verified_at' => now(),
        'verification_token' => null,
    ]);

    $response = $this->post(route('members.register'), [
        'name' => 'Baru',
        'email' => 'baru@example.com',
        'phone' => '08111222333',
        'province' => 'Jawa Barat',
        'regency' => 'Kota Bandung',
        'district' => 'Coblong',
    ]);

    $response->assertSessionHasErrors('phone');
    expect(Member::query()->count())->toBe(1);
});

test('email verification link rejects expired or tampered table qr', function () {
    $table = DiningTable::query()->create([
        'table_number' => 'A1',
        'image' => null,
        'qr_value' => null,
    ]);

    $code = basename((string) parse_url((string) $table->qr_value, PHP_URL_PATH));
    $signer = app(TableQrSigner::class);
    $pastExpiry = now()->subDay()->timestamp;

    $member = Member::query()->create([
        'name' => 'Member A',
        'email' => 'member@example.com',
        'phone' => '628111',
        'points' => 0,
        'email_verified_at' => null,
        'verification_token' => Str::random(40),
    ]);

    $expiredUrl = rtrim((string) config('app.url'), '/')
        .'/t/'.$code.'?e='.$pastExpiry.'&s='.$signer->signature($code, $pastExpiry);

    $this->get(route('members.verify', ['token' => $member->verification_token]).'?t='.urlencode($expiredUrl))
        ->assertOk()
        ->assertSessionMissing('dining_table_id');

    $tamperedUrl = rtrim((string) config('app.url'), '/')
        .'/t/'.$code.'?e='.now()->addDay()->timestamp.'&s=deadbeef';

    $this->get(route('members.verify', ['token' => $member->verification_token]).'?t='.urlencode($tamperedUrl))
        ->assertOk()
        ->assertSessionMissing('dining_table_id');
});

test('email verification link accepts a valid signed table qr', function () {
    $table = DiningTable::query()->create([
        'table_number' => 'A1',
        'image' => null,
        'qr_value' => null,
    ]);

    $member = Member::query()->create([
        'name' => 'Member A',
        'email' => 'member@example.com',
        'phone' => '628111',
        'points' => 0,
        'email_verified_at' => null,
        'verification_token' => Str::random(40),
    ]);

    $this->get(route('members.verify', ['token' => $member->verification_token]).'?t='.urlencode((string) $table->qr_value))
        ->assertRedirect(route('self-order.home'))
        ->assertSessionHas('dining_table_id', $table->id);
});
